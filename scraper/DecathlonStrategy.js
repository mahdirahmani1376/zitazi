const fs = require('fs');
const {BaseStrategy, delay, safeClose, isShuttingDown} = require('./BaseStrategy');
const {extractItemGroupFromHtml} = require('./parse-next-flight');

/**
 * Decathlon product scraper.
 *
 * Instead of clicking through every color/size tile and reading CSS selectors,
 * the strategy extracts the product catalog from the Next.js RSC "flight"
 * payload (`self.__next_f` chunks) embedded in the page. See
 * `parse-next-flight.js` for the payload extraction.
 *
 * The flight data provides an `itemGroup` object:
 *
 *   itemGroup
 *   ├── id                         → decathlon_product_id (e.g. "306417")
 *   ├── skuGroups[]                → one per color/variation
 *   │   ├── modelId                → source_variation_id (the ?mc= value)
 *   │   ├── varianceAttributes.colors[].name → color name(s) for the group
 *   │   └── skus[]                 → every size in this color
 *   │       ├── skuId              → sku_id (source sku UUID)
 *   │       ├── providerSkuCode.skuCode → sku (matches existing DB values)
 *   │       ├── sizeGridValue      → size
 *   │       ├── isAvailable        → stock (true/false) — authoritative
 *   │       ├── isHomeOrStoreDeliveryAvailable → stock fallback when isAvailable is absent
 *   │       ├── offers[].fixedPrices[].typeTargets[].currencies.main
 *   │       │     .valueWithTaxes/.currency → price (integer, TRY)
 *   │       ├── brand.name         → brand
 *   │       └── url                → relative product URL
 *
 * Stock must come from this payload, never from the
 * `/api/product/tr-TR/availabilities/stocks` endpoint: that call returns 403
 * while the IP is challenged, and treating an empty answer as "out of stock"
 * zeroed every variation in the catalogue. When availability cannot be read
 * from the payload the product is reported as blocked instead.
 *
 * When the native browser is blocked by Cloudflare the product is fetched
 * through ScrapeOps instead (see `scrapeViaScrapeOps`), so a hard ban does not
 * stall the queue.
 */
class DecathlonStrategy extends BaseStrategy {
    /** Cloudflare / WAF interstitial markers (checked in title, DOM and body text). */
    static CHALLENGE_PATTERN = /just a moment|attention required|cf-challenge|challenge-platform|verifying you are human|checking your browser/i;

    /**
     * Minimum time to wait before any judgement is made. Cloudflare interstitials
     * often resolve themselves in a few seconds and reload the real page, so
     * calling it a ban earlier than this would produce false positives that
     * cost a whole cooldown cycle.
     */
    static MIN_JUDGE_WINDOW_MS = 15000;

    /** How long to keep polling for the payload before giving up. */
    static PAYLOAD_POLL_TIMEOUT_MS = 45000;

    /** Extra time granted when a challenge is still visibly resolving. */
    static CHALLENGE_GRACE_TIMEOUT_MS = 90000;

    constructor() {
        super('Decathlon');
    }

    /**
     * @param {object} productData queue payload (`{id, decathlon_url}`)
     * @param {{skipBrowser?: boolean, allowFallback?: boolean}} [options]
     *        `skipBrowser` is set by the worker while the native browser is
     *        cooling down after a hard ban; `allowFallback` enables the
     *        ScrapeOps fallback. Both are false during a normal block so a
     *        short ban is handled by backoff alone, without spending credits.
     */
    async scrape(productData, options = {}) {
        if (!productData.decathlon_url?.trim()) {
            return {product_data: productData, success: false, message: 'empty url provided'};
        }

        // While a hard ban is in effect, go straight to the proxy fallback.
        // The native browser is retried as soon as the cooldown expires, so the
        // switch back to the default path is automatic once the ban lifts.
        if (options.skipBrowser === true) {
            return (await this.scrapeViaScrapeOps(productData))
                ?? this.blockedResult(productData, {block_reason: 'browser_cooling_down'});
        }

        const native = await this.scrapeWithBrowser(productData);

        if (native.blocked !== true || options.allowFallback !== true) {
            return native;
        }

        // Native attempt was blocked during a hard ban: try the proxy fallback
        // so the queue keeps flowing. If the fallback is unavailable or fails,
        // hand back the native result so the worker re-queues with backoff.
        return (await this.scrapeViaScrapeOps(productData, native)) ?? native;
    }

