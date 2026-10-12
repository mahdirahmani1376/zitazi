const beginScrape = require('./scraper');
const Worker = require('./Worker');

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

const workers = [
    new Worker("Trendyol", 'laravel_database_trendyol_scrape_product'),
    new Worker("Decathlon", 'laravel_database_decathlon_scrape_product'),
];

Promise.all(workers.map(w => w.run(beginScrape, () => shuttingDown))).catch(console.error);