const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        userDataDir: '/app/chrome-profile',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            // '--proxy-server=http://94.101.184.189:3128',
            // Match the host architecture to prevent token invalidation
            '--user-agent=Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]
    });

    const page = await browser.newPage();

    // Pass credentials if Squid requires authentication
    // await page.authenticate({
    //     username: 'root',
    //     password: '123@r%e$q'
    // });

    const testUrl = 'https://torob.com/p/7effdb3d-4e90-4432-adf5-54960dc91ea1/';
    const response = await page.goto(testUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });

    console.log('[torob] HTTP Status:', response.status());
    const body = await page.evaluate(() => document.body.innerText);

    const isBlocked = body.includes('آیا شما یک ربات هستید؟');
    console.log('[torob] Blocked:', isBlocked ? 'YES (Challenge)' : 'NO (Success)');

    await browser.close();
})();