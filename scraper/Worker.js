const Redis = require('ioredis');

/**
 * Exponential backoff ladder for a blocked source (the ban is per IP, so one
 * ladder is shared by every product of that source):
 *   5m → 1h → 3h → 6h → 24h, then stay at 24h forever.
 * A single successful scrape deletes the rung, so the next block starts at 5m
 * again.
 */
const COOLDOWN_STEPS_SECONDS = [
    5 * 60,          // 5 minutes
    60 * 60,         // 1 hour
    3 * 60 * 60,     // 3 hours
    6 * 60 * 60,     // 6 hours
    24 * 60 * 60,    // 1 day (repeats)
];
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
        this.slug = name.toLowerCase();
        this.cooldownKey = `scraper:cooldown:${this.slug}`;
        // Current rung of the ladder (1..5). Deliberately has no TTL: an
        // abandoned high rung only means "keep waiting a day", and a success
        // resets it to the first step.
        this.ladderKey = `scraper:ladder:${this.slug}`;
    }

    async run(scrapeFn, shuttingDown) {
        console.info(`${this.name} worker started...`);

        while (!shuttingDown()) {
            try {
                const ttl = await this.redis.ttl(this.cooldownKey);

                // The cooldown is the whole point of the backoff: sleep its
                // full remaining time. Skipping or capping this sleep is what
                // turned a 5-hour cooldown into a request every ~2 minutes.
                if (ttl > 0) {
                    console.info(JSON.stringify({
                        message: `${this.name} cooling down for ${ttl}s`,
                        cooldown_ttl: ttl,
                        level: 'info',
                    }));

                    await this.sleep(ttl * 1000, shuttingDown);
                    continue;
                }

                const result = await this.redis.blpop(this.queueIn, 1);
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

                if (response.success === true) {
                    // A fresh success means the ban is gone: the next block
                    // starts the ladder over at 5 minutes.
                    await this.resetLadder();
                }

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
        // Wake periodically so a deploy can shut the worker down promptly, but
        // not so often that a day-long cooldown burns CPU.
        const tick = Math.min(5000, ms);

        while (!shuttingDown() && Date.now() - started < ms) {
            await new Promise(resolve => setTimeout(resolve, tick));
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

    /**
     * Advance the shared ladder by one rung and return the cooldown that rung
     * earned. Rungs climb 5m → 1h → 3h → 6h → 24h and then stay at 24h.
     */
    async nextCooldown() {
        let rawRung = 1;

        try {
            rawRung = await this.redis.incr(this.ladderKey);
        } catch (e) {
            console.error(JSON.stringify({
                message: 'failed to advance the cooldown ladder, using the first step',
                error: {name: e.name, message: e.message},
                level: 'error',
            }));
        }

        // Clamp to the last rung: after 24h every further block keeps 24h.
        const rung = Math.min(rawRung, COOLDOWN_STEPS_SECONDS.length);
        const cooldownSeconds = COOLDOWN_STEPS_SECONDS[rung - 1];

        return {rung, cooldownSeconds};
    }

    /**
     * Drop the ladder back to the first rung. Called after any successful
     * scrape: the ban has lifted, so the next block waits only 5 minutes.
     */
    async resetLadder() {
        try {
            const rung = Number(await this.redis.get(this.ladderKey)) || 0;

            if (rung > 0) {
                console.info(JSON.stringify({
                    message: `${this.name} cooldown ladder reset after a successful scrape`,
                    previous_rung: rung,
                    level: 'info',
                }));
            }

            await this.redis.del(this.ladderKey);
        } catch (e) {
            console.error(JSON.stringify({
                message: 'failed to reset the cooldown ladder',
                error: {name: e.name, message: e.message},
                level: 'error',
            }));
        }
    }

    /**
     * Handle an anti-bot block: climb the ladder, cool down the whole source,
     * requeue the product and report the block to Laravel so the product is
     * marked `rate_limited` without touching stock, price or WooCommerce.
     */
    async handleBlocked(data, response) {
        if (!response.blocked) return false;

        const {rung, cooldownSeconds} = await this.nextCooldown();

        data.retry_count = (data.retry_count ?? 0) + 1;

        await this.publishStatus(data.product.id, 'no_response_retrying');

        try {
            await this.redis.setex(this.cooldownKey, cooldownSeconds, '1');
            await this.redis.rpush(this.queueIn, JSON.stringify(data));
        } catch (e) {
            console.error(JSON.stringify({
                message: `${this.name} failed to requeue blocked product`,
                error: {name: e.name, message: e.message},
                level: 'error',
            }));
        }

        await this.publishStatus(data.product.id, 'rate_limited');

        console.error(
            JSON.stringify({
                message: `${this.name} bot detected. Product ${data.product.id} returned to queue. Cooldown: ${cooldownSeconds}s`,
                level: 'error',
                product_id: data.product.id,
                url: data.product.decathlon_url ?? data.product.full_url ?? null,
                block_reason: response.block_reason ?? null,
                ladder_rung: rung,
                next_retry_in_seconds: cooldownSeconds,
                response_status: response.response_status ?? null,
            })
        );

        // Send the block onward so Laravel can mark the product `rate_limited`.
        // The payload carries no `response_data`, so PHP only changes the sync
        // status — stock, price and variations are left untouched.
        response.bulk = data.bulk ?? false;
        response.source = this.name;

        try {
            await this.redis.rpush(this.queueOut, JSON.stringify(response));
        } catch (e) {
            console.error(JSON.stringify({
                message: `${this.name} failed to publish blocked result`,
                error: {name: e.name, message: e.message},
                level: 'error',
            }));
        }

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
