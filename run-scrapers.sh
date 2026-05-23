#!/bin/bash

echo "=== Scrape started at $(date) ==="

/usr/bin/node node/src/scraper-tr.js
/usr/bin/node node/src/scraper.js
/usr/bin/node node/src/scraper-retry.js

echo "=== Scrape finished at $(date) ==="