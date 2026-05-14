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
mkdir -p "$BACKUP_DIR"

# === Cleanup old backups ===
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +"$RETENTION_DAYS" -delete

echo "=== backup started at $(date) ===" >> $LOGFILE

# === Run backup ===
/usr/bin/docker exec zitazi-mysql /usr/bin/mysqldump -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" | gzip > "$BACKUP_DIR/db-backup-$DATE.sql.gz"

echo "=== backup finished at $(date) ===" >> $LOGFILE
