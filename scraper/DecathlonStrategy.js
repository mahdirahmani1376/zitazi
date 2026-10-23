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
 *   │       ├── isAvailable        → stock (true/false)
 *   │       ├── offers[].fixedPrices[].typeTargets[].currencies.main
 *   │       │     .valueWithTaxes/.currency → price (integer, TRY)
 *   │       ├── brand.name         → brand
 *   │       └── url                → relative product URL
 */
class DecathlonStrategy extends BaseStrategy {
    constructor() {
        super('Decathlon');
    }

    async scrape(productData) {
        if (!productData.decathlon_url?.trim()) {
            return {product_data: productData, success: false, message: 'empty url provided'};
        }

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

            response = await page.goto(productData.decathlon_url, {waitUntil: 'domcontentloaded', timeout: 1000 * 60});

            if ([403, 429].includes(response.status())) {
                // A 403 here is usually the Cloudflare "Just a moment..."
                // challenge page rather than a Decathlon rate limit. Either
                // way it is an anti-bot block: re-queue with backoff.
                console.error(JSON.stringify({
                    message: 'decathlon rate limit',
                    status: response.status(),
                    data: productData,
                    level: 'error'
                }));
                closeBrowser = true;
                return {
                    product_id: productData.id,
                    success: false,
                    response_status: response.status(),
                    response_headers: response.headers(),
                    blocked: true
                };
            }

            // Deleted products 302 to a category page but still return HTTP 200.
            // Detect that here (before the flight-payload wait) so we do not
            // burn 15s waiting on a page that will never contain the product.
            const deleted = this.deletedResultIfRedirected(productData, response, page.url());
            if (deleted) {
                return deleted;
            }

            try {
                // Wait until the page has rendered the flight payload so the
                // product catalog is present. The chunks are pushed into the
                // `window.__next_f` array by plain <script> tags. However, in
                // headless runs the inline `self.__next_f.push(...)` scripts can
                // fail to execute (the global stays an empty array) even though
                // the raw HTML already contains the full flight payload. So wait
                // for the flight marker inside the rendered DOM instead.
                await page.waitForFunction(() => {
                    const scripts = document.querySelectorAll('script');
                    for (const s of scripts) {
                        if ((s.textContent || '').includes('self.__next_f.push([1,"')) {
                            return true;
                        }
                    }
                    return false;
                }, {timeout: 25000});
            } catch (e) {
                // Cloudflare sometimes serves the interstitial challenge with a
                // 200 status. Detect it by the challenge page's distinctive title
                // before burning 15s on the flight-payload wait below.
                const challengeTitle = await page.evaluate(() => document.title).catch(() => '');
                if (challengeTitle.includes('Just a moment')) {
                    console.error(JSON.stringify({
                        message: 'decathlon cloudflare challenge (200)',
                        status: response.status(),
                        data: productData,
                        level: 'error'
                    }));
                    closeBrowser = true;
                    return {
                        product_id: productData.id,
                        success: false,
                        response_status: response.status(),
                        response_headers: response.headers(),
                        blocked: true
                    };
                } else {
                    return this.deletedResultIfRedirected(productData, response, page.url())
                        ?? {
                            product_id: productData.id,
                            success: false,
                            response_status: response.status(),
                            response_headers: response.headers(),
                            blocked: false
                        };
                }
            }

            const html = await page.content();
            const itemGroups = extractItemGroupFromHtml(html);
            const itemGroup = itemGroups[0];

            if (!itemGroup) {
                throw new Error('itemGroup not found in next flight data');
            }

            const origin = page.url();
            const variations = this.buildVariationsFromItemGroup(itemGroup, origin);

            return {
                product_id: productData.id,
                response_data: variations,
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
     */
    buildVariationsFromItemGroup(itemGroup, origin) {
        const variations = [];

        for (const skuGroup of itemGroup.skuGroups ?? []) {
            // Color(s) for this variation group, e.g. "füme siyah / Neon yavruağzı".
            const colors = (skuGroup.varianceAttributes?.colors ?? [])
                .map((color) => color.name)
                .filter(Boolean)
                .join(' / ');

            for (const sku of skuGroup.skus ?? []) {
                const main = sku.offers?.[0]?.fixedPrices?.[0]?.typeTargets?.[0]?.currencies?.main;

                const url = this.toAbsoluteUrl(origin, sku.url);

                variations.push(this.buildVariation({
                    sourceProductId: itemGroup.id,
                    sourceVariationId: skuGroup.modelId,
                    skuId: sku.skuId,
                    sku: sku.providerSkuCode?.skuCode ?? sku.skuId ?? null,
                    price: main?.valueWithTaxes ?? null,
                    priceCurrency: main?.currency?.toLowerCase() ?? null,
                    url,
                    stock: sku.isAvailable ? 88 : 0,
                    brand: sku.brand?.name ?? null,
                    size: sku.sizeGridValue ?? null,
                    color: colors,
                }));
            }
        }

        return variations;
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
