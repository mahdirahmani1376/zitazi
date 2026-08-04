#!/bin/bash

LOGFILE=/var/log/backup_mysql.log

truncate -s 0 "$LOGFILE"

# === Config ===
DATE=$(date +%F)

# Load .env file
set -o allexport
source /root/zitazi/.env
set +o allexport

echo "env loaded" >> $LOGFILE
# === Create backup dir if it doesn't exist ===
mkdir -p /root/backup

# === Cleanup old backups ===
find /root/backup -type f -name "*.sql.gz" -mtime +7 -delete

echo "=== backup started at $(date) ===" >> $LOGFILE

# === Run backup ===
/usr/bin/docker exec zitazi-mysql /usr/bin/mysqldump -u"$DB_USER" -p"$DB_PASSWORD" zitazi | gzip > "/root/backup/db-backup-$DATE.sql.gz"

echo "=== backup finished at $(date) ===" >> $LOGFILE
