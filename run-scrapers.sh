#!/bin/bash
LOGFILE=/var/log/scraper.log

truncate -s 0 /var/log/*.log
truncate -s 0 /var/log/*/*.log

echo "=== Scrape started at $(date) ===" >> $LOGFILE

node ~/projects/zitazi/node/src/scraper.js >> $LOGFILE 2>&1
node ~/projects/zitazi/node/src/scraper-tr.js >> $LOGFILE 2>&1
node ~/projects/zitazi/node/src/scraper-retry.js >> $LOGFILE 2>&1

echo "=== Scrape finished at $(date) ===" >> $LOGFILE
