const Redis = require('ioredis');

const redis = new Redis({
    host: 'redis',
    port: 6379,
});

async function run() {

    console.log('Scrape worker started...');

    while (true) {

        // BLOCK until a message arrives
        const result = await redis.blpop('scrape-product', 0);

        const message = JSON.parse(result[1]);

        console.log('Received:', message);

        // simulate processing
        const response = {
            status: 'ok',
            message: 'test ok',
        };

        await redis.rpush(
            'scrape-result',
            JSON.stringify(response)
        );

        console.log('Published: scrape-result');
    }
}

run().catch(console.error);