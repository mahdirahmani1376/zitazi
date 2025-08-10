const express = require('express');
const puppeteer = require('puppeteer-extra');
const stealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(stealthPlugin());
const {scrapeUrl} = require('./scraper');

const app = express();
app.use(express.json());

app.post('/scrape', async (req, res) => {
    const {url} = req.body;
    if (!url) {
        return res.status(400).json({error: 'URL is required'});
    }
    try {
        const data = await scrapeUrl(url);
        res.json(data);
    } catch (err) {
        console.error(err);
        res.status(500).json({error: err.message, success: false});
    }
});

app.get('/', (req, res) => {
    return res.json('success')
});

app.listen(3000, '0.0.0.0', () => {
    console.log('Scraper API listening on port 3000');
});
