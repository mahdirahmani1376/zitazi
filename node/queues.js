const {Queue, Worker} = require('bullmq');
const Redis = require('ioredis');
const puppeteer = require('puppeteer');

const connection = new Redis({host: 'redis'});

const scrapeResultQueue = new Queue(
    'scrape-product-result',
    {connection}
);

let scrapeBrowser;
let bulkBrowser;

async function processJob(job) {

    const page = await browser.newPage();

    try {

        await page.goto(job.data.url, {
            waitUntil: 'networkidle2'
        });

        const result = {
            title: await page.title()
        };

        await scrapeResultQueue.add(
            'scrape-result',
            {
                queue: job.queueName,
                payload: result
            }
        );

        return result;

    } finally {
        await page.close();
    }
}

function createWorker(queueName, concurrency = 1) {

    return new Worker(
        queueName,
        processJob,
        {
            connection,
            concurrency
        }
    );
}

process.on('uncaughtException', async (err) => {

    console.error(err);

    await browser?.close().catch(() => {
    });

    process.exit(1);
});

process.on('unhandledRejection', async (err) => {

    console.error(err);

    await browser?.close().catch(() => {
    });

    process.exit(1);
});

async function run() {

    const scrapeBrowser = await puppeteer.launch({
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

    const bulkBrowser = await puppeteer.launch({
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

    createWorker(
        'scrape-product',
        2
    );

    createWorker(
        'bulk-scrape-product',
        1
    );

    console.log('Workers started');
}

run();