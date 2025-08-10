const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

async function scrapeUrl(url) {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const page = await browser.newPage();

    try {
        await page.goto(url, {waitUntil: 'networkidle2', timeout: 30000});

        // Try to find the element, but timeout if not found
        const elHandle = await page.$('script[type="application/ld+json"]');
        if (!elHandle) {
            throw new Error('JSON-LD script not found');
        }

        const jsonText = await page.evaluate(el => el.textContent, elHandle);

        let parsed;
        try {
            parsed = JSON.parse(jsonText);
        } catch (err) {
            throw new Error('Invalid JSON-LD format');
        }

        return {success: true, body: parsed};

    } catch (err) {
        return {success: false, error: err.message};

    } finally {
        await browser.close();
    }
}

module.exports = {scrapeUrl};
