const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser;
process.on('SIGINT', async () => {
    console.log('Shutting down gracefully...');
    if (browser) await browser.close();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('Terminating...');
    if (browser) await browser.close();
    process.exit(0);
});
async function getBrowser() {
    if (!browser) {
        browser = await puppeteer.launch({
            headless: true,
            protocolTimeout: 60000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-zygote',
            ]
        });
    }
    return browser;
}

async function scrapeAll() {
    let nextUrl = "http://localhost/api/decathlon-list-retry?page=1";

    while (nextUrl) {
        console.log("Fetching list:", nextUrl);

        // 1. get 1 page of URLs
        const res = await fetch(nextUrl);
        const json = await res.json();


        const productsData = json.data.data;
        if (productsData.length === 0) {
            console.log("No URLs found on this page.");
            break;
        }

        // 2. scrape those urls
        const variationData = await scrapePageOfUrls(productsData);

        console.log('variation data', JSON.stringify(variationData))
        // 3. send results back to backend
        const response = await fetch("http://localhost/api/store-decathlon", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify(variationData),
        });
        console.log('response', await response.json())

        // 4. move to next page
        nextUrl = json.data.next_page_url;
    }

    console.log("✅ Done scraping all pages!");
    if (browser) await browser.close();
    process.exit(0);
}

async function scrapePageOfUrls(productsData) {
    const browser = await getBrowser();
    const page = await browser.newPage();
    const results = [];

    for (const productData of productsData) {
        console.log('product_id', productData.id)
        console.log('url', productData.decathlon_url)
        try {
            new URL(productData.decathlon_url);
            await page.goto(productData.decathlon_url, {waitUntil: 'networkidle2', timeout: 60000});
            const elHandle = await page.$('script[type="application/ld+json"]');
            const el = await page.evaluate(el => el.textContent, elHandle)
            const targetData = JSON.parse(el);

            const variations = [];
            const productId = targetData.productID;

            targetData.offers.forEach((baseOffer, baseIndex) => {
                baseOffer.forEach((offer, index) => {
                    const stock = offer.availability === 'https://schema.org/InStock' ? 88 : 0;
                    variations.push({
                        decathlon_product_id: productId,
                        sku: offer.sku ?? null,
                        price: offer.price ?? null,
                        url: offer.url ?? null,
                        stock,
                    });
                });
            })


            const scriptHandle = await page.$('#__dkt');
            const scriptHandleData = await page.evaluate(script => script.textContent, scriptHandle)


            variations.forEach(variation => {
                let pattern = new RegExp(
                    `"skuId"\\s*:\\s*"` + variation.sku.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + `"` +
                    `\\s*,\\s*"size"\\s*:\\s*"([^"]+)"`,
                    "g"
                );
                let match = pattern.exec(scriptHandleData);
                if (match) {
                    // console.log("Found size:", match[1]); // "XL"
                    variation.size = match[1]
                }
            });

            results.push({
                'product_id': productData.id,
                'variations': variations,
                'success': true
            });
        } catch (err) {
            const safeError = {
                name: err.name,
                message: err.message,
                code: err.code ?? null,
                stack: err.stack?.split('\n').slice(0, 3).join(' ') ?? null, // shorten
            };

            results.push({
                'product_id': productData.id,
                'success': false,
                'error': safeError
            });
            console.log('error in fetching results', err, 'product_id', productData.id);
        }
    }

    await page.close();
    return results;
}

scrapeAll().catch(err => console.error("Scraping error:", err));