    /**
     * Scrape through the real headful browser (the default path).
     */
    async scrapeWithBrowser(productData) {
        let page = null;
        let response = null;
        let closeBrowser = false;

        try {
            const browser = await this.ensureBrowser();
            page = await browser.newPage();

            await page.setRequestInterception(true);
            page.on('request', (req) => {
                const type = req.resourceType();
                if (type === 'image' || type === 'font' || type === 'media') req.abort();
                else req.continue();
            });

            const preDelay = Math.floor(Math.random() * 15000 + 5000);
            await delay(preDelay);

            try {
                response = await page.goto(productData.decathlon_url, {waitUntil: 'domcontentloaded', timeout: 1000 * 60});
            } catch (err) {
                // A navigation that dies mid-flight can still leave the real page
                // behind (Cloudflare reloads it), so let the settle loop decide.
                console.error(JSON.stringify({
                    message: 'decathlon navigation error',
                    error: {name: err.name, message: err.message},
                    data: productData,
                    level: 'error',
                }));
            }

            // Wait for the product payload to settle. Cloudflare commonly serves
            // "Just a moment..." (sometimes with a 403 status) and then resolves
            // the challenge on its own and reloads the real page. That is NOT a
            // ban, so a challenge or a 403 seen here is only a reason to keep
            // waiting — never an immediate block.
            const settled = await this.waitForProductPayload(page, productData);

            if (settled.deleted) {
                return settled.deleted;
            }

            if (!settled.ok) {
                const httpBlocked = [403, 429].includes(response?.status());

                if (settled.challengeSeen || httpBlocked) {
                    console.error(JSON.stringify({
                        message: 'decathlon anti-bot block persisted after waiting',
                        block_reason: settled.challengeSeen ? 'challenge_page' : 'http_403',
                        status: response?.status() ?? null,
                        waited_ms: settled.waitedMs,
                        data: productData,
                        level: 'error',
                    }));
                    closeBrowser = true;
                    return this.blockedResult(productData, {
                        block_reason: settled.challengeSeen ? 'challenge_page' : 'http_403',
                        response_status: response?.status() ?? null,
                        response_headers: response?.headers() ?? {},
                    });
                }

                // No challenge, no payload, no block status: a genuine data
                // failure rather than a ban, so the product is marked failed
                // instead of being re-queued forever.
                return {
                    product_id: productData.id,
                    success: false,
                    response_status: response?.status() ?? null,
                    response_headers: response?.headers() ?? {},
                    blocked: false,
                };
            }

            const html = await page.content();
            const itemGroups = extractItemGroupFromHtml(html);
            const itemGroup = itemGroups[0];

            if (!itemGroup) {
                throw new Error('itemGroup not found in next flight data');
            }

            const origin = page.url();
            const result = this.buildVariationsFromItemGroup(itemGroup, origin);

            if (result.blocked) {
                console.error(JSON.stringify({
                    message: 'decathlon payload is not trustworthy',
                    block_reason: result.block_reason,
                    product_id: productData.id,
                    data: productData,
                    level: 'error',
                }));

                return this.blockedResult(productData, {
                    block_reason: result.block_reason,
                    response_status: response?.status() ?? null,
                    response_headers: response?.headers() ?? {},
                });
            }

            return {
                product_id: productData.id,
                response_data: result.variations,
                success: (response?.status() >= 200 && response?.status() < 400),
                response_status: response ? response.status() : null
            };
        } catch (err) {
            const error = {name: err.name, message: err.message};
            if (error.name === 'TimeoutError' || error.message.includes('Target.createTarget timed out')) {
                console.error(JSON.stringify({message: 'Decathlon browser had timeout', error}));
                closeBrowser = true;
            }

            return {
                product_id: productData.id,
                success: false,
                response_status: response?.status(),
                response_headers: response?.headers(),
                error,
                blocked: false,
                // A browser crash / timeout is NOT a deletion. Only the
                // explicit redirect check (deletedResultIfRedirected) may mark
                // a product as deleted.
                deleted: false
            };
        } finally {
            if (page) await safeClose(() => page.close());
            if (isShuttingDown() || closeBrowser) await this.closeBrowser();
        }
    }

