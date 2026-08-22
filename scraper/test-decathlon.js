const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        headless: "new",
        args: [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu"
        ]
    });

    const page = await browser.newPage();

    const response = await page.goto(
        "https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-lila-80-130-cm/_/R-p-158325?mc=8732957&c=MAV%C4%B0",
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

    await browser.close();
})();