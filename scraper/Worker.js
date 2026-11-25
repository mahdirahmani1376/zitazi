const Redis = require('ioredis');

/**
 * Exponential backoff ladder, in seconds: 5m → 15m → 60m → 300m.
 * The last step is the cap: a blocked product is never dropped, it keeps
 * cycling until the ban lifts.
 */
const COOLDOWN_STEPS_SECONDS = [5 * 60, 15 * 60, 60 * 60, 300 * 60];
const BACKOFF_JITTER = 0.2;
const QUEUE_OUT = 'laravel_database_scrape_result';

/**
 * A failed backoff run is expiring on its own; the streak counter only needs
 * to survive long enough to keep the ladder climbing.
 */
const BLOCK_STREAK_TTL = 6 * 60 * 60;

/**
 * How many consecutive blocks before the native browser is considered hard
 * banned and requests go through the ScrapeOps fallback instead.
 */
const HARD_BAN_STREAK = 3;

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
        this.blockStreakKey = `scraper:block-streak:${this.slug}`;
    }

    async run(scrapeFn, shuttingDown) {
        console.info(`${this.name} worker started...`);

        while (!shuttingDown()) {
            try {
                const ttl = await this.redis.ttl(this.cooldownKey);
                const coolingDown = ttl > 0;
                const streak = Number(await this.redis.get(this.blockStreakKey)) || 0;
                const hardBan = streak >= HARD_BAN_STREAK;

                // Once the ban looks hard, drain the queue through the ScrapeOps
                // fallback instead of stalling. A single block just backs off,
                // so no credits are spent on something a short pause would fix.
                const useFallback = this.isFallbackAvailable() && hardBan;
                const skipBrowser = coolingDown && useFallback;

                // Full cooldown sleep: every blocked scrape sets this key so
                // the source backs off for the configured ladder step (5m →
                // 15m → 60m → 300m). The sleep still respects shutdown signals
                // through the 200ms tick, so the worker can always be stopped.
                if (coolingDown && !skipBrowser) {
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

                // Per-product backoff: the product itself carries a timestamp
                // from its last block. Skip it silently until that time passes,
                // so a product blocked for 300m doesn't burn a CPU cycle every
                // 60 seconds only to get blocked again.
                if (data.retry_after && Date.now() < data.retry_after) {
                    await this.publishStatus(data.product.id, 'rate_limited');
                    continue;
                }

                await this.publishStatus(data.product.id, 'processing');

                const response = await scrapeFn(this.name, data.product, {
                    skipBrowser,
                    allowFallback: useFallback,
                });

                const retried = await this.handleBlocked(data, response);
                if (retried) {
                    continue;
                }

                await this.handleInvalidCurrency(data, response);

                if (response.native_blocked === true) {
                    // The native browser was blocked but the fallback rescued
                    // the product: escalate the streak and arm the cooldown so
                    // the next items skip straight to the fallback.
                    await this.registerNativeBlock();
                    await this.publishStatus(data.product.id, 'rate_limited');
                } else if (response.success === true) {
                    // A genuine success (native or fallback) means the ban is
                    // gone, so the ladder starts over at 5m on the next block.
                    await this.clearBlockStreak();
                }

                if (response.scrapeops_fallback === true) {
                    response.scrapeops_requests_today = await this.recordFallbackUsage();
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

    /**
     * The ScrapeOps fallback is available whenever a key is configured. There
     * is no request budget: the daily counter is reporting only.
     */
    isFallbackAvailable() {
        if (process.env.SCRAPE_OPS_FALLBACK_ENABLED === '0') {
            return false;
        }

        return Boolean(process.env.SCRAPE_OPS_KEY);
    }

    /**
     * Count one fallback request for today's Telegram report. Never gates the
     * request — it only exists so the daily cost is visible.
     */
    async recordFallbackUsage() {
        try {
            const key = `scrapeops:fallback:requests:${this.todayKey()}`;
            const count = await this.redis.incr(key);

            if (count === 1) {
                await this.redis.expire(key, 2 * 24 * 60 * 60);
            }

            return count;
        } catch (e) {
            console.error(JSON.stringify({
                message: 'failed to record scrapeops fallback usage',
                error: {name: e.name, message: e.message},
                level: 'error',
            }));

            return null;
        }
    }

    todayKey() {
        const timeZone = process.env.TZ || 'Asia/Tehran';

        return new Intl.DateTimeFormat('en-CA', {timeZone}).format(new Date());
    }

    /**
     * Count one consecutive block and return the cooldown it earns.
     * Steps climb 5m → 15m → 60m → 300m and stay at the last one.
     */
    async nextCooldownSeconds() {
        let streak = 1;

        try {
            streak = await this.redis.incr(this.blockStreakKey);
            await this.redis.expire(this.blockStreakKey, BLOCK_STREAK_TTL);
        } catch (e) {
            console.error(JSON.stringify({
                message: 'failed to read block streak, using first step',
                error: {name: e.name, message: e.message},
                level: 'error',
            }));
        }

        const step = COOLDOWN_STEPS_SECONDS[Math.min(streak, COOLDOWN_STEPS_SECONDS.length) - 1];
        const jitter = step * BACKOFF_JITTER * (Math.random() * 2 - 1);

        return {
            streak,
            cooldownSeconds: Math.max(1, Math.round(step + jitter)),
        };
    }

    /**
     * The native browser was blocked but the fallback delivered the product.
     * Count the block (so a hard ban still escalates to the fallback) and arm
     * the cooldown so following items skip straight to the fallback. The native
     * browser is retried once the cooldown expires, which is how we check
     * periodically whether the ban has lifted.
     */
    async registerNativeBlock() {
        const {cooldownSeconds} = await this.nextCooldownSeconds();

        try {
            await this.redis.setex(this.cooldownKey, cooldownSeconds, '1');
        } catch (e) {
            console.error(JSON.stringify({
                message: 'failed to arm cooldown',
                error: {name: e.name, message: e.message},
                level: 'error',
            }));
        }
    }

    async clearBlockStreak() {
        try {
            const streak = await this.redis.get(this.blockStreakKey);

            if (streak) {
                console.info(JSON.stringify({
                    message: `${this.name} block streak cleared after a successful scrape`,
                    previous_streak: Number(streak),
                    level: 'info',
                }));
            }

            await this.redis.del(this.blockStreakKey);
        } catch (e) {
            console.error(JSON.stringify({
                message: 'failed to clear block streak',
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

        const {streak, cooldownSeconds} = await this.nextCooldownSeconds();

        data.retry_count = (data.retry_count ?? 0) + 1;
        data.retry_after = Date.now() + cooldownSeconds * 1000;

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
                block_streak: streak,
                hard_ban: streak >= HARD_BAN_STREAK,
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
