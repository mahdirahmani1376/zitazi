const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser;

async function getVariations(url) {
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

    const context = await browser.createBrowserContext();
    const page = await context.newPage();

    try {
        const response = await page.goto(url, {
            waitUntil: 'domcontentloaded'
        });

        console.log('status:', response?.status());
        console.log('data:', await page.content());
    } finally {
        await context.close().catch(() => {
        });
        await browser.close().catch(() => {
        });
    }
}

const url = process.argv[2];

if (!url) {
    console.error('Usage: node test.js <url>');
    process.exit(1);
}

getVariations(url).catch(error => {
    console.error(error);
    process.exit(1);
});