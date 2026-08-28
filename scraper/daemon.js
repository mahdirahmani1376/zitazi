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
        password: process.env.REDIS_PASSWORD,
    });
}

const TR_QUEUE_IN = 'laravel_database_trendyol_scrape_product';
const DE_QUEUE_IN = 'laravel_database_decathlon_scrape_product';
const COOLDOWN_SECONDS = 20 * 60;
const QUEUE_OUT = 'laravel_database_scrape_result';

async function runWorker(name, queueIn) {
    const redis = createRedis();
    console.info(`${name} worker started...`);

    const cooldownKey = `scraper:cooldown:${name.toLowerCase()}`;

    while (!shuttingDown) {
        try {
            const ttl = await redis.ttl(cooldownKey);

            if (ttl > 0) {
                await new Promise(resolve =>
                    setTimeout(resolve, Math.min(ttl, 60) * 1000)
                );
                continue;
            }


            const result = await redis.blpop(queueIn, 1)
            if (!result) {
                continue;
            }

            const data = JSON.parse(result[1]);

            await redis.publish(
                `laravel_database_product_sync_status_changed`,
                JSON.stringify({
                    product_id: data.product.id,
                    status: 'processing'
                })
            );

            const response = await beginScrape(name, data.product);

            if (response.blocked) {
                await redis.setex(
                    `scraper:cooldown:${name.toLowerCase()}`,
                    COOLDOWN_SECONDS,
                    '1',
                );

                await redis.rpush(
                    queueIn,
                    JSON.stringify(data)
                );

                await redis.publish(
                    'laravel_database_product_sync_status_changed',
                    JSON.stringify({
                        product_id: data.product.id,
                        status: 'cooldown'
                    })
                );

                console.info(
                    `${name} bot detected. Product ${data.product.id} returned to queue. Cooldown: ${COOLDOWN_SECONDS}s`
                );
            }

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
