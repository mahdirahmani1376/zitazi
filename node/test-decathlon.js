const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('https://www.decathlon.com.tr/p/siyah-kisa-kollu-futbol-formasi-viralto-pxl/_/R-p-333380?mc=8844122&c=S%25C4%25B0YAH_BEYAZ', {waitUntil: 'networkidle2'});

    const data = await page.evaluate(() => {
        return {
            html: document.documentElement.outerHTML,
        };
    });

    console.log(JSON.stringify(data));
    await browser.close();
})();
