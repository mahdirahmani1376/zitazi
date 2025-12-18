const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');
const {all} = require("express/lib/application");

puppeteer.use(stealthPlugin());

let browser

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
    let nextUrl = "http://localhost/api/eth-list?page=1";

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
        // const response = await fetch("http://localhost/api/store-decathlon", {
        //     method: "POST",
        //     headers: {"Content-Type": "application/json", "Accept": "application/json"},
        //     body: JSON.stringify(variationData),
        // });
        // console.log('response', await response.json())

        // 4. move to next page
        nextUrl = json.data.next_page_url;
    }

    console.log("✅ Done scraping all pages!",);
    if (browser) await browser.close();
    process.exit(0);
}

async function scrapePageOfUrls(productsData) {
    const browser = await getBrowser();
    const page = await browser.newPage();
    const results = [];

    for (const productData of productsData) {
        // console.log('product_id', productData.id)
        // console.log('url', productData.eth_source)
        try {
            new URL(productData.eth_source);
            await page.goto(productData.eth_source, {waitUntil: 'networkidle2', timeout: 60000});

            const text = await page.content();
            const variationsList = await page.evaluate(() => {
                const el = document.querySelector('div.owl-stage-outer a.detailLink.detailUrl')
                if (!el) return [];
                return Array.from(
                    document.querySelectorAll('div.owl-stage-outer a.detailLink.detailUrl')
                ).map(a => ({
                    id: a.getAttribute('data-id'),
                    href: a.href
                }));
            })

            await page.close();

            for (const variation of variationsList) {
                const result = await crawl(variation);
                results.push(result);
            }

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
            await page.close();
        }
    }

    return results;
}

async function crawl(variation) {
    const page = await browser.newPage();
    await page.goto(variation.href, {waitUntil: 'networkidle2'});

    const jsonData = await page.evaluate((variation) => {
        const el = document.querySelector('script[type="application/ld+json"]');

        const body = JSON.parse(el.textContent)
        body['variation_id'] = variation.id;

        return {
            body: body
        };

    }, variation);

    // console.log(JSON.stringify(jsonData));
    await page.close();
    return jsonData;
}

scrapeAll().catch(err => console.error("Scraping error:", err));