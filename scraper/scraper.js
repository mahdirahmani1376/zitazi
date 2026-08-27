const puppeteer = require("puppeteer-extra");
const StealthPlugin = require("puppeteer-extra-plugin-stealth");

puppeteer.use(StealthPlugin());

let browser;
let pageCounter = 0;

async function getBrowser() {
    if (!browser) {
        browser = await puppeteer.launch({
            headless: true,
            protocolTimeout: 60000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-zygote',
            ]
        });

        pageCounter = 0;
    }

    pageCounter++;

    return browser;
}

process.on('SIGINT', async () => {
    console.log(JSON.stringify({
        'message': "Shutting down...",
        'level': 'debug'
    }));

    if (browser) await browser.close().catch(() => {
    });
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log(JSON.stringify({
        'message': "Terminating...",
        'level': 'debug'
    }));
    if (browser) await browser.close().catch(() => {
    });
    process.exit(0);
});

async function beginScrape(name, data) {
    await getBrowser();

    let context = await browser.createBrowserContext();

    try {
        let result = {
            product_id: data.id,
            success: false,
            error: "no valid source"
        };

        if (name === 'Trendyol') {
            result = await scrapeTrendyolData(data, context);

            // Trendyol can return a misleading 404 when the current browser
            // session has stale site state. Retry once with a completely fresh
            // browser context before treating the product as deleted.
            if (result.retryWithFreshContext) {
                await context.close();
                context = await browser.createBrowserContext();

                result = await scrapeTrendyolData(data, context, false);
            }
        } else if (name === "Decathlon") {
            result = await scrapeDecathlonData(data, context);
        }

        return result;
    } finally {
        await context.close().catch(() => {
        });
    }
}

async function scrapeDecathlonData(productData, context) {
    const page = await context.newPage();
    let response = null;
    if (!productData.decathlon_url?.trim()) {
        return {
            product_data: productData,
            success: false,
            message: 'empty url provided'
        };
    }

    try {
        await page.setRequestInterception(true);

        page.on('request', req => {

            const type = req.resourceType();

            if (
                type === 'image' ||
                type === 'font' ||
                type === 'media'
            ) {
                req.abort();
            } else {
                req.continue();
            }

        });

        response = await page.goto(productData.decathlon_url, {
            waitUntil: 'domcontentloaded',
            timeout: 1000 * 60
        });

        if (response.status() === 403) {
            console.error(JSON.stringify({
                'message': 'decathlon rate limit',
                'data': productData,
            }))

            return {
                product_id: productData.id,
                success: false,
                response_status: response.status(),
                response_headers: response.headers(),
                blocked: true,
            };
        }

        const delayTime = Math.floor(Math.random() * (7000 - 2000) + 2000);
        await delay(delayTime);

        const elHandle = await page.waitForSelector(
            'script[type="application/ld+json"]',
            {timeout: 9000}
        );

        if (!elHandle) throw new Error("JSON-LD not found");

        const el = await page.evaluate(el => el.textContent, elHandle);
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
                });

            }
        }

        const scriptHandle = await page.$('#__dkt');
        const scriptHandleData = scriptHandle
            ? await page.evaluate(el => el.textContent, scriptHandle)
            : '';

        for (const variation of variations) {
            if (!variation.sku) continue;

            const pattern = new RegExp(
                `"skuId"\\s*:\\s*"` + variation.sku.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + `"` +
                `\\s*,\\s*"size"\\s*:\\s*"([^"]+)"`,
                "g"
            );

            const match = pattern.exec(scriptHandleData);
            if (match) {
                variation.size = match[1];
            }
        }

        return {
            product_id: productData.id,
            response_data: variations,
            success: response?.ok(),
            response_status: response ? response.status() : null,
        };

    } catch (err) {
        if (err.name === "TimeoutError") {

            await browser.close();

            browser = null;

        }

        const error = {
            name: err.name,
            message: err.message
        };

        return {
            product_id: productData.id,
            success: false,
            response_status: response ? response.status() : null,
            response_headers: response ? response.headers() : null,
            error
        };

    } finally {
        await page.close().catch(() => {
        });
    }
}

async function scrapeTrendyolData(data, context, allowFreshContextRetry = true) {
    const page = await context.newPage();
    let response = null;

    try {
        if (!data.full_url?.trim()) {
            return {
                product_data: data,
                success: false,
                message: 'empty url provided'
            };
        }

        response = await page.goto(data.full_url, {
            waitUntil: 'domcontentloaded',
            timeout: 1000 * 60
        });

        const delayTime = Math.floor(Math.random() * (5000 - 2000) + 2000);
        await delay(delayTime);

        // Trendyol may use HTTP 404 as a soft block for a stale/suspicious
        // browser session. A fresh browser context is equivalent to clearing
        // Trendyol's site data without restarting Chromium.
        if (response?.status() === 404 && allowFreshContextRetry) {
            return {
                product_id: data.id,
                response_status: response.status(),
                response_headers: response.headers(),
                full_url: data.full_url,
                success: false,
                retryWithFreshContext: true
            };
        }

        if (response?.status() === 418) {
            console.error(JSON.stringify({
                'message': 'trendyol tea pot bot blocked',
                'data': data,
                'level': 'error'
            }))

            return {
                product_id: data.id,
                response_status: response?.status(),
                response_headers: response?.headers(),
                full_url: data.full_url,
                success: false,
                blocked: true,
            };
        }

        const responseData = await page.evaluate(() => {
            return JSON.parse(document.body.innerText);
        });

        if (responseData?.statusCode === 404) {
            if (allowFreshContextRetry) {
                console.error(JSON.stringify({
                    'message': 'trendyol 404 response; retrying with fresh browser context',
                    'data': data,
                    'level': 'warning'
                }));

                return {
                    product_id: data.id,
                    response_status: response?.status(),
                    response_headers: response?.headers(),
                    full_url: data.full_url,
                    success: false,
                    retryWithFreshContext: true
                };
            }

            console.error(JSON.stringify({
                'message': 'trendyol product not found after fresh context retry',
                'data': data,
                'level': 'error'
            }));

            return {
                product_id: data.id,
                response_status: response?.status(),
                response_headers: response?.headers(),
                full_url: data.full_url,
                success: false,
                blocked: false,
                deleted: true
            };
        }


        return {
            product_id: data.id,
            response_data: responseData,
            response_status: response?.status(),
            headers: response.headers(),
            url: response.url(),
            full_url: data.full_url,
            success: responseData?.isSuccess && responseData?.statusCode === 200 && response?.ok()
        };

    } catch (err) {

        if (err.name === "TimeoutError") {

            await browser.close();

            browser = null;

        }

        const error = {
            name: err.name,
            message: err.message
        };

        return {
            product_id: data.id,
            response_status: response?.status(),
            response_headers: response?.headers(),
            full_url: data.full_url,
            success: false,
            error
        };

    } finally {
        await page.close().catch(() => {
        });
    }
}

// Source - https://stackoverflow.com/a/46965281
// Posted by Md. Abu Taher, modified by community. See post 'Timeline' for change history
// Retrieved 2026-06-21, License - CC BY-SA 4.0

function delay(time) {
    return new Promise(function (resolve) {
        setTimeout(resolve, time)
    });
}

module.exports = beginScrape;