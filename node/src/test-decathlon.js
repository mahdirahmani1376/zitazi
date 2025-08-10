const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('https://www.decathlon.com.tr/p/yetiskin-futbol-formasi-beyaz-siyah-viralto-damier/_/R-p-333380?mc=8844122&c=SİYAH_BEYAZ', {waitUntil: 'networkidle2'});

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

    console.log(JSON.stringify(jsonData));

    await browser.close();
})();
