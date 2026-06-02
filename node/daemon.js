const Redis = require('ioredis');
const beginScrape = require('./newScraper'); // FIXED

const redis = new Redis({
    host: 'zitazi-redis',
    port: 6379,
});

// IMPORTANT: match Laravel prefix OR remove prefix in Laravel
const QUEUE_IN = 'laravel_database_scrape_product';
const QUEUE_OUT = 'laravel_database_scrape_result';

async function run() {

    console.log('Scrape worker started...');

    while (true) {

        try {
            const result = await redis.blpop(QUEUE_IN, 0);

            const data = JSON.parse(result[1]);

            const response = await beginScrape(data.product);

            await redis.rpush(
                QUEUE_OUT,
                JSON.stringify(response)
            );

            console.log('Published: scrape_result');

        } catch (e) {
            console.error('Worker error:', e);
        }
    }
}

run();