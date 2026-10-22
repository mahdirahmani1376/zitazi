const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

let scraperShuttingDown = false;

function setShuttingDown() {
    scraperShuttingDown = true;
}

function isShuttingDown() {
    return scraperShuttingDown;
}

const userDataDir = process.env.CHROME_USER_DATA_DIR;

// Headful mode (under Xvfb) passes Cloudflare's "Just a moment" challenge
// that blocks headless Chromium from Decathlon. Enable it for the scraper
// container via SCRAPER_HEADFUL=1 + a running DISPLAY (see docker-compose).
const headful = process.env.SCRAPER_HEADFUL === '1';

const puppeteerOptions = {
    headless: headful ? false : 'new',
    protocolTimeout: 120000,
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        ...(headful ? ['--window-size=1280,1024'] : []),
    ],
    ...(userDataDir ? {userDataDir} : {}),
};

const BROWSER_RESTART_INTERVAL = 30 * 60 * 1000;

async function safeClose(closeFn) {
    try {
        await Promise.race([closeFn(), new Promise((resolve) => setTimeout(resolve, 10000))]);
    } catch (e) {
    }
}

function delay(time) {
    return new Promise((resolve) => setTimeout(resolve, time));
}

class BaseStrategy {
    constructor(name) {
        this.name = name;
        this.browser = null;
        this.initialTime = Date.now();
    }

    async ensureBrowser() {
        if (scraperShuttingDown) throw new Error('Scraper is shutting down');

        if (this.browser && Date.now() - this.initialTime > BROWSER_RESTART_INTERVAL) {
            await safeClose(() => this.browser.close());
            this.browser = null;
            this.initialTime = Date.now();
        }

        if (!this.browser) {
            this.browser = await puppeteer.launch(puppeteerOptions);
            this.initialTime = Date.now();
        }

        return this.browser;
    }

    async closeBrowser() {
        if (this.browser) {
            await safeClose(() => this.browser.close());
            this.browser = null;
        }
    }

    async scrape(_data) {
        return {success: false, error: 'not implemented'};
    }
}

module.exports = {BaseStrategy, delay, safeClose, setShuttingDown, isShuttingDown};