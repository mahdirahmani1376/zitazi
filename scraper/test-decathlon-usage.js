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

    // -----------------------------
    // Network traffic measurement
    // -----------------------------

    const client = await page.target().createCDPSession();

    await client.send('Network.enable');

    let totalBytes = 0;
    let responseCount = 0;

    client.on('Network.responseReceived', ({response}) => {
        const url = response.url;

        if (
            url.startsWith('http://') ||
            url.startsWith('https://')
        ) {
            responseCount++;

            console.log(
                `[RESPONSE] ${response.status} ${url}`
            );
        }
    });

    client.on('Network.loadingFinished', ({encodedDataLength}) => {
        totalBytes += encodedDataLength;
    });

    // -----------------------------
    // Only allow the main HTML
    // -----------------------------

    await page.setRequestInterception(true);

    page.on('request', request => {
        if (
            request.isNavigationRequest() &&
            request.frame() === page.mainFrame()
        ) {
            request.continue();
        } else {
            request.abort();
        }
    });

    // -----------------------------
    // Navigate
    // -----------------------------

    const url =
        'https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-lila-80-130-cm/_/R-p-158325?mc=8732957&c=MAV%C4%B0';

    const response = await page.goto(url, {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
    });

    console.log('HTTP status:', response.status());

    // -----------------------------
    // Extract #__dkt
    // -----------------------------

    const dkt = await page.$eval(
        '#__dkt',
        el => el.textContent
    );

    // -----------------------------
    // Extract JSON-LD
    // -----------------------------

    const jsonLd = await page.$eval(
        'script[type="application/ld+json"]',
        el => el.textContent
    );

    const jsonData = JSON.parse(jsonLd);

    // -----------------------------
    // Give CDP events time to finish
    // -----------------------------

    await new Promise(resolve => setTimeout(resolve, 500));

    // -----------------------------
    // Results
    // -----------------------------

    console.log('');
    console.log('==============================');
    console.log('SCRAPE RESULTS');
    console.log('==============================');

    console.log('Response count:', responseCount);

    console.log(
        'Transferred:',
        `${(totalBytes / 1024).toFixed(2)} KB`
    );

    console.log(
        'Transferred:',
        `${(totalBytes / 1024 / 1024).toFixed(3)} MB`
    );

    console.log(
        '#__dkt size:',
        `${(Buffer.byteLength(dkt, 'utf8') / 1024).toFixed(2)} KB`
    );

    console.log(
        'JSON-LD size:',
        `${(Buffer.byteLength(jsonLd, 'utf8') / 1024).toFixed(2)} KB`
    );

    console.log('==============================');

    await browser.close();
})();