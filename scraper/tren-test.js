const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser
let totalBytes = 0;
let requests = 0;
async function getVariations() {
    browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        headless: "new",
    });

    const page = await browser.newPage();

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

    const response = await page.goto('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content?contentId=866304539&merchantId=724849&countryCode=TR', {waitUntil: 'networkidle2'});
    // const response = await page.goto('https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content?contentId=1021811977&countryCode=TR', {waitUntil: 'domcontentloaded'});
    // await page.goto('https://www.trendyol.com/english-home/ethnic-bukle-kilim-80x150-cm-natural-p-966592692?boutiqueId=61&merchantId=107113&v=120-x-180', {waitUntil: 'networkidle2'});

    console.log('status', response.status())

    console.log('data', await page.content())

    await page.close();
    console.log('-----------------------');
    console.log(`Requests: ${requests}`);
    console.log(`Total: ${(totalBytes / 1024).toFixed(2)} KB`);
    console.log(`Total: ${(totalBytes / 1024 / 1024).toFixed(2)} MB`);
    await browser.close();
}

async function crawl(variation) {
    const page = await browser.newPage();
    await page.goto(variation.href, {waitUntil: 'networkidle2'});

    const jsonData = await page.evaluate((variation) => {
        const el = document.querySelector('script[type="application/ld+json"]');
        return {
            body: [
                JSON.parse(el.textContent),
                ...variation.id
            ]
        };
    }, variation);

    console.log(JSON.stringify(jsonData));
}

getVariations().then(r => console.log(r));