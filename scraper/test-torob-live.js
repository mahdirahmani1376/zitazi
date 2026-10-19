const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: false, // Visible window on the client's desktop
        userDataDir: './client_browser_data', // Remembers the solved checkbox across days
        defaultViewport: null,
    });

    const page = await browser.newPage();
    await page.goto('https://torob.com/p/7effdb3d-4e90-4432-adf5-54960dc91ea1/', {
        waitUntil: 'domcontentloaded'
    });

    // Check if the WAF challenge page appeared
    const isChallenged = await page.evaluate(() => {
        return document.body.innerText.includes('آیا شما یک ربات هستید؟');
    });

    if (isChallenged) {
        console.log('Please click the "من ربات نیستم" checkbox in the browser window...');
        // Wait until the user clicks the checkbox and the real product page loads
        await page.waitForFunction(
            () => !document.body.innerText.includes('آیا شما یک ربات هستید؟'),
            { timeout: 0 } // Waits indefinitely for the human to click it
        );
        console.log('Verification complete! Continuing data collection...');
    }

    // Proceed with reading the page or calling internal APIs now that cookies are set
    const content = await page.evaluate(() => document.body.innerText);
    console.log('Page loaded successfully, length:', content.length);

    // Keep reusing this same browser/page for the rest of the daily URLs
})();