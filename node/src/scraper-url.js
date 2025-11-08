const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const data = JSON.parse(process.argv[2]); // read from CLI args
    console.log('🔍 Scraping:', data.decathlon_url);

    const browser = await puppeteer.launch({
        headless: true,
        protocolTimeout: 60000,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-zygote',
        ],
    });

    const page = await browser.newPage();

    try {
        await page.goto(data.decathlon_url, {waitUntil: 'networkidle2', timeout: 60000});

        const elHandle = await page.$('script[type="application/ld+json"]');
        const el = await page.evaluate(el => el.textContent, elHandle);
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

        const updateData = {
            'product_id': data.id,
            'variations': variations,
            'success': true
        }

        const postData = {
            'data': updateData,
            'sync': true
        }

        console.log(JSON.stringify(postData))
        // 3. send results back to backend
        const res = await fetch("http://localhost/api/store-decathlon", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify(postData),
        })

        if (!res.ok) {
            console.error('❌ Failed request', res.status, await res.text());
        } else {
            const response = await res.json();
            console.log('✅ Success:', response);
        }
    } catch (err) {
        console.error('❌ Error scraping:', err.message);
    }

    await browser.close();
    console.log('🔚 Done scraping');
    process.exit(0);
})();
