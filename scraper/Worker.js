const Redis = require('ioredis');

const COOLDOWN_ARRAY = [15, 60, 300, 600];
const MAX_RETRIES = COOLDOWN_ARRAY.length;
const BACKOFF_JITTER = 0.2;
const QUEUE_OUT = 'laravel_database_scrape_result';

class Worker {
    constructor(name, queueIn) {
        this.name = name;
        this.queueIn = queueIn;
        this.queueOut = QUEUE_OUT;
        this.redis = new Redis({
            host: 'zitazi-redis',
            port: 6379,
            password: process.env.REDIS_PASSWORD,
        });
        this.cooldownKey = `scraper:cooldown:${name.toLowerCase()}`;
    }

    async run(scrapeFn, shuttingDown) {
        console.info(`${this.name} worker started...`);

        while (!shuttingDown()) {
            try {
                const ttl = await this.redis.ttl(this.cooldownKey);

                if (ttl > 0) {
                    await this.sleep(Math.min(ttl, 60) * 1000, shuttingDown);
                    continue;
                }

                const result = await this.redis.blpop(this.queueIn, 1)
                if (!result) {
                    continue;
                }

                const data = JSON.parse(result[1]);

                await this.publishStatus(data.product.id, 'processing');

                const response = await scrapeFn(this.name, data.product);

                const retried = await this.handleBlocked(data, response);
                if (retried) {
                    continue;
                }

                await this.handleInvalidCurrency(data, response);

                response.bulk = data.bulk ?? false
                response.source = this.name

                await this.redis.rpush(
                    this.queueOut,
                    JSON.stringify(response)
                );

                let level = 'debug';
                level = response.success
                    ? 'info'
                    : 'error';

                const logger = console[level] ?? console.log;

                logger(JSON.stringify({
                    type: "scrape-response",
                    source: this.name,
                    product_id: data.product.id,
                    response,
                    level: level
                }));

            } catch (e) {
                console.error(JSON.stringify({
                    message: `${this.name} worker error`,
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

    async sleep(ms, shuttingDown) {
        const started = Date.now();

        while (!shuttingDown() && Date.now() - started < ms) {
            await new Promise(resolve => setTimeout(resolve, Math.min(200, ms)));
        }
    }

    async close() {
        try {
            await this.redis.quit();
        } catch (e) {
            this.redis.disconnect();
        }
    }

    async publishStatus(productId, status) {
        await this.redis.publish(
            'laravel_database_product_sync_status_changed',
            JSON.stringify({product_id: productId, status})
        );
    }

    async handleBlocked(data, response) {
        if (!response.blocked) return false;

        let retryCount = data.retry_count ?? 0;

        if (retryCount >= MAX_RETRIES) {
            return false;
        }

        const retryAfter = Number(response.response_headers?.['retry-after'] ?? response.response_headers?.['Retry-After']);
        const baseSeconds = (Number.isFinite(retryAfter) && retryAfter > 0)
            ? retryAfter
            : COOLDOWN_ARRAY[retryCount];
        const jitter = baseSeconds * BACKOFF_JITTER * (Math.random() * 2 - 1);
        const cooldownSeconds = Math.max(1, Math.round(baseSeconds + jitter));

        data.retry_count = retryCount + 1;

        await this.publishStatus(data.product.id, 'no_response_retrying');

        await this.redis.setex(
            this.cooldownKey,
            cooldownSeconds,
            '1',
        );

        await this.redis.lpush(
            this.queueIn,
            JSON.stringify(data)
        );

        await this.publishStatus(data.product.id, 'cooldown');

        console.error(
            JSON.stringify({
                message: `${this.name} bot detected. Product ${data.product.id} returned to queue. Cooldown: ${cooldownSeconds}s`,
                level: 'error',
                current_retry: retryCount + 1,
                next_retry_in_seconds: cooldownSeconds,
                response_status: response.response_status ?? null,
            })
        );

        return true;
    }

    async handleInvalidCurrency(data, response) {
        if (!response.invalid_currency) return;

        data.retry_count = (data.retry_count || 0) + 1;
        if (data.retry_count <= 1) {
            await this.redis.rpush(this.queueIn, JSON.stringify(data));
        }

        await this.publishStatus(data.product.id, 'invalid_currency');
    }
}

module.exports = Worker;