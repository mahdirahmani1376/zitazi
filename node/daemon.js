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
    console.info(`${name} worker started...`);

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

            let level = 'info';
            if (data.source === 'Trendyol') {
                level = response?.response_data?.statusCode === 200 || response.success ? "info" : "error"
            } else if (data.source === 'Decathlon') {
                level = response?.response_status === 200 ? "info" : "error"
            }

            console.info(JSON.stringify({
                type: "scrape-response",
                level: level,
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
