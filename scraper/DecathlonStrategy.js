const {BaseStrategy, delay, safeClose, isShuttingDown} = require('./BaseStrategy');
const fs = require('fs');

const CURRENCY_SYMBOL_TO_CODE = {
    '₺': 'try',
    '€': 'eur',
    '$': 'usd',
    '£': 'gbp',
};
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

            const elHandle = await page.waitForSelector('script[type="application/ld+json"]', {timeout: 15000});
            const el = await page.evaluate((e) => e.textContent, elHandle);
            const targetData = JSON.parse(el);

            const sourceProductId = targetData['@graph'][0]['productGroupID']
            const brand = targetData['@graph'][0]['brand']['name']

            const variations = [];

            const variationIdSelector = 'div.product-info span.vp-caption'
            const colorSelector = '#content > article div.tiled-layout div.variant-image-tile'
            const priceSelector = '#content article.product-view section.buy-box__section span.vp-price-amount'
            const stockSelector = '#content article.product-view section.buy-box__section span.vp-price-label'
            const colorNameSelector = '#content > article fieldset[aria-labelledby="variant-selector-colour"] div.variant-selector-headline__value p.vp-body-s'
            const singleSizeSelector = '#content > article fieldset[aria-labelledby="variant-selector-size"] div.variant-selector-headline__value p.vp-body-s'
            const sizesSelector = '#content > article fieldset[aria-labelledby="variant-selector-size"] div.variant-text-tile'


            let hasColors = false;
            try {
                // 1. Wait for at least one element to load to ensure the section is ready
                await page.waitForSelector(colorSelector, { timeout: 15000 });
                hasColors = true;
            } catch (e) {

            }

            // When the product has no color selector, treat it as a single
            // "pseudo-color" so both cases share the same loop below.
            const colorHandles = hasColors ? await page.$$(colorSelector) : [null];

            // Loop through each color (or the single pseudo-color) and collect variations.
            for (const handle of colorHandles) {
                if (handle) await handle.click();

                const sourceVariationId = await this.extractVariationId(page, variationIdSelector);
                const url = page.url();
                const color = await this.readElementText(page, colorNameSelector);
                const sizeHandles = await page.$$(sizesSelector);

                if (sizeHandles.length > 0) {
                    for (let i = 0; i < sizeHandles.length; i++) {
                        // Re-query the tile fresh on each iteration. After a click the DOM
                        // re-renders and previously collected handles get detached
                        // ("Node is detached from document"). A fresh handle also lets us
                        // read the size text straight from the element we clicked instead
                        // of scanning the whole DOM again.
                        const sizeHandle = (await page.$$(sizesSelector))[i];
                        if (!sizeHandle) continue;

                        const size = await this.readSizeFromHandle(page, sizeHandle);
                        await sizeHandle.click();

                        const {price, variationPriceCurrency, stock} = await this.extractPriceAndStock(page, priceSelector, stockSelector);

                        if ((size === "36" || size === 36) && (sourceVariationId === 8547764 || sourceVariationId === "8547764")) {
                            console.log('saving dome')
                        }

                        variations.push(this.buildVariation({
                            sourceProductId,
                            sourceVariationId,
                            price,
                            url,
                            stock,
                            priceCurrency: variationPriceCurrency,
                            brand,
                            size,
                            color,
                        }));
                    }
                } else {
                    const {price, variationPriceCurrency, stock} = await this.extractPriceAndStock(page, priceSelector, stockSelector);
                    const size = await this.readSingleSize(page, singleSizeSelector);

                    variations.push(this.buildVariation({
                        sourceProductId,
                        sourceVariationId,
                        price,
                        url,
                        stock,
                        priceCurrency: variationPriceCurrency,
                        brand,
                        size,
                        color,
                    }));
                }
            }


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

            let deleted = false;
            if (error.message === 'offers is not iterable') {
                deleted = true
            }

            await this.saveDomSnapshot(page)

            return {
                product_id: productData.id,
                success: false,
                response_status: response?.status(),
                response_headers: response?.headers(),
                error,
                blocked: false,
                deleted: deleted
            };
        } finally {
            if (page) await safeClose(() => page.close());
            if (isShuttingDown() || closeBrowser) await this.closeBrowser();
        }
    }

    /**
     * Parse a Decathlon price shown in the DOM, e.g. "₺395,00" or "₺1.410,00".
     *
     * - Extracts the leading currency symbol and maps it to a currency code (₺ -> try).
     * - Drops everything after the decimal comma, including the comma.
     * - Strips thousand separators and whitespace.
     *
     * Returns e.g. { price: '395', priceCurrency: 'try' } or { price: '1410', priceCurrency: 'try' }.
     */
    parseDecathlonPrice(rawPrice) {
        const text = String(rawPrice ?? '').trim();
        if (!text) {
            return {price: '', priceCurrency: null};
        }

        const symbol = text[0];
        const priceCurrency = CURRENCY_SYMBOL_TO_CODE[symbol] ?? null;

        const amount = text
            .slice(1)               // drop the currency symbol
            .split(',')[0]          // drop the decimal part and comma
            .replace(/[.\s]/g, ''); // strip thousand separators and whitespace

        return {price: amount,priceCurrency: priceCurrency};
    }

    async extractPriceAndCurrency(page, priceSelector) {
        let rawPrice = await page.$eval(priceSelector, el => {
            // Find all child nodes of the span
            for (const node of el.childNodes) {
                // Node.TEXT_NODE means it's raw text (not an inner HTML tag like <span>)
                if (node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent.trim();
                    // If the text node actually contains characters (and isn't just whitespace)
                    if (text) {
                        return text;
                    }
                }
            }
            return '';
        });

        const {price, priceCurrency: variationPriceCurrency} = this.parseDecathlonPrice(rawPrice);
        return {price, variationPriceCurrency};
    }

    /**
     * Read the variation id from the "product info" caption and normalize it:
     * "Ürün No: 123 456" -> "123456".
     */
    async extractVariationId(page, variationIdSelector) {
        const raw = await this.readElementText(page, variationIdSelector);
        return raw.split(' ')[1].replace(/[.\s]/g, '');
    }

    /**
     * Read the text content of the first element matching the selector,
     * waiting up to 15s for it to appear.
     */
    async readElementText(page, selector, {timeout = 15000} = {}) {
        const handle = await page.waitForSelector(selector, {timeout});
        return page.evaluate((e) => e.textContent, handle);
    }

    /**
     * Read and normalize the size text straight from the clicked tile handle
     * ("M" -> "M", "39/40" -> "39/40"). On failure, save a DOM snapshot for debugging.
     */
    async readSizeFromHandle(page, sizeHandle) {
        const size = await page.evaluate((el) => el.textContent.trim(), sizeHandle);
        return size.replace(/[.\s]/g, '');
    }

    /**
     * Read the single (no size-tiles) size label and normalize it.
     */
    async readSingleSize(page, singleSizeSelector) {
        let raw = null
        try {
            raw = (await this.readElementText(page, singleSizeSelector)).replace(/[.\s]/g, '');
        } catch (e) {

        }

        return raw;
    }

    /**
     * Extract the price (with currency) and the stock state for the currently
     * selected variant. If the stock label element is found, the variant is
     * considered out of stock (0); otherwise it's in stock (88).
     */
    async extractPriceAndStock(page, priceSelector, stockSelector) {
        const {price, variationPriceCurrency} = await this.extractPriceAndCurrency(page, priceSelector);
        const outOfStock = await page.$(stockSelector);

        return {price, variationPriceCurrency, stock: outOfStock ? 0 : 88};
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
     * Build a single variation row. Normalizes brand, and uses `stock` already
     * resolved to 0 (out of stock) or 88 (in stock).
     */
    buildVariation({sourceProductId, sourceVariationId, price, url, stock, priceCurrency, brand, size, color}) {
        return {
            decathlon_product_id: sourceProductId,
            source_variation_id: sourceVariationId,
            price,
            url: url ?? null,
            stock,
            priceCurrency,
            brand: brand.replace(/[.\s]/g, ''),
            size,
            color,
        };
    }
}

module.exports = DecathlonStrategy;