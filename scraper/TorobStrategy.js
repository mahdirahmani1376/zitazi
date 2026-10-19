const Redis = require('ioredis');
const {BaseStrategy, delay, safeClose, isShuttingDown} = require('./BaseStrategy');

const CIRCUIT_BREAKER_FAILURE_COUNT_KEY = 'scraper:torob:circuit_failure_count';
const CIRCUIT_BREAKER_OPEN_KEY = 'scraper:torob:circuit_open';
const CIRCUIT_BREAKER_THRESHOLD = Number(process.env.TOROB_CIRCUIT_BREAKER_THRESHOLD) || 5;
const CIRCUIT_BREAKER_TTL_SECONDS = Number(process.env.TOROB_CIRCUIT_BREAKER_TTL_SECONDS) || 5 * 60 * 60;

class TorobStrategy extends BaseStrategy {
    constructor() {
        super('Torob');
        this.redis = new Redis({
            host: 'zitazi-redis',
            port: 6379,
            password: process.env.REDIS_PASSWORD,
        });
    }

    async isCircuitOpen() {
        try {
            return (await this.redis.exists(CIRCUIT_BREAKER_OPEN_KEY)) === 1;
        } catch (e) {
            console.error(JSON.stringify({
                message: 'Torob circuit breaker redis error while checking state',
                error: {name: e.name, message: e.message},
                level: 'error'
            }));

            return false;
        }
    }

    async recordResult(status) {
        try {
            if (status === 490) {
                const failures = await this.redis.incr(CIRCUIT_BREAKER_FAILURE_COUNT_KEY);

                if (failures >= CIRCUIT_BREAKER_THRESHOLD) {
                    await this.redis.setex(CIRCUIT_BREAKER_OPEN_KEY, CIRCUIT_BREAKER_TTL_SECONDS, '1');
                    await this.redis.del(CIRCUIT_BREAKER_FAILURE_COUNT_KEY);

                    console.error(JSON.stringify({
                        message: `Torob circuit breaker opened after ${failures} consecutive 490 responses`,
                        ttl_seconds: CIRCUIT_BREAKER_TTL_SECONDS,
                        level: 'error'
                    }));
                }
            } else {
                await this.redis.del(CIRCUIT_BREAKER_FAILURE_COUNT_KEY);
            }
        } catch (e) {
            console.error(JSON.stringify({
                message: 'Torob circuit breaker redis error while recording result',
                error: {name: e.name, message: e.message},
                level: 'error'
            }));
        }
    }

    async scrape(data) {
        if (!data.full_url?.trim()) {
            return {product_data: data, success: false, message: 'empty url provided'};
        }

        if (await this.isCircuitOpen()) {
            console.error(JSON.stringify({
                message: 'Torob circuit breaker open, skipping request',
                product_id: data.id,
                level: 'error'
            }));

            return {
                product_id: data.id,
                full_url: data.full_url,
                success: false,
                blocked: false,
                retry_count: data.retry_count ?? 0,
                circuit_breaker_open: true,
            };
        }

        let page = null;
        let response = null;
        let closeBrowser = false;

        try {
            const browser = await this.ensureBrowser();
            page = await browser.newPage();

            const preDelayTime = Math.floor(Math.random() * 15000 + 5000);
            await delay(preDelayTime);

            response = await page.goto(data.full_url, {waitUntil: 'domcontentloaded', timeout: 1000 * 60});

            const status = response?.status();
            const responseHeaders = response?.headers() ?? {};
            const contentType = responseHeaders['content-type'] ?? '';

            await this.recordResult(status);

            if ([418, 429, 490].includes(status) || contentType.includes('text/html')) {
                return {
                    product_id: data.id,
                    response_status: status,
                    response_headers: responseHeaders,
                    full_url: data.full_url,
                    success: false,
                    blocked: false,
                    retry_count: data.retry_count ?? 0,
                };
            }

            const responseData = await page.evaluate(() => JSON.parse(document.body.innerText));

            if (status === 404) {
                return {
                    product_id: data.id,
                    response_status: status,
                    response_headers: responseHeaders,
                    full_url: data.full_url,
                    success: false,
                    blocked: false,
                    deleted: true,
                    retry_count: data.retry_count ?? 0,
                };
            }

            return {
                product_id: data.id,
                response_data: responseData['products_info'],
                response_status: status,
                headers: responseHeaders,
                url: response.url(),
                full_url: data.full_url,
                success: (status >= 200 && status < 400),
            };
        } catch (err) {
            const error = {name: err.name, message: err.message};

            await this.recordResult(response?.status());

            if (error.name === 'SyntaxError') {
                console.error(JSON.stringify({message: 'Torob bot blocked (non-JSON response)', data, level: 'error'}));
                return {
                    product_id: data.id,
                    response_status: response?.status(),
                    response_headers: response?.headers(),
                    full_url: data.full_url,
                    success: false,
                    blocked: false,
                    retry_count: data.retry_count ?? 0,
                };
            }

            if (error.name === 'TimeoutError' || error.message.includes('Target.createTarget timed out')) {
                console.error(JSON.stringify({message: 'Torob browser has Timeout error', error}));
                closeBrowser = true;
            }

            return {
                product_id: data.id,
                full_url: data.full_url,
                success: false,
                response_status: response?.status(),
                response_headers: response?.headers(),
                error
            };
        } finally {
            if (page) await safeClose(() => page.close());
            if (closeBrowser || isShuttingDown()) await this.closeBrowser();
        }
    }
}

module.exports = TorobStrategy;