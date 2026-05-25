const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser;
process.on('SIGINT', async () => {
    console.log(JSON.stringify({
        'message': 'Shutting down gracefully...',
        type: "general",
    }));
    if (browser) await browser.close();
    process.exit(0);
});
process.on('SIGTERM', async () => {
    console.log(JSON.stringify({
        'message': 'Terminating',
        'type': "general",
    }));
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
    let nextUrl = "http://localhost/api/decathlon-list?page=1";

    while (nextUrl) {
        console.log(JSON.stringify({
            'message': 'Fetching list',
            'url': nextUrl,
            'type': "general",
        }));

        // 1. get 1 page of URLs
        const res = await fetch(nextUrl);
        const json = await res.json();


        const productsData = json.data.data;
        if (productsData.length === 0) {
            console.log(JSON.stringify({
                'message': 'No URLs found on this page.',
                'url': nextUrl,
                'type': "general",
            }));
            break;
        }

        // 2. scrape those urls
        const variationData = await scrapePageOfUrls(productsData);

        // 3. send results back to backend
        await fetch("http://localhost/api/store-decathlon", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify(variationData),
        });

        // 4. move to next page
        nextUrl = json.data.next_page_url;
    }

    console.log(JSON.stringify({
        'message': '✅ Done scraping all pages!',
        'type': "general",
    }));

    if (browser) await browser.close();
    process.exit(0);
}

async function scrapePageOfUrls(productsData) {
    const browser = await getBrowser();
    const page = await browser.newPage();
    const results = [];

    for (const productData of productsData) {
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

            console.log(JSON.stringify({
                type: "scrape_success",
                product_id: productData.id,
                message: "success in fetching results",
                variations_count: variations.length
            }));

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

            console.log(JSON.stringify({
                type: "scrape_error",
                product_id: productData.id,
                message: "error in fetching results",
                error: safeError
            }));
        }
    }

    await page.close();
    return results;
}

scrapeAll().catch(err => console.error("Scraping error:", err));

