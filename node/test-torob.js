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
    const response = await page.goto('https://api.torob.com/v4/base-product/details/?prk=aef9eeca-6855-42d5-b253-2495062505d8', {waitUntil: 'domcontentloaded'});

    console.log('status', response.status())
    console.log('data', await page.content())

    await page.close();

    await browser.close();
}

getVariations().then(r => console.log(r));