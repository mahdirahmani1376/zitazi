const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser;

async function getBrowser() {
    if (!browser) {
        browser = await puppeteer.launch({
            headless: true,
            protocolTimeout: 300000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-zygote',
                '--single-process',
            ]
        });
    }
    return browser;
}
async function scrapeUrl(url) {
    const browser = await getBrowser();
    const page = await browser.newPage();

    try {
        await page.goto(url, {waitUntil: 'networkidle2', timeout: 60000});

        // Try to find the element, but timeout if not found
        const elHandle = await page.$('script[type="application/ld+json"]');
        if (!elHandle) {
            throw new Error('JSON-LD script not found');
        }

        const jsonText = await page.evaluate(el => el.textContent, elHandle);

        let parsed;
        parsed = JSON.parse(jsonText);

        return {success: true, body: parsed};

    } catch (err) {
        return {success: false, error: err.message};

    } finally {
        await page.close();
    }
}

async function seedUrl(url) {
    const browser = await getBrowser();
    const page = await browser.newPage();

    try {
        await page.goto(url, {waitUntil: 'networkidle2', timeout: 60000});

        const jsonData = await page.evaluate(() => {
            const el = document.querySelector('script[type="application/ld+json"]');
            const targetData = JSON.parse(el.textContent.trim());
            const variations = [];
            const productId = targetData.productID;

            targetData.offers[0].forEach((offer, index) => {
                const stock = offer.availability === 'https://schema.org/InStock' ? 88 : 0;
                variations.push({
                    product_id: productId,
                    sku: offer.sku ?? null,
                    price: offer.price ?? null,
                    url: offer.url ?? null,
                    stock,
                    index
                });
            });

            variations.forEach(variation => {
                const elId = `#sku-${variation.index}`;
                const el = document.querySelector(elId);
                variation.size = el ? el.getAttribute('aria-label') : null;
            });

            return variations;
        });

        return {success: true, body: jsonData};

    } catch (err) {
        return {success: false, error: err.message};

    } finally {
        await page.close();
    }
}

module.exports = {scrapeUrl, seedUrl};
