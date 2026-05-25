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
    let nextUrl = "http://localhost/api/trendyol-list?page=1";

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

        try {
            // 3. send results back to backend
            const response = await fetch("http://localhost/api/store-trendyol", {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json"},
                body: JSON.stringify(variationData),
            });
        } catch (err) {
            const safeError = {
                name: err.name,
                message: err.message,
                code: err.code ?? null,
                stack: err.stack?.split('\n').slice(0, 3).join(' ') ?? null, // shorten
            };
            console.log(JSON.stringify({
                type: "scrape_error",
                message: "error in fetching results",
                error: safeError
            }));
        }


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
        try {
            new URL(productData.full_url);
            await page.goto(productData.full_url, {waitUntil: 'networkidle2', timeout: 60000});

            // const responseData = await page.waitForResponse(
            //     response => response.json()
            // )
            const responseData = await page.evaluate(() => {
                return JSON.parse(document.querySelector("body").innerText);
            });

            results.push({
                'product_id': productData.id,
                'response': responseData,
                'success': true
            });

            console.log(JSON.stringify({
                type: "scrape_success",
                product_id: productData.id,
                message: "success in fetching results",
                response: responseData,
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

