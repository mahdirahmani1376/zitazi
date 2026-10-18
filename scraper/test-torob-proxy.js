#!/usr/bin/env node

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

const cliArgs = process.argv.slice(2);
const directFlag = cliArgs.includes('--direct');
const positionalProxy = cliArgs.find((arg) => !arg.startsWith('--'));

const testUrl = process.env.TOROB_TEST_URL || 'https://api.torob.com/v4/base-product/details/?prk=26fa627c-9479-4cf6-a521-f1dddbd814ef';

function envValue(names, defaultValue) {
    for (const name of names) {
        const value = process.env[name];
        if (value && value.length) {
            return value;
        }
    }

    return defaultValue ?? '';
}

function buildProxyUrlFromEnv() {
    const ip = envValue(['PROXY_IP', 'TOROB_PROXY_IP']);
    if (!ip) {
        return '';
    }

    const username = envValue(['PROXY_USER', 'TOROB_PROXY_USER']);
    const password = envValue(['PROXY_PASSWORD', 'TOROB_PROXY_PASSWORD']);
    const port = envValue(['PROXY_PORT', 'TOROB_PROXY_PORT'], '3128');
    const protocol = envValue(['PROXY_PROTOCOL', 'TOROB_PROXY_PROTOCOL'], 'http');
    const auth = username || password ? `${encodeURIComponent(username)}:${encodeURIComponent(password)}@` : '';

    return `${protocol}://${auth}${ip}:${port}`;
}

const proxyUrl = buildProxyUrlFromEnv() || '';

function parseProxy(url) {
    if (!url) return null;

    const parsed = new URL(url);

    return {
        protocol: parsed.protocol,
        host: parsed.hostname,
        port: parsed.port || (parsed.protocol === 'http:' ? 80 : 443),
        auth: parsed.username || parsed.password
            ? {
                username: decodeURIComponent(parsed.username),
                password: decodeURIComponent(parsed.password),
            }
            : null,
    };
}

async function test() {
    const proxy = parseProxy(proxyUrl);

    if (directFlag || !proxy) {
        console.log(`[torob-proxy-test] mode: DIRECT${proxy ? ' (proxy configured but --direct passed)' : ' (no proxy)'}`);
    } else {
        console.log(`[torob-proxy-test] mode: PROXY ${proxy.protocol}//${proxy.auth ? '***:***@' : ''}${proxy.host}:${proxy.port}`);
    }

    console.log(`[torob-proxy-test] url: ${testUrl}`);

    const args = ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'];

    if (proxy && !directFlag) {
        args.push(`--proxy-server=${proxy.protocol}//${proxy.host}:${proxy.port}`);
    }

    const browser = await puppeteer.launch({
        headless: 'new',
        protocolTimeout: 120000,
        args,
    });

    const page = await browser.newPage();

    // Set a realistic Windows User-Agent to override Puppeteer's default
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    // Force Persian language headers to match standard Iranian traffic
    await page.setExtraHTTPHeaders({
        'Accept-Language': 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8'
    });

    if (proxy?.auth && !directFlag) {
        await page.authenticate(proxy.auth);
    }

    const startedAt = Date.now();
    const response = await page.goto(testUrl, {waitUntil: 'domcontentloaded', timeout: 1000 * 60});
    const elapsedMs = Date.now() - startedAt;

    const status = response?.status();
    const headers = response?.headers() ?? {};
    const body = await page.evaluate(() => document.body.innerText);

    let payload = null;
    let parseable = false;
    try {
        payload = JSON.parse(body);
        parseable = true;
    } catch (e) {
        parseable = false;
    }

    const challengePattern = /آیا شما یک|ربات|captcha|challenge/i;
    const blocked = !parseable || [403, 418, 429, 490].includes(status) || challengePattern.test(body);

    console.log(`[torob-proxy-test] status       : ${status}`);
    console.log(`[torob-proxy-test] final url    : ${response?.url()}`);
    console.log(`[torob-proxy-test] content-type : ${headers['content-type']}`);
    console.log(`[torob-proxy-test] elapsed      : ${elapsedMs}ms`);
    console.log(`[torob-proxy-test] body length  : ${body.length}`);
    console.log(`[torob-proxy-test] json parse   : ${parseable ? 'YES' : 'NO'}`);
    console.log(`[torob-proxy-test] blocked      : ${blocked ? 'YES (anti-bot / challenge page)' : 'NO'}`);

    if (blocked) {
        console.log(`[torob-proxy-test] body preview  : ${body.slice(0, 300).replace(/\s+/g, ' ')}`);
    } else {
        const productsInfo = payload?.products_info;
        const sellers = Array.isArray(productsInfo?.result) ? productsInfo.result : null;
        console.log(`[torob-proxy-test] sellers found : ${sellers ? sellers.length : 'n/a'}`);
        if (sellers && sellers.length) {
            console.log(`[torob-proxy-test] lowest price  : ${Math.min(...sellers.map((s) => s.price ?? Infinity))}`);
        }
    }

    await browser.close();

    process.exit(blocked ? 1 : 0);
}

test().catch((err) => {
    console.error(`[torob-proxy-test] ERROR ${err.message || err}`);
    process.exit(2);
});
