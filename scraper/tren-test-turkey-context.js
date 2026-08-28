const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser

async function getVariations() {
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

    let context = await browser.createBrowserContext();
    const page = await context.newPage();

    const response = await page.goto('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content?contentId=330697668&merchantId=298133&countryCode=TR', {waitUntil: 'domcontentloaded'});

    console.log('status', response.status())

    console.log('data', await page.content())

    await page.close();

    await browser.close();
}

getVariations().then(r => console.log(r));