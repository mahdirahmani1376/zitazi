const Redis = require('ioredis');
const beginScrape = require('./scraper');

let shuttingDown = false;

process.on('SIGTERM', () => {
    shuttingDown = true;
});

process.on('unhandledRejection', (reason) => {
    console.error(JSON.stringify({
        message: 'unhandled rejection',
        error: {
            name: reason?.name || 'Error',
            message: reason?.message || String(reason),
            stack: reason?.stack
        },
        level: 'error'
    }));
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

            if (response.deleted) {
                data.retry_count = (data.retry_count || 0) + 1;
                if (data.retry_count <= 1) {
                    await redis.rpush(queueIn, JSON.stringify(data));
                }

                await redis.publish(
                    'laravel_database_product_sync_status_changed',
                    JSON.stringify({
                        product_id: data.product.id,
                        status: 'no_response_retrying'
                    })
                );

                console.error(JSON.stringify({
                    message: "product may be deleted",
                    source: name,
                    product_id: data.product.id,
                    level: 'error'
                }))

            } else if (response.invalid_currency) {
                data.retry_count = (data.retry_count || 0) + 1;
                if (data.retry_count <= 1) {
                    await redis.rpush(queueIn, JSON.stringify(data));
                }

                await redis.publish(
                    'laravel_database_product_sync_status_changed',
                    JSON.stringify({
                        product_id: data.product.id,
                        status: 'no_response_retrying'
                    })
                );
            }

            response.bulk = data.bulk ?? false
            response.source = name

            await redis.rpush(
                QUEUE_OUT,
                JSON.stringify(response)
            );

            let level = 'debug';

            level = response.success
                ? 'info'
                : 'error';

            const logger = console[level] ?? console.log;

            logger(JSON.stringify({
                type: "scrape-response",
                source: name,
                product_id: data.product.id,
                response,
                level: level
            }));


        } catch (e) {
            console.error(JSON.stringify({
                message: `${name} worker error`,
                error: {
                    name: e.name,
                    message: e.message,
                    stack: e.stack
                },
                level: 'error'
            }));
        }
    }
}

Promise.all([
    runWorker("Trendyol", TR_QUEUE_IN),
    runWorker("Decathlon", DE_QUEUE_IN)
]).catch(console.error);
