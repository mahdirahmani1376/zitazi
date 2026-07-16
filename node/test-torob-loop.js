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
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-dev-shm-usage",
                "--disable-gpu",
                "--no-zygote",
            ]
        });
    }

    return browser;
}

async function test() {
    const browser = await getBrowser();
    const page = await browser.newPage()

    for (let i = 1; i <= 10; i++) {
        const response = await page.goto(
            "https://api.torob.com/v4/base-product/details/?prk=aef9eeca-6855-42d5-b253-2495062505d8",
            {
                waitUntil: "domcontentloaded",
                timeout: 60000
            }
        );

        console.log('iteration', i);
        console.log('status', response.status());
        console.log('headers', response.headers());
        // console.log('text',await response.text());

    }

    await browser.close();
}

test()