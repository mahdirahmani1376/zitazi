const {BaseStrategy, delay, safeClose, isShuttingDown} = require('./BaseStrategy');

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

            const variations = [];
            const offers = targetData.offers || [];

            for (const baseOffer of offers) {
                for (const offer of baseOffer) {
                    variations.push({
                        decathlon_product_id: targetData.productID,
                        sku: offer.sku ?? null,
                        price: offer.price ?? null,
                        url: offer.url ?? null,
                        stock: offer.availability === 'https://schema.org/InStock' ? 88 : 0,
                        priceCurrency: offer?.priceCurrency,
                    });
                }
            }

            const scriptHandle = await page.$('#__dkt');
            const scriptHandleData = scriptHandle ? await page.evaluate((e) => e.textContent, scriptHandle) : '';

            for (const variation of variations) {
                if (!variation.sku) continue;
                const pattern = new RegExp('"skuId"\\s*:\\s*"' + variation.sku.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '"' + '\\s*,\\s*"size"\\s*:\\s*"([^"]+)"', 'g');
                const match = pattern.exec(scriptHandleData);
                if (match) variation.size = match[1];
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
}

module.exports = DecathlonStrategy;