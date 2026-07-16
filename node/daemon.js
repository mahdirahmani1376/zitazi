const Redis = require('ioredis');
const beginScrape = require('./scraper');


function createRedis() {
    return new Redis({
        host: 'zitazi-redis',
        port: 6379,
    });
}

const TR_QUEUE_IN = 'laravel_database_trendyol_scrape_product';
const DE_QUEUE_IN = 'laravel_database_decathlon_scrape_product';

const QUEUE_OUT = 'laravel_database_scrape_result';

async function runWorker(name, queueIn) {
    const redis = createRedis();
    console.log(`${name} worker started...`);

    while (true) {
        try {

            const result = await redis.blpop(queueIn, 0)

            const data = JSON.parse(result[1]);

            const response = await beginScrape(name, data.product);
            response.bulk = data.bulk ?? false

            await redis.rpush(
                QUEUE_OUT,
                JSON.stringify(response)
            );


            console.log(JSON.stringify({
                type: "scrape-response",
                level: "info",
                source: name,
                product_id: data.product.id,
                response: response
            }));

        } catch (e) {
            console.error(`${name} worker error`, e);
        }
    }
}

Promise.all([
    runWorker("Trendyol", TR_QUEUE_IN),
    runWorker("Decathlon", DE_QUEUE_IN)
]).catch(console.error);
