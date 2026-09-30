const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ],
    });

    const page = await browser.newPage();

    await page.setRequestInterception(true);

    page.on('request', (request) => {
        // We only need the initial HTML document.
        if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
            request.continue();
        } else {
            request.abort();
        }
    });

    const response = await page.goto(
        'https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-lila-80-130-cm/_/R-p-158325?mc=8732957&c=MAV%C4%B0',
        {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        }
    );

    console.log('status:', response.status());

    // #__dkt
    const dkt = await page.$eval(
        '#__dkt',
        el => el.textContent
    );

    // application/ld+json
    const jsonLd = await page.$eval(
        'script[type="application/ld+json"]',
        el => el.textContent
    );

    console.log('DKT length:', dkt.length);
    console.log('JSON-LD length:', jsonLd.length);

    console.log('DKT:', dkt);
    console.log('JSON-LD:', JSON.parse(jsonLd));

    await browser.close();
})();