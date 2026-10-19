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
    const startedAt = Date.now();

    await new Promise((r) => setTimeout(r, 8000));

    const response = await page.goto('https://api.torob.com/v4/base-product/details/?prk=aef9eeca-6855-42d5-b253-2495062505d8', {waitUntil: 'networkidle2'});

    await new Promise((r) => setTimeout(r, 8000));

    // If status is 490, wait 8 seconds to see if the JS browser check auto-solves and reloads
    if (response?.status() === 490) {
        console.log('[torob-proxy-test] 490 challenge detected, waiting 8s for JS verification...');
    }

    const elapsedMs = Date.now() - startedAt;
    const body = await page.evaluate(() => document.body.innerText);
    const html = await page.content();

    console.log('status', response.status())
    console.log('data', await page.content())

    await page.close();

    await browser.close();
}

getVariations().then(r => console.log(r));