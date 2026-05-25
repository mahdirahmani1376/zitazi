const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

(async () => {
    const data = JSON.parse(process.argv[2]); // read from CLI args
    console.log(JSON.stringify({
        'message': '🔍 Scraping:',
        type: "general",
        url: data.full_url
    }));


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
        await page.goto(data.full_url, {waitUntil: 'networkidle2', timeout: 60000});

        const responseData = await page.evaluate(() => {
            return JSON.parse(document.querySelector("body").innerText);
        });

        let updateData = [];
        updateData.push({
            'product_id': data.id,
            'response': responseData,
            'success': true,
            'sync': true
        })

        console.log(JSON.stringify({
            type: "scrape_success",
            data: updateData,
            message: "success in fetching results",
        }));

        // 3. send results back to backend
        const res = await fetch("http://localhost/api/store-trendyol", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify(updateData),
        })

        if (!res.ok) {
            console.error('❌ Failed request', res.status, await res.text());
        } else {
            const response = await res.json();
            console.log('✅ Success:', response);
        }
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

    await browser.close();
    console.log('🔚 Done scraping');
    process.exit(0);
})();
