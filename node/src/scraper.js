const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser;

// Graceful shutdown handlers
process.on('SIGINT', async () => {
    console.log('🛑 Shutting down gracefully...');
    if (browser) await browser.close();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('🛑 Terminating...');
    if (browser) await browser.close();
    process.exit(0);
});

// Utility sleep function
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

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
    const failedUrls = [];

    while (nextUrl) {
        console.log("📄 Fetching list:", nextUrl);

        // 1. Get one page of URLs
        const res = await fetch(nextUrl);
        const json = await res.json();
        const productsData = json.data.data;

        if (productsData.length === 0) {
            console.log("⚠️  No URLs found on this page.");
            break;
        }

        // 2. Scrape those URLs
        const {results, failed} = await scrapePageOfUrls(productsData);

        if (failed.length) {
            console.log(`❌ ${failed.length} failed on this page, will retry later.`);
            failedUrls.push(...failed);
        }

        // 3. Send results back to backend
        const postData = {data: results};
        console.log('✅ post data', JSON.stringify(postData));

        try {
            const response = await fetch("http://localhost/api/store-decathlon", {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json"},
                body: JSON.stringify(postData),
            });
            if (!response.ok) {
                console.error("⚠️  Failed to POST scraped data:", await response.text());
            }
        } catch (err) {
            console.error("⚠️  Network error while posting scraped data:", err.message);
        }

        // 4. Move to next page
        nextUrl = json.data.next_page_url;
    }

    // 5. Wait 2 hours, then retry failed URLs once
    if (failedUrls.length > 0) {
        console.log(`⏳ ${failedUrls.length} URLs failed. Waiting 2 hours before retry...`);

        for (let remaining = 2 * 60; remaining > 0; remaining -= 10) {
            console.log(`🕒 Retry starts in ~${remaining} minutes...`);
            await sleep(10 * 60 * 1000); // log every 10 minutes
        }

        console.log("🔁 Starting retry for failed URLs...");


        const {results} = await scrapePageOfUrls(failedUrls);

        const postData = {data: results};
        try {
            await fetch("http://localhost/api/store-decathlon", {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json"},
                body: JSON.stringify(postData),
            });
            console.log("✅ Retried results successfully sent.");
        } catch (err) {
            console.error("❗ Failed to send retried data:", err.message);
        }
    }

    console.log("🎉 Done scraping all pages!");
    if (browser) await browser.close();
    process.exit(0);
}

async function scrapePageOfUrls(productsData) {
    const browser = await getBrowser();
    const page = await browser.newPage();
    const results = [];
    const failedUrls = [];

    for (const productData of productsData) {
        console.log('🔗 Scraping:', productData.decathlon_url);
        try {
            new URL(productData.decathlon_url); // validate URL

            await page.goto(productData.decathlon_url, {waitUntil: 'networkidle2', timeout: 60000});

            // Extract LD+JSON data
            const elHandle = await page.$('script[type="application/ld+json"]');
            if (!elHandle) throw new Error("No JSON-LD found on page");
            const el = await page.evaluate(el => el.textContent, elHandle);
            const targetData = JSON.parse(el);

            const variations = [];
            const productId = targetData.productID;

            targetData.offers.forEach(baseOffer => {
                baseOffer.forEach(offer => {
                    const stock = offer.availability === 'https://schema.org/InStock' ? 88 : 0;
                    variations.push({
                        decathlon_product_id: productId,
                        sku: offer.sku ?? null,
                        price: offer.price ?? null,
                        url: offer.url ?? null,
                        stock,
                    });
                });
            });

            // Extract size info from embedded script
            const scriptHandle = await page.$('#__dkt');
            const scriptHandleData = scriptHandle
                ? await page.evaluate(script => script.textContent, scriptHandle)
                : '';

            variations.forEach(variation => {
                const pattern = new RegExp(
                    `"skuId"\\s*:\\s*"` + variation.sku.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + `"` +
                    `\\s*,\\s*"size"\\s*:\\s*"([^"]+)"`,
                    "g"
                );
                const match = pattern.exec(scriptHandleData);
                if (match) variation.size = match[1];
            });

            results.push({
                product_id: productData.id,
                variations,
                success: true
            });

        } catch (err) {
            const safeError = {
                name: err.name,
                message: err.message,
                code: err.code ?? null,
                stack: err.stack?.split('\n').slice(0, 3).join(' ') ?? null,
            };

            results.push({
                product_id: productData.id,
                success: false,
                error: safeError
            });

            failedUrls.push(productData);
            console.log('❌ Error scraping', productData.decathlon_url, safeError.message);
        }
    }

    await page.close();
    return {results, failed: failedUrls};
}

scrapeAll().catch(err => console.error("Scraping error:", err));
