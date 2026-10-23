const fs = require('fs');
const path = require('path');
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

const userDataDirBase = process.env.CHROME_USER_DATA_DIR;

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

    /**
     * Per-strategy Chrome profile directory. Chrome allows only one process
     * per profile directory (SingletonLock); sharing a single
     * CHROME_USER_DATA_DIR between the Trendyol/Decathlon strategies makes a
     * second launch abort while the first strategy's browser is alive.
     */
    userDataDir() {
        return userDataDirBase ? path.join(userDataDirBase, this.name.toLowerCase()) : undefined;
    }

    /**
     * Strip stale Chrome singleton lock files from this strategy's profile so
     * a hard-crashed browser cannot block the next launch.
     */
    cleanSingletonLocks() {
        const dir = this.userDataDir();
        if (!dir) {
            return;
        }

        try {
            fs.readdirSync(dir)
                .filter((f) => f.startsWith('Singleton'))
                .forEach((f) => fs.rmSync(path.join(dir, f), {force: true}));
        } catch (e) {
        }
    }

    async ensureBrowser() {
        if (scraperShuttingDown) throw new Error('Scraper is shutting down');

        if (this.browser && Date.now() - this.initialTime > BROWSER_RESTART_INTERVAL) {
            await safeClose(() => this.browser.close());
            this.browser = null;
            this.initialTime = Date.now();
        }

        if (!this.browser) {
            this.cleanSingletonLocks();

            const dir = this.userDataDir();
            this.browser = await puppeteer.launch({
                ...puppeteerOptions,
                ...(dir ? {userDataDir: dir} : {}),
            });
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