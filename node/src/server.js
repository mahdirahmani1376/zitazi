const express = require('express');
const {spawn} = require('child_process');
const path = require('path');

const app = express();
app.use(express.json());

app.get('/', (req, res) => {
    res.send('✅ Server is running. Use POST /scrape to start scraping.');
});

app.post('/scrape', async (req, res) => {
    const data = req.body;
    if (!data) return res.status(400).json({error: 'Missing "data" payload'});

    console.log('📩 Received scrape request:', data.decathlon_url);

    const scraperPath = path.resolve('./scraper-url.js');
    const child = spawn('node', [scraperPath, JSON.stringify(data)], {
        stdio: 'inherit', // inherit output for logs
    });

    child.on('error', (err) => {
        console.error('❌ Failed to start scraper process:', err);
    });

    // Kill the process if it exceeds 5 minutes
    setTimeout(() => {
        if (!child.killed) {
            console.warn('⚠️ Killing slow scrape process...');
            child.kill('SIGTERM');
        }
    }, 5 * 60 * 1000);

    res.json({success: true, message: 'Scraping started in background.'});
});

app.listen(3000, () => console.log('🚀 Server running on port 3000'));
