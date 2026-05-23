module.exports = {
    apps: [
        {
            name: "api-server",
            script: "src/server.js",
            cwd: "/root/zitazi/node",
            autorestart: true,
        },

    ]
}