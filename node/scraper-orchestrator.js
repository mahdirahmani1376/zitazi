const {spawn} = require("child_process");

function run(cmd, args = []) {
    return new Promise((resolve, reject) => {
        const child = spawn(cmd, args, {
            stdio: "inherit", // stream output directly
            shell: true
        });

        child.on("close", (code) => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`${cmd} exited with code ${code}`));
            }
        });

        child.on("error", reject);
    });
}

async function main() {
    console.log("Scraper job started");
    console.log(Date.now());

    await run("node", ["src/scraper-tr.js"]);
    await run("node", ["src/scraper.js"]);
    await run("node", ["src/scraper-retry.js"]);

    console.log("Scraper job finished");
    console.log(Date.now());
}

main().catch(err => {
    console.error("FAILED:", err);
    process.exit(1);
});