#!/usr/bin/env node

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

const cliArgs = process.argv.slice(2);
const directFlag = cliArgs.includes('--direct');

const testUrl = process.env.TOROB_TEST_URL || 'https://torob.com/p/7effdb3d-4e90-4432-adf5-54960dc91ea1/%D8%AD%D8%A7%D9%81%D8%B8%D9%87-%D8%A7%D8%B3-%D8%A7%D8%B3-%D8%AF%DB%8C-%D8%A7%DB%8C%D9%86%D8%AA%D8%B1%D9%86%D8%A7%D9%84-%D9%84%DA%A9%D8%B3%D8%A7%D8%B1-%D9%85%D8%AF%D9%84-ns100-%D8%B8%D8%B1%D9%81%DB%8C%D8%AA-256-%DA%AF%DB%8C%DA%AF%D8%A7%D8%A8%D8%A7%DB%8C%D8%AA/';

// Default to local SSH SOCKS5 tunnel unless overridden
const socksProxy = process.env.SOCKS_PROXY || 'socks5://host.docker.internal:1080';

async function test() {
    if (directFlag) {
        console.log('[torob-proxy-test] mode: DIRECT (--direct passed)');
    } else {
        console.log(`[torob-proxy-test] mode: SOCKS5 ${socksProxy}`);
    }

    console.log(`[torob-proxy-test] url: ${testUrl}`);

    const args = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
    ];

    if (!directFlag) {
        args.push(`--proxy-server=${socksProxy}`);
    }

    const browser = await puppeteer.launch({
        headless: 'new',
        protocolTimeout: 120000,
        args,
    });

    const page = await browser.newPage();

    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    await page.setExtraHTTPHeaders({
        'Accept-Language': 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    });

    const startedAt = Date.now();
    const response = await page.goto(testUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    const elapsedMs = Date.now() - startedAt;

    const status = response?.status();
    const headers = response?.headers() ?? {};
    const body = await page.evaluate(() => document.body.innerText);
    const isApiUrl = testUrl.includes('api.torob.com');

    let payload = null;
    let parseable = false;
    try {
        payload = JSON.parse(body);
        parseable = true;
    } catch (e) {
        parseable = false;
    }

    const challengePattern = /آیا شما یک|ربات|captcha|challenge/i;
    const blocked =
        (isApiUrl && !parseable) ||
        [403, 418, 429, 490].includes(status) ||
        challengePattern.test(body);

    console.log(`[torob-proxy-test] status       : ${status}`);
    console.log(`[torob-proxy-test] final url    : ${response?.url()}`);
    console.log(`[torob-proxy-test] content-type : ${headers['content-type']}`);
    console.log(`[torob-proxy-test] elapsed      : ${elapsedMs}ms`);
    console.log(`[torob-proxy-test] body length  : ${body.length}`);
    console.log(`[torob-proxy-test] json parse   : ${parseable ? 'YES' : 'NO (HTML page)'}`);
    console.log(`[torob-proxy-test] blocked      : ${blocked ? 'YES (anti-bot / challenge page)' : 'NO'}`);
    console.log(`[torob-proxy-test] body preview : ${body.slice(0, 300).replace(/\s+/g, ' ')}`);

    await browser.close();
    process.exit(blocked ? 1 : 0);
}

test().catch((err) => {
    console.error(`[torob-proxy-test] ERROR ${err.message || err}`);
    process.exit(2);
});