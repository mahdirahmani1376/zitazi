const TrendyolStrategy = require('./TrendyolStrategy');
const TorobStrategy = require('./TorobStrategy');
const DecathlonStrategy = require('./DecathlonStrategy');

const strategies = {
    Trendyol: new TrendyolStrategy(),
    Torob: new TorobStrategy(),
    Decathlon: new DecathlonStrategy(),
};

async function beginScrape(name, data) {
    const strategy = strategies[name];
    if (!strategy) return {product_id: data.id, success: false, error: 'no valid source'};
    return strategy.scrape(data);
}

module.exports = beginScrape;