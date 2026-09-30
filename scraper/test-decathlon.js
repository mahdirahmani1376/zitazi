const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
let totalBytes = 0;
let requests = 0;
puppeteer.use(StealthPlugin());

(async () => {


    const browser = await puppeteer.launch({
        headless: true,
        args: [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu"
        ]
    });

    const page = await browser.newPage();

    await page.setRequestInterception(true);

    page.on('request', req => {

        const type = req.resourceType();

        if (
            type === 'image' ||
            type === 'font' ||
            type === 'media'
        ) {
            req.abort();
        } else {
            req.continue();
        }

    });

    page.on('response', async (response) => {
        try {
            const request = response.request();

            // فقط HTTP/HTTPS
            if (!['http:', 'https:'].includes(new URL(response.url()).protocol)) {
                return;
            }

            const buffer = await response.buffer();
            const size = buffer.length;

            totalBytes += size;
            requests++;

            console.log(
                response.status(),
                size,
                response.url()
            );
        } catch (error) {
            // بعضی responseها ممکن است body نداشته باشند
        }
    });

    const response = await page.goto(
        "https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-lila-80-130-cm/_/R-p-158325?mc=8732957&c=MAV%C4%B0",
        // "https://www.decathlon.com.tr/p/olta-takimi-seti-balikcilik-3000-240-cm-ufish-2-40/_/R-p-350584?mc=8843245",
        {waitUntil: "domcontentloaded"}
    );

    console.log('status', response.status())

    const jsonData = await page.evaluate(() => {
        const el = document.querySelector('script[type="application/ld+json"]');
        if (!el) return null;

        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    });

    console.log(jsonData);

    console.log('-----------------------');
    console.log(`Requests: ${requests}`);
    console.log(`Total: ${(totalBytes / 1024).toFixed(2)} KB`);
    console.log(`Total: ${(totalBytes / 1024 / 1024).toFixed(2)} MB`);

    await browser.close();
})();