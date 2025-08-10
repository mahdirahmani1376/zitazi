const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('https://www.decathlon.com.tr/p/6-kisilik-kamp-tentesi-arpenaz-fresh/_/R-p-334065?mc=8648418&c=BEYAZ', {waitUntil: 'networkidle2'});

    const data = await page.evaluate(() => {
        return {
            html: document.documentElement.outerHTML,
        };
    });

    console.log(JSON.stringify(data));
    await browser.close();
})();
