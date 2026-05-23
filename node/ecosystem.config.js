module.exports = {
    apps: [
        {
            name: "api-server",
            script: "src/server.js",
            cwd: "/root/zitazi/node",
            autorestart: true,
        },

        {
            name: "scraper-orchestrator",
            script: "src/scraper-orchestrator.js",
            cwd: "/root/zitazi/node",
            interpreter: "node",
            cron_restart: "15 47 * * *",
            autorestart: false,
        }
    ]
}