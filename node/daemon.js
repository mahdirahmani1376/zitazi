const Redis = require('ioredis');
const beginScrape = require('./newScraper'); // FIXED

const redis = new Redis({
    host: 'zitazi-redis',
    port: 6379,
});

// IMPORTANT: match Laravel prefix OR remove prefix in Laravel
const QUEUE_IN_USER = 'laravel_database_scrape_bulk_product';
const QUEUE_IN_BULK = 'laravel_database_scrape_product';
const QUEUE_OUT = 'laravel_database_scrape_result';

async function run() {

    console.log('Scrape worker started...');
    try {
        await waitForQueue()
    } catch (e) {
        await waitForQueue()
        console.error('Worker error:', e);
    }
    console.log('Scrape worker finished...');
}

async function waitForQueue() {
    console.log('awaiting user jobs');

    const result = await redis.blpop(QUEUE_IN_USER, 0)

    const data = JSON.parse(result[1]);

    const response = await beginScrape(data.product);
    response.sync = true

    await redis.rpush(
        QUEUE_OUT,
        JSON.stringify(response)
    );

    console.log('Published: scrape_result');
}

run();