    /**
     * Fetch the product page through the ScrapeOps proxy and parse the same
     * Next.js flight payload out of the returned HTML.
     *
     * Used when the native browser hits a Cloudflare block, so a hard ban does
     * not stall the queue. The proxy renders JavaScript server-side, so no
     * browser is needed and no stocks API call is made — stock comes from the
     * payload exactly like the native path.
     *
     * Returns `null` when the fallback is unconfigured or fails; the caller
     * then uses the native blocked result so the worker re-queues normally.
     * There is no request budget cap: the daily counter is telemetry for the
     * Telegram report, not a limiter.
     */
    async scrapeViaScrapeOps(productData, nativeResult = null) {
        const apiKey = process.env.SCRAPE_OPS_KEY;
        const targetUrl = productData.decathlon_url;

        if (!apiKey) {
            console.error(JSON.stringify({
                message: 'decathlon scrapeops fallback skipped: SCRAPE_OPS_KEY is not set',
                product_id: productData.id,
                level: 'error',
            }));

            return null;
        }

        try {
            const params = new URLSearchParams({
                api_key: apiKey,
                url: targetUrl,
                render_js: 'true',
                country: 'tr',
            });

            const response = await fetch(`https://proxy.scrapeops.io/v1/?${params.toString()}`, {
                headers: {'Accept-Encoding': 'gzip'},
                signal: AbortSignal.timeout(180000),
            });

            const html = await response.text();

            if (!response.ok) {
                console.error(JSON.stringify({
                    message: 'decathlon scrapeops fallback failed',
                    product_id: productData.id,
                    status: response.status,
                    level: 'error',
                }));

                return null;
            }

            const itemGroup = extractItemGroupFromHtml(html)[0];

            if (!itemGroup) {
                console.error(JSON.stringify({
                    message: 'decathlon scrapeops fallback found no itemGroup',
                    product_id: productData.id,
                    final_url: targetUrl,
                    level: 'error',
                }));

                return null;
            }

            const result = this.buildVariationsFromItemGroup(itemGroup, targetUrl);

            if (result.blocked) {
                console.error(JSON.stringify({
                    message: 'decathlon scrapeops payload is not trustworthy',
                    block_reason: result.block_reason,
                    product_id: productData.id,
                    level: 'error',
                }));

                return null;
            }

            console.info(JSON.stringify({
                message: 'decathlon scraped through scrapeops fallback',
                product_id: productData.id,
                variations: result.variations.length,
                previous_block_reason: nativeResult?.block_reason ?? null,
                level: 'info',
            }));

            return {
                product_id: productData.id,
                response_data: result.variations,
                success: true,
                scrapeops_fallback: true,
                // The native browser was blocked and the fallback rescued this
                // product: the worker escalates the streak and arms the
                // cooldown so following items skip straight to the fallback.
                native_blocked: nativeResult?.blocked === true,
                response_status: response.status,
            };
        } catch (err) {
            console.error(JSON.stringify({
                message: 'decathlon scrapeops fallback error',
                product_id: productData.id,
                error: {name: err.name, message: err.message},
                level: 'error',
            }));

            return null;
        }
    }

    /**
     * Detect a Cloudflare / WAF interstitial served with a 200 status.
     */
    async isChallengePage(page) {
        return this.challengeState(page);
    }

    /**
     * Light challenge probe: title, Cloudflare's challenge DOM nodes, and the
     * first chunk of visible text. Cheap enough to poll while a challenge
     * resolves itself.
     */
    async challengeState(page) {
        return page.evaluate((patternSource) => {
            const re = new RegExp(patternSource, 'i');

            if (re.test(document.title || '')) {
                return true;
            }

            if (document.querySelector('#challenge-form, #cf-challenge-running, #challenge-stage, #cf-please-wait')) {
                return true;
            }

            const text = (document.body?.innerText || '').slice(0, 2000);

            return re.test(text);
        }, DecathlonStrategy.CHALLENGE_PATTERN.source).catch(() => false);
    }

    /**
     * True once the Next.js flight payload is present in the DOM.
     */
    async hasFlightPayload(page) {
        return page.evaluate(() => {
            const scripts = document.querySelectorAll('script');

            for (const script of scripts) {
                if ((script.textContent || '').includes('self.__next_f.push([1,"')) {
                    return true;
                }
            }

            return false;
        }).catch(() => false);
    }

