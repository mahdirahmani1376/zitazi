const fs = require('fs');
const path = require('path');
const DecathlonStrategy = require('./DecathlonStrategy');

// https://www.decathlon.com.tr/p/futbol-topu-numara/_/R-p-331004?mc=8789908&c=Beyaz_f%C3%BCme+siyah has colors + single size
// https://www.decathlon.com.tr/p/kadin-kapali-sirt-etekli-mayo-una/_/R-p-306417?mc=8547764&c=f%C3%BCme+siyah_Neon+yavrua%C4%9Fz%C4%B1 has color + has sizes
// https://www.decathlon.com.tr/p/mikrofiber-havlu-l-boy-80-x-130-cm-mor/_/R-p-158325?mc=8543246&c=YE%C5%9E%C4%B0L has colors + no size
// https://www.decathlon.com.tr/p/yetiskin-voleybol-ayakkabisi-beyaz-mavi-neon-sari-comfort/_/R-p-334756?mc=8651685&c=Beyaz_gece%20mavisi_Neon%20lime%20ye%C5%9Fili no color + has sizes

// ---------------------------------------------------------------------------
// Output directory. The scraper container is bind-mounted from the host repo,
// but `/app/home/ignores` is a dedicated writable area that maps to the host
// user's workspace. Files created here are chmod'ed to 666 so they stay
// editable from the host (the container process may run as root).
// ---------------------------------------------------------------------------
const DEFAULT_OUT_DIR = '/app/home/ignores';

/**
 * Create the output directory and normalize its permissions. The scraper
 * container runs as root/node, while the host user owns the bind mount;
 * generated files must stay writable on the host.
 */
function prepareOutDir(dir) {
    fs.mkdirSync(dir, {recursive: true, mode: 0o777});
    // Ensure the directory itself is accessible to the host user.
    try {
        fs.chmodSync(dir, 0o777);
    } catch (err) {
        console.error(`Failed to chmod output dir ${dir}:`, err.message);
    }
}

/**
 * Write a file and relax its mode so the host user can edit/delete it.
 * Falls back to a safe default mode if chmod fails.
 */
function writeOutput(file, content) {
    fs.writeFileSync(file, content, {mode: 0o666});
    try {
        fs.chmodSync(file, 0o666);
    } catch (err) {
        console.error(`Failed to chmod ${file}:`, err.message);
    }
}

// ---------------------------------------------------------------------------
// Subclass that runs the real DecathlonStrategy but captures the page so we
// can dump the rendered HTML to a file once the product data has loaded.
// ---------------------------------------------------------------------------
class CaptureHtmlDecathlonStrategy extends DecathlonStrategy {
    constructor() {
        super();
        this.capturedHtml = null;
        this.capturedUrl = null;
    }

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

        // Wrap newPage so we can grab the page instance the strategy creates
        // and snapshot the rendered HTML right after navigation.
        const originalNewPage = browser.newPage.bind(browser);
        browser.newPage = async (...args) => {
            const page = await originalNewPage(...args);

            const originalGoto = page.goto.bind(page);
            page.goto = async (url, opts) => {
                const resp = await originalGoto(url, opts);

                // Capture the rendered DOM immediately after navigation
                // (before the strategy's finally block closes the page).
                try {
                    this.capturedHtml = await page.content();
                    this.capturedUrl = page.url();
                } catch (err) {
                    console.error('Failed to capture HTML:', err.message);
                }

                return resp;
            };

            return page;
        };

        return browser;
    }
}

(async () => {
    const url = process.argv[2];
    const outArg = process.argv[3];

    if (!url) {
        console.error('Usage: node test-decathlon-strategy.js "https://www.decathlon.com.tr/p/..." [output-name]');
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

        // Always save a snapshot of what the browser actually rendered, so
        // blocked/challenge pages are as inspectable as successful ones.
        const html = strategy.capturedHtml;
        if (html) {
            const name = outArg
                ? (outArg.endsWith('.html') ? outArg : `${outArg}.html`)
                : `decathlon-page-${Date.now()}.html`;
            const outPath = path.isAbsolute(name) ? name : path.join(DEFAULT_OUT_DIR, name);
            prepareOutDir(path.dirname(outPath));
            writeOutput(outPath, html);
            console.log(`Saved rendered HTML to ${outPath} (${html.length} bytes)`);
        }

        if (!result.success) {
            console.log('Success:', result.success);
            console.log('Error:', result.error ?? null);
            console.log('Response status:', result.response_status ?? null);
            console.log('Blocked:', result.blocked ?? null);
            console.log('Deleted:', result.deleted ?? null);
            process.exit(1);
        }

        // Also persist the parsed variations JSON next to the HTML snapshot.
        if (result.success && result.response_data) {
            const base = outArg ? outArg.replace(/\.html$/, '') : `variations-${Date.now()}`;
            const jsonOut = path.isAbsolute(base)
                ? `${base}-${Date.now()}.json`
                : path.join(DEFAULT_OUT_DIR, `${base}-${Date.now()}.json`);
            prepareOutDir(path.dirname(jsonOut));
            writeOutput(jsonOut, JSON.stringify(result.response_data, null, 4));
            console.log(`Saved variations JSON to ${jsonOut}`);
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
