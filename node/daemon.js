const Redis = require('ioredis');
const beginScrape = require('./scraper'); // FIXED

const redis = new Redis({
    host: 'zitazi-redis',
    port: 6379,
});

// IMPORTANT: match Laravel prefix OR remove prefix in Laravel
const QUEUE_IN = 'laravel_database_scrape_product';
const QUEUE_OUT = 'laravel_database_scrape_result';

async function run() {
    while (true) {
        try {
            console.log('Scrape worker started...');
            await waitForQueue()
        } catch (e) {
            console.error('Worker error:', e);
        }
    }
}

async function waitForQueue() {
    console.log('awaiting user jobs');

    const result = await redis.blpop(QUEUE_IN, 0)

    const data = JSON.parse(result[1]);

    const response = await beginScrape(data.product);
    response.sync = data.sync

    await redis.rpush(
        QUEUE_OUT,
        JSON.stringify(response)
    );

    console.log('Published: scrape_result');
}

run();