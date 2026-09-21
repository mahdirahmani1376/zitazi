const puppeteer = require("puppeteer-extra");
const StealthPlugin = require("puppeteer-extra-plugin-stealth");

puppeteer.use(StealthPlugin());

let trendyolBrowser;
let decathlonBrowser;
let scraperShuttingDown = false;

const puppeteerOptions = {
    headless: true,
    protocolTimeout: 120000,
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--single-process',
        '--disable-breakpad',
        '--disable-crashpad-for-testing',
    ]
}
async function getTrendyolBrowser() {
    if (scraperShuttingDown) {
        throw new Error('Scraper is shutting down');
    }

    if (!trendyolBrowser) {
        trendyolBrowser = await puppeteer.launch(puppeteerOptions);
        initialTrendyolTime = Date.now();
    }

    return trendyolBrowser;
}

async function getDecathlonBrowser() {
    if (scraperShuttingDown) {
        throw new Error('Scraper is shutting down');
    }

    if (!decathlonBrowser) {
        decathlonBrowser = await puppeteer.launch(puppeteerOptions);
        initialDecathlonTime = Date.now();
    }

    return decathlonBrowser;
}

process.on('SIGINT', () => {
    scraperShuttingDown = true;
});

process.on('SIGTERM', () => {
    scraperShuttingDown = true;
});

let currentTrendyolTime = Date.now()
let initialTrendyolTime = Date.now()

let currentDecathlonTime = Date.now()
let initialDecathlonTime = Date.now()
const BROWSER_RESTART_INTERVAL = 30 * 60 * 1000;

async function beginScrape(name, data) {
    let result = {
        product_id: data.id,
        success: false,
        error: "no valid source"
    };

    if (name === 'Trendyol') {
        if (trendyolBrowser && Date.now() - initialTrendyolTime > BROWSER_RESTART_INTERVAL) {
            trendyolBrowser.close().catch(() => {
            });
            trendyolBrowser = null;
            initialTrendyolTime = Date.now();
        }

        await getTrendyolBrowser();

        result = await scrapeTrendyolData(data);

    } else if (name === "Decathlon") {
        if (decathlonBrowser && Date.now() - initialDecathlonTime > BROWSER_RESTART_INTERVAL) {
            decathlonBrowser.close().catch(() => {
            });
            decathlonBrowser = null;
            initialDecathlonTime = Date.now();
        }

        await getDecathlonBrowser();

        result = await scrapeDecathlonData(data);
    }

    return result;
}

async function scrapeDecathlonData(productData) {
    let response = null;
    let page = null;
    let closeBrowser = false;

    try {
        page = await decathlonBrowser.newPage();
        if (!productData.decathlon_url?.trim()) {
            return {
                product_data: productData,
                success: false,
                message: 'empty url provided'
            };
        }

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

        if ([403, 429].includes(response.status())) {
            console.error(JSON.stringify({
                'message': 'decathlon rate limit',
                'status': response.status(),
                'data': productData,
            }))

            closeBrowser = true

            return {
                product_id: productData.id,
                success: false,
                response_status: response.status(),
                response_headers: response.headers(),
                blocked: true,
            };
        }

        const delayTime = Math.floor(Math.random() * (5000 - 2000) + 10000);
        await delay(delayTime);

        const elHandle = await page.waitForSelector(
            'script[type="application/ld+json"]',
            {timeout: 15000}
        );

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
                    priceCurrency: offer?.priceCurrency
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
            success: (response?.status() >= 200 && response?.status() < 400),
            response_status: response ? response.status() : null,
        };

    } catch (err) {
        const error = {
            name: err.name,
            message: err.message
        };

        if (error.name === "TimeoutError" || error.message.includes('Target.createTarget timed out')) {
            console.error(JSON.stringify({
                message: "Decathlon browser became unhealthy",
                error
            }));

            closeBrowser = true;
        }

        return {
            product_id: productData.id,
            success: false,
            response_status: response ? response.status() : null,
            response_headers: response ? response.headers() : null,
            error
        };

    } finally {
        if (page) {
            page.close().catch(() => {
            });
        }

        if (scraperShuttingDown || closeBrowser) {
            if (decathlonBrowser) {
                decathlonBrowser.close().catch(() => {
                });
                decathlonBrowser = null;
            }
        }

        currentDecathlonTime = Date.now()
    }
}

async function scrapeTrendyolData(data) {
    let response = null;
    let closeBrowser = false;
    let page = null;

    try {
        page = await trendyolBrowser.newPage();

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

        const delayTime = Math.floor(Math.random() * (5000 - 2000) + 5000);
        await delay(delayTime);

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

        if ([404, 410].includes(responseData?.statusCode)) {
            return {
                product_id: data.id,
                response_status: response?.status(),
                response_headers: response?.headers(),
                full_url: data.full_url,
                success: false,
                blocked: false,
                deleted: true,
                retry_count: data.retry_count ?? 0
            };
        }

        if (responseData?.result?.merchantListing?.winnerVariant?.price?.currency !== 'TRY') {
            console.log(JSON.stringify({
                'message': "invalid currency",
                'level': 'error',
                'product_id': data.id,
                'currency': responseData?.result?.merchantListing?.winnerVariant?.price?.currency
            }));

            closeBrowser = true;

            return {
                product_id: data.id,
                response_status: response?.status(),
                response_headers: response?.headers(),
                full_url: data.full_url,
                success: false,
                blocked: false,
                deleted: false,
                invalid_currency: true,
                retry_count: data.retry_count ?? 0
            };
        }


        return {
            product_id: data.id,
            response_data: responseData,
            response_status: response?.status(),
            headers: response.headers(),
            url: response.url(),
            full_url: data.full_url,
            success: responseData?.isSuccess && responseData?.statusCode === 200 && (response?.status() >= 200 && response?.status() < 400)
        };

    } catch (err) {
        const error = {
            name: err.name,
            message: err.message
        };

        if (error.name === "TimeoutError" || error.message.includes('Target.createTarget timed out')) {
            console.error(JSON.stringify({
                message: "Trendyol browser became unhealthy",
                error
            }));

            closeBrowser = true;
        }

        return {
            product_id: data.id,
            response_status: response?.status(),
            response_headers: response?.headers(),
            full_url: data.full_url,
            success: false,
            error
        };

    } finally {
        if (page) {
            page.close().catch(() => {
            });
        }

        if (closeBrowser || scraperShuttingDown) {
            if (trendyolBrowser) {
                trendyolBrowser.close().catch(() => {
                });
                trendyolBrowser = null;
            }
        }

        currentTrendyolTime = Date.now()
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