    /**
     * Wait for the product payload while tolerating Cloudflare interstitials.
     *
     * Cloudflare frequently serves "Just a moment..." — sometimes with a 403
     * status — and then resolves the challenge on its own and reloads the real
     * page. Those cases are NOT a ban, so:
     *
     *   - a payload that arrives is always a success, however early;
     *   - a challenge or 403 is only a reason to keep waiting, never to judge;
     *   - nothing is ever declared blocked before MIN_JUDGE_WINDOW_MS (15s) has
     *     elapsed, so a self-resolving challenge is never mistaken for a ban.
     *
     * @returns {Promise<{ok: boolean, challengeSeen: boolean, waitedMs: number, deleted?: object}>}
     */
    async waitForProductPayload(page, productData) {
        const startedAt = Date.now();
        let challengeSeen = false;

        while (true) {
            const elapsedMs = Date.now() - startedAt;

            if (await this.hasFlightPayload(page)) {
                return {ok: true, challengeSeen, waitedMs: elapsedMs};
            }

            if (await this.challengeState(page)) {
                challengeSeen = true;
            }

            // Only treat a redirect as a deletion when we are past the judge
            // window, the page is not a challenge (a challenge lives on
            // challenges.cloudflare.com, which has no product id either), and
            // the URL is still on Decathlon's own domain.
            const finalUrl = page.url();
            const onDecathlon = /(^|\.)decathlon\.com\.tr/i.test(this.hostOf(finalUrl));

            if (! challengeSeen && onDecathlon && elapsedMs >= DecathlonStrategy.MIN_JUDGE_WINDOW_MS) {
                const deleted = this.deletedResultIfRedirected(productData, null, finalUrl);

                if (deleted) {
                    return {ok: false, challengeSeen, waitedMs: elapsedMs, deleted};
                }
            }

            if (elapsedMs >= DecathlonStrategy.PAYLOAD_POLL_TIMEOUT_MS) {
                // A self-resolving challenge gets a final grace period instead of
                // being judged the moment the poll timeout hits.
                if (challengeSeen && elapsedMs < DecathlonStrategy.CHALLENGE_GRACE_TIMEOUT_MS) {
                    await delay(1000);
                    continue;
                }

                return {ok: false, challengeSeen, waitedMs: elapsedMs};
            }

            await delay(1000 + Math.floor(Math.random() * 250));
        }
    }

    /**
     * Hostname of a URL, or an empty string when it cannot be parsed.
     */
    hostOf(url) {
        try {
            return new URL(url).host;
        } catch (e) {
            return '';
        }
    }

    /**
     * Uniform "anti-bot block" result. It carries no `response_data`, so the
     * PHP side only moves the product to `rate_limited` and never touches
     * variation stock, price or WooCommerce.
     */
    blockedResult(productData, {block_reason, response_status = null, response_headers = {}}) {
        return {
            product_id: productData.id,
            success: false,
            blocked: true,
            block_reason,
            response_status,
            response_headers,
        };
    }

    productIdFromUrl(url) {
        return url?.match(/R-p-(\d+)/i)?.[1] ?? null;
    }

    /**
     * Deleted Decathlon products 302 to a category page while still returning
     * HTTP 200 (`/p/.../_/R-p-340989` -> `/tum-sporlar/binicilik/eyer`).
     * Detect it by the product id disappearing from the final URL. A redirect
     * that keeps a product id (same or replacement) is left to normal handling.
     */
    deletedResultIfRedirected(productData, response, finalUrl) {
        const requestedProductId = this.productIdFromUrl(productData.decathlon_url);

        if (!requestedProductId || this.productIdFromUrl(finalUrl)) {
            return null;
        }

        console.error(JSON.stringify({
            message: 'decathlon product deleted (redirected away)',
            requested_url: productData.decathlon_url,
            final_url: finalUrl,
            data: productData,
            level: 'error',
        }));

        return {
            product_id: productData.id,
            success: false,
            blocked: false,
            deleted: true,
            response_status: response?.status() ?? null,
            response_headers: response?.headers() ?? {},
        };
    }

