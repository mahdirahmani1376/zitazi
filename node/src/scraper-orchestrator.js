import {exec} from "child_process";

function run(cmd) {
    return new Promise((resolve, reject) => {
        exec(cmd, (err, stdout, stderr) => {
            if (err) return reject(err);
            console.log(stdout);
            console.error(stderr);
            resolve();
        });
    });
}

async function main() {
    console.log("Scraper job started");

    await run("node scraper-tr.js");
    await run("node scraper.js");
    await run("node scraper-retry.js");

    console.log("Scraper job finished");
}

main().catch(err => {
    console.error("FAILED:", err);
    process.exit(1);
});