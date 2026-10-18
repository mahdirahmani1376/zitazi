const {BaseStrategy, delay, safeClose, isShuttingDown} = require('./BaseStrategy');

class TorobStrategy extends BaseStrategy {
    constructor() {
        super('Torob');
    }

    async scrape(data) {
        if (!data.full_url?.trim()) {
            return {product_data: data, success: false, message: 'empty url provided'};
        }

        let page = null;
        let response = null;
        let closeBrowser = false;

        try {
            const browser = await this.ensureBrowser();
            page = await browser.newPage();

            response = await page.goto(data.full_url, {waitUntil: 'domcontentloaded', timeout: 1000 * 60});

            const status = response?.status();
            const responseHeaders = response?.headers() ?? {};
            const contentType = responseHeaders['content-type'] ?? '';

            if ([418, 429, 490].includes(status) || contentType.includes('text/html')) {
                console.error(JSON.stringify({message: 'Torob bot blocked', data, level: 'error', status, content_type: contentType}));
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

            const delayTime = Math.floor(Math.random() * 15000 + 5000);
            await delay(delayTime);

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

            if (error.name === 'SyntaxError') {
                console.error(JSON.stringify({message: 'Torob bot blocked (non-JSON response)', data, level: 'error'}));
                return {
                    product_id: data.id,
                    response_status: response?.status(),
                    response_headers: response?.headers(),
                    full_url: data.full_url,
                    success: false,
                    blocked: true,
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