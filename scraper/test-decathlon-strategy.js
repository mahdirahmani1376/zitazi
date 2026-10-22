const fs = require('fs');
const path = require('path');
const DecathlonStrategy = require('./DecathlonStrategy');

const HTML_FILE = process.argv[3] || 'decathlon-page.html';

// https://www.decathlon.com.tr/p/futbol-topu-numara/_/R-p-331004?mc=8789908&c=Beyaz_f%C3%BCme+siyah has colors + single size
// https://www.decathlon.com.tr/p/kadin-kapali-sirt-etekli-mayo-una/_/R-p-306417?mc=8547764&c=f%C3%BCme+siyah_Neon+yavrua%C4%9Fz%C4%B1 has color + has sizes
// https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-80-x-130-cm-mor/_/R-p-158325?mc=8543246&c=YE%C5%9E%C4%B0L has colors + no size
// https://www.decathlon.com.tr/p/yetiskin-voleybol-ayakkabisi-beyaz-mavi-neon-sari-comfort/_/R-p-334756?mc=8651685&c=Beyaz_gece%20mavisi_Neon%20lime%20ye%C5%9Fili no color + has sizes

// ---------------------------------------------------------------------------
// Subclass that runs the real DecathlonStrategy but captures the page so we
// can dump the rendered HTML to a file once the product data has loaded.
// ---------------------------------------------------------------------------
class CaptureHtmlDecathlonStrategy extends DecathlonStrategy {
    async ensureBrowser() {
        // The copied host profile may carry stale Chrome singleton lock files
        // ("The profile appears to be in use by another Chromium process").
        // Strip them so Chromium can launch with the profile.
        const {readdirSync, rmSync} = require('fs');
        const userDataDir = process.env.CHROME_USER_DATA_DIR;
        if (userDataDir) {
            try {
                readdirSync(userDataDir)
                    .filter((f) => f.startsWith('Singleton'))
                    .forEach((f) => rmSync(`${userDataDir}/${f}`, {force: true}));
            } catch (err) {
                console.error('Failed to clean Singleton lock files:', err.message);
            }
        }

        const browser = await super.ensureBrowser();

        // Wrap newPage so we can grab the page instance the strategy creates.
        const originalNewPage = browser.newPage.bind(browser);
        browser.newPage = async (...args) => {
            const page = await originalNewPage(...args);

            // Wrap goto so that right after the page loads we save the HTML.
            const originalGoto = page.goto.bind(page);
            page.goto = async (url, opts) => {
                return await originalGoto(url, opts);
            };

            return page;
        };

        return browser;
    }
}

(async () => {
    const url = process.argv[2];

    if (!url) {
        console.error('Usage: node test-decathlon-strategy.js "https://www.decathlon.com.tr/p/..." [output.html]');
        process.exit(1);
    }

    const strategy = new CaptureHtmlDecathlonStrategy();

    try {
        const result = await strategy.scrape({
            id: 'test',
            decathlon_url: url,
        });

        console.log('==============================');
        console.log('SCRAPE RESULT');
        console.log('==============================');

        if (!result.success) {
            console.log('Success:', result.success);
            console.log('Error:', result.error ?? null);
            console.log('Response status:', result.response_status ?? null);
            console.log('Blocked:', result.blocked ?? null);
            console.log('Deleted:', result.deleted ?? null);
            process.exit(1);
        }

        console.log(`Variations count: ${result.response_data.length}`);
        console.log('');
        console.log('Final variations array:');
        console.log(JSON.stringify(result.response_data, null, 4));
    } catch (err) {
        console.error('Unexpected error:', err);
        process.exit(1);
    } finally {
        await strategy.closeBrowser();
    }
})();
