#!/usr/bin/env node

const puppeteer = require('puppeteer');

const cliArgs = process.argv.slice(2);
const directFlag = cliArgs.includes('--direct');
const positionalProxy = cliArgs.find((arg) => !arg.startsWith('--'));

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
    const ip = envValue(['PROXY_IP']);
    if (!ip) {
        return '';
    }

    const username = envValue(['PROXY_USER']);
    const password = envValue(['PROXY_PASSWORD']);
    const port = envValue(['PROXY_PORT'], '3128');
    const protocol = envValue(['PROXY_PROTOCOL'], 'http');
    const auth = username || password ? `${encodeURIComponent(username)}:${encodeURIComponent(password)}@` : '';

    return `${protocol}://${auth}${ip}:${port}`;
}

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

const proxyUrl = buildProxyUrlFromEnv() || '';
const checkUrl = process.env.CHECK_URL || 'https://api.ipify.org';

(async () => {
    const proxy = parseProxy(proxyUrl);

    if (directFlag || !proxy) {
        console.log(`[proxy-test] mode: DIRECT${proxy ? ' (proxy configured but --direct passed)' : ' (no proxy)'}`);
    } else {
        console.log(`[proxy-test] mode: PROXY ${proxy.protocol}//${proxy.auth ? '***:***@' : ''}${proxy.host}:${proxy.port}`);
    }

    console.log(`[proxy-test] url: ${checkUrl}`);

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

    if (proxy?.auth && !directFlag) {
        await page.authenticate(proxy.auth);
    }

    await page.goto(checkUrl, {waitUntil: 'domcontentloaded', timeout: 1000 * 60});

    const content = await page.content();
    console.log('Current IP seen by the website:', content);

    await browser.close();
})();
