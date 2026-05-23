#!/bin/bash

truncate -s 0 "$LOGFILE"

echo "=== Scrape started at $(date) ==="

/usr/bin/node node/src/scraper-tr.js


echo "=== Scrape finished at $(date) ==="