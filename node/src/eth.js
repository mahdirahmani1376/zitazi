const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        headless: "new",
    });
    const page = await browser.newPage();
    await page.goto('https://www.englishhome.com/magnet-pamuklu-cift-kisilik-battaniye-200x220-cm-krem-gri-23902', {waitUntil: 'networkidle2'});

    const jsonData = await page.evaluate(() => {
        const el = document.querySelector('script[type="application/ld+json"]');
        if (!el) return null;
        try {
            return {
                body: JSON.parse(el.textContent)
            };
        } catch {
            return null;
        }
    });

    let test = JSON.stringify(jsonData);

    console.log(JSON.stringify(jsonData));

    await browser.close();
})();
