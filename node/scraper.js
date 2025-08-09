const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

async function scrapeUrl(url) {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const page = await browser.newPage();
        await page.goto(url, {waitUntil: 'networkidle2'});

        const data = await page.evaluate(() => ({
            html: document.documentElement.outerHTML,
        }));

        return data;
    } finally {
        await browser.close();
    }
}

module.exports = {scrapeUrl};