    /**
     * Flatten the flight payload's `itemGroup` into the variation rows the PHP
     * side expects. One row per (color × size) sku.
     *
     * Returns `{variations, blocked, block_reason}`. `blocked` is set — and no
     * variations are returned — when the payload cannot be trusted, so a bad
     * payload can never be reported as "everything out of stock".
     */
    buildVariationsFromItemGroup(itemGroup, origin) {
        const variations = [];
        let availabilityKnown = 0;

        for (const skuGroup of itemGroup.skuGroups ?? []) {
            // Color(s) for this variation group, e.g. "füme siyah / Neon yavruağzı".
            const colors = (skuGroup.varianceAttributes?.colors ?? [])
                .map((color) => color.name)
                .filter(Boolean)
                .join(' / ');

            for (const sku of skuGroup.skus ?? []) {
                const main = sku.offers?.[0]?.fixedPrices?.[0]?.typeTargets?.[0]?.currencies?.main;
                const url = this.toAbsoluteUrl(origin, sku.url);

                if (this.hasAvailability(sku)) {
                    availabilityKnown++;
                }

                variations.push(this.buildVariation({
                    sourceProductId: itemGroup.id,
                    sourceVariationId: skuGroup.modelId,
                    skuId: sku.skuId,
                    sku: sku.providerSkuCode?.skuCode ?? sku.skuId ?? null,
                    price: main?.valueWithTaxes ?? null,
                    priceCurrency: main?.currency?.toLowerCase() ?? null,
                    url,
                    stock: this.stockFor(sku),
                    brand: sku.brand?.name ?? null,
                    size: sku.sizeGridValue ?? null,
                    color: colors,
                }));
            }
        }

        // Decathlon dropped/renamed the availability fields: this is a payload
        // change, not a sold-out catalogue. Reporting it as a block keeps the
        // previous stock intact instead of zeroing every variation.
        if (variations.length > 0 && availabilityKnown === 0) {
            return {
                variations: [],
                blocked: true,
                block_reason: 'payload_missing_availability',
            };
        }

        // Every size out of stock at once is far more likely a bad payload than
        // a real sold-out product, so treat it as suspect rather than fact.
        if (variations.length > 0 && variations.every((variation) => variation.stock === 0)) {
            return {
                variations: [],
                blocked: true,
                block_reason: 'all_stock_zero_suspicious',
            };
        }

        return {variations, blocked: false, block_reason: null};
    }

    hasAvailability(sku) {
        return typeof sku.isAvailable === 'boolean'
            || typeof sku.isHomeOrStoreDeliveryAvailable === 'boolean';
    }

    /**
     * Stock from the flight payload only. `isAvailable` wins;
     * `isHomeOrStoreDeliveryAvailable` is only used when it is absent.
     * `LOCAL_STOCK_ONLY` is intentionally ignored — it appears on in-stock
     * items too, so using it zeroes products that are actually sellable.
     */
    stockFor(sku) {
        const inStock = sku.isAvailable === true
            || (sku.isAvailable === undefined && sku.isHomeOrStoreDeliveryAvailable === true);

        return inStock ? 88 : 0;
    }

    /**
     * Resolve the relative product URL from the flight payload against the
     * page origin, e.g. "/p/_/R-p-306417?mc=8547764" ->
     * "https://www.decathlon.com.tr/p/_/R-p-306417?mc=8547764".
     */
    toAbsoluteUrl(origin, relativeUrl) {
        if (!relativeUrl) {
            return null;
        }

        if (/^https?:\/\//i.test(relativeUrl)) {
            return relativeUrl;
        }

        return new URL(relativeUrl, origin).toString();
    }

    /**
     * Save the current rendered DOM to a timestamped file for debugging.
     */
    async saveDomSnapshot(page) {
        try {
            const html = await page.content();
            const file = `decathlon-page.html`;
            fs.writeFileSync(file, html);
            console.log(`Saved DOM snapshot to ${file} (${html.length} bytes)`);
        } catch (writeErr) {
            console.error('Failed to save DOM snapshot:', writeErr.message);
        }
    }

    /**
     * Build a single variation row.
     */
    buildVariation({sourceProductId, sourceVariationId, skuId, sku, price, priceCurrency, url, stock, brand, size, color}) {
        return {
            decathlon_product_id: sourceProductId,
            source_variation_id: sourceVariationId,
            sku_id: skuId,
            sku,
            price,
            priceCurrency,
            url: url ?? null,
            stock,
            brand: brand?.replace(/[.\s]/g, '') ?? null,
            size,
            color,
        };
    }
}

module.exports = DecathlonStrategy;
