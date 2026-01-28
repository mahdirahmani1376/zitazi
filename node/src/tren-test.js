const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser

async function getVariations() {
    browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        headless: "new",
    });

    const page = await browser.newPage();
    await page.goto('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content?contentId=872167503&merchantId=692043', {waitUntil: 'networkidle2'});

    console.log('data', await page.content())

    await page.close();

    await browser.close();
}

async function crawl(variation) {
    const page = await browser.newPage();
    await page.goto(variation.href, {waitUntil: 'networkidle2'});

    const jsonData = await page.evaluate((variation) => {
        const el = document.querySelector('script[type="application/ld+json"]');
        return {
            body: [
                JSON.parse(el.textContent),
                ...variation.id
            ]
        };
    }, variation);

    console.log(JSON.stringify(jsonData));
}

getVariations().then(r => console.log(r));