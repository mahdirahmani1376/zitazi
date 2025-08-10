const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser;

async function getBrowser() {
    if (!browser) {
        browser = await puppeteer.launch({headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox']});
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

module.exports = {scrapeUrl};
