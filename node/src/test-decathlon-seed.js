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
        const targetData = JSON.parse(el.textContent.trim());
        const variations = [];
        const productId = targetData.productID;

        targetData.offers[0].forEach((offer, index) => {
            const stock = offer.availability === 'https://schema.org/InStock' ? 88 : 0;
            variations.push({
                product_id: productId,
                sku: offer.sku ?? null,
                price: offer.price ?? null,
                url: offer.url ?? null,
                stock,
                index
            });
        });

        variations.forEach(variation => {
            const elId = `#sku-${variation.index}`;
            const el = document.querySelector(elId);
            variation.size = el ? el.getAttribute('aria-label') : null;
        });

        return {body: variations};
    });


    console.log(JSON.stringify(jsonData));

    await browser.close();
})();
