const Redis = require('ioredis');

const redis = new Redis({
    host: 'zitazi-redis',
    port: 6379,
});

async function run() {

    console.log('Scrape worker started...');

    while (true) {
        try {
            // BLOCK until a message arrives
            const result = await redis.blpop('laravel_database_scrape_product', 0);

            const message = JSON.parse(result[1]);

            console.log('Received:', result);

            // simulate processing
            const response = {
                result: message,
                status: 'ok',
                message: 'test ok',
            };

            await redis.rpush(
                'laravel_database_scrape_result',
                JSON.stringify(response)
            );

            console.log('Published: scrape_result');
        } catch (e) {
            console.log('error happened', e)
        }
    }
}

run().catch(console.error);