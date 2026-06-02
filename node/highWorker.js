const {Worker} = require('bullmq');
const puppeteer = require('puppeteer');
const {connection} = require('./queues');

let browser;

process.on('uncaughtException', async (err) => {
    console.error('Uncaught Exception:', err);

    if (browser) {
        await browser.close().catch(() => {
        });
    }

    process.exit(1);
});

process.on('unhandledRejection', async (err) => {
    console.error('Unhandled Rejection:', err);

    if (browser) {
        await browser.close().catch(() => {
        });
    }

    process.exit(1);
});

async function initBrowser() {
    browser = await puppeteer.launch({
        headless: true,
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH,
        protocolTimeout: 60000,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-zygote',
        ]
    });
}

(async () => {
    await initBrowser();

    new Worker(
        'scrape-product',
        async (job) => {
            const page = await browser.newPage();

            try {
                await page.goto(job.data.url, {
                    waitUntil: 'networkidle2'
                });

                return {
                    title: await page.title()
                };

            } finally {
                await page.close();
            }
        },
        {
            connection,
            concurrency: 2
        }
    );
})();