const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());

let browser

async function getVariations() {
    browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        headless: "new",
    });

    const page = await browser.newPage();
    await page.goto('https://www.englishhome.com/cozy-oasis-supersoft-tv-battaniye-120x160-cm-sari-24385', {waitUntil: 'networkidle2'});

    const variationsList = await page.evaluate(() => {
        const el = document.querySelector('div.owl-stage-outer a.detailLink.detailUrl')
        if (!el) return [];
        return Array.from(
            document.querySelectorAll('div.owl-stage-outer a.detailLink.detailUrl')
        ).map(a => ({
            id: a.getAttribute('data-id'),
            href: a.href
        }));
    })

    await page.close();

    for (const variation of variationsList) {
        await crawl(variation);
    }

    await browser.close();
}

async function crawl(variation) {
    const page = await browser.newPage();
    await page.goto(variation.href, {waitUntil: 'networkidle2'});

    const jsonData = await page.evaluate((variation) => {
        const el = document.querySelector('script[type="application/ld+json"]');
        return {
            body: [
                JSON.parse(el.textContent),
                ...variation.id
            ]
        };
    });

    console.log(JSON.stringify(jsonData));
}

getVariations().then(r => console.log(r));