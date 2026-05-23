#!/bin/bash
LOGFILE=/var/log/scraper.log

truncate -s 0 "$LOGFILE"

echo "=== Scrape started at $(date) ===" >> $LOGFILE

/usr/bin/node node/src/scraper-tr.js >> $LOGFILE 2>&1
#/usr/bin/node node/src/scraper.js >> $LOGFILE 2>&1
#/usr/bin/node node/src/scraper-retry.js >> $LOGFILE 2>&1

echo "=== Scrape finished at $(date) ===" >> $LOGFILE