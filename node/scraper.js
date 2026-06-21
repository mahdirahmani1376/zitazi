const puppeteer = require("puppeteer-extra");
const StealthPlugin = require("puppeteer-extra-plugin-stealth");

puppeteer.use(StealthPlugin());

let browser;

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
    }
    return browser;
}

process.on('SIGINT', async () => {
    console.log('Shutting down...');
    if (browser) await browser.close().catch(() => {
    });
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('Terminating...');
    if (browser) await browser.close().catch(() => {
    });
    process.exit(0);
});

async function beginScrape(data) {
    await getBrowser();

    if (data.decathlon_url) {
        return scrapeDecathlonData(data);
    }

    if (data.trendyol_source) {
        return scrapeTrendyolData(data);
    }

    return {
        product_id: data.id,
        success: false,
        error: "no valid source"
    };
}

async function scrapeDecathlonData(productData) {
    const page = await browser.newPage();
    let response = null;

    try {
        response = await page.goto(productData.decathlon_url, {
            waitUntil: 'domcontentloaded',
            timeout: 90000
        });

        await delay(4000);

        const elHandle = await page.waitForSelector(
            'script[type="application/ld+json"]',
            {timeout: 90000}
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
            variations,
            success: true,
            response_status: response ? response.status() : null,
        };

    } catch (err) {

        const error = {
            name: err.name,
            message: err.message
        };

        return {
            product_id: productData.id,
            success: false,
            response_status: response ? response.status() : null,
            error
        };

    } finally {
        await page.close().catch(() => {
        });
    }
}

async function scrapeTrendyolData(data) {
    const page = await browser.newPage();
    let response = null;

    try {
        await page.goto(data.full_url, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        await delay(4000);

        const responseData = await page.evaluate(() => {
            return JSON.parse(document.body.innerText);
        });

        return {
            product_id: data.id,
            response: responseData,
            response_status: response ? response.status() : null,
            success: true
        };

    } catch (err) {

        const error = {
            name: err.name,
            message: err.message
        };

        return {
            product_id: data.id,
            response_status: response ? response.status() : null,
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