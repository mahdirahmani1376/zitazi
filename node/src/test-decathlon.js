const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-lila-80-130-cm/_/R-p-158325?mc=8732957&c=MAV%C4%B0', {waitUntil: 'networkidle2'});

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
