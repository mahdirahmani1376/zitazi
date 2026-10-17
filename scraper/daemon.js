const beginScrape = require('./scraper');
const Worker = require('./Worker');
const {setShuttingDown} = require('./BaseStrategy');

let shuttingDown = false;

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
    new Worker("Torob", 'laravel_database_torob_scrape_product'),
];

const workerPromises = workers.map(w => w.run(beginScrape, () => shuttingDown));

async function shutdown(signal) {
    if (shuttingDown) return;
    shuttingDown = true;
    setShuttingDown();

    console.info(JSON.stringify({
        message: `Received ${signal}, shutting down gracefully...`,
        level: 'info'
    }));

    const forceExit = setTimeout(() => process.exit(0), 10000);
    forceExit.unref();

    try {
        await workerPromises;
    } catch (e) {
        console.error(JSON.stringify({
            message: 'error while stopping workers',
            error: {
                name: e.name,
                message: e.message,
                stack: e.stack
            },
            level: 'error'
        }));
    }

    await Promise.all(workers.map(w => w.close()));
    process.exit(0);
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));

Promise.all(workerPromises).catch(console.error);