const {BaseStrategy, delay, safeClose, scraperShuttingDown} = require('./BaseStrategy');

class TrendyolStrategy extends BaseStrategy {
    constructor() {
        super('Trendyol');
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

            const delayTime = Math.floor(Math.random() * (5000 - 2000) + 5000);
            await delay(delayTime);

            const responseData = await page.evaluate(() => JSON.parse(document.body.innerText));

            if ([418, 429].includes(response?.status())) {
                console.error(JSON.stringify({message: 'Trendyol bot blocked', data, level: 'error'}));
                return {
                    product_id: data.id,
                    response_status: response.status(),
                    response_headers: response.headers(),
                    full_url: data.full_url,
                    success: false,
                    blocked: true,
                };
            }

            if ([404, 410].includes(responseData?.statusCode)) {
                return {
                    product_id: data.id,
                    response_status: response?.status(),
                    response_headers: response?.headers(),
                    full_url: data.full_url,
                    success: false,
                    blocked: false,
                    deleted: true,
                    retry_count: data.retry_count ?? 0,
                };
            }

            if (responseData?.result?.merchantListing?.winnerVariant?.price?.currency !== 'TRY') {
                console.log(JSON.stringify({
                    message: 'invalid currency',
                    level: 'error',
                    product_id: data.id,
                    currency: responseData?.result?.merchantListing?.winnerVariant?.price?.currency
                }));
                closeBrowser = true;
                return {
                    product_id: data.id,
                    response_status: response?.status(),
                    response_headers: response?.headers(),
                    full_url: data.full_url,
                    success: false,
                    blocked: false,
                    deleted: false,
                    invalid_currency: true,
                    retry_count: data.retry_count ?? 0,
                };
            }

            return {
                product_id: data.id,
                response_data: responseData,
                response_status: response?.status(),
                headers: response.headers(),
                url: response.url(),
                full_url: data.full_url,
                success: responseData?.isSuccess && responseData?.statusCode === 200 && (response?.status() >= 200 && response?.status() < 400),
            };
        } catch (err) {
            const error = {name: err.name, message: err.message};

            if (error.name === 'TimeoutError' || error.message.includes('Target.createTarget timed out')) {
                console.error(JSON.stringify({message: 'Trendyol browser has Timeout error', error}));
                closeBrowser = true;
            }

            return {product_id: data.id, full_url: data.full_url, success: false, error};
        } finally {
            if (page) await safeClose(() => page.close());
            if (closeBrowser || scraperShuttingDown) await this.closeBrowser();
        }
    }
}

module.exports = TrendyolStrategy;