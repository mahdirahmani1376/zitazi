const Redis = require('ioredis');
const beginScrape = require('./scraper');

let shuttingDown = false;

process.on('SIGTERM', () => {
    shuttingDown = true;
});
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

    while (!shuttingDown) {
        try {

            const result = await redis.blpop(queueIn, 0)

            const data = JSON.parse(result[1]);

            await redis.setex(
                `laravel_database_sync_status_product:${data.product.id}`,
                86400,
                'processing'
            );

            await redis.publish(
                `laravel_database_product_sync_status_changed`,
                JSON.stringify({
                    product_id: data.product.id,
                    status: 'processing'
                })
            );

            const response = await beginScrape(name, data.product);
            response.bulk = data.bulk ?? false
            response.source = name

            await redis.rpush(
                QUEUE_OUT,
                JSON.stringify(response)
            );

            let level = 'debug';

            if (name === 'Trendyol') {
                level = response?.response_data?.statusCode === 200 && response.success
                    ? 'info'
                    : 'error';
            } else if (name === 'Decathlon') {
                level = response?.response_status === 200 && response.success
                    ? 'info'
                    : 'error';
            }

            const logger = console[level] ?? console.log;

            logger(JSON.stringify({
                type: "scrape-response",
                source: name,
                product_id: data.product.id,
                response,
                level: level
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
