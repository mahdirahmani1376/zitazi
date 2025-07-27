#!/bin/bash

# === Config ===
DATE=$(date +%F)

set -e
# Load .env file
set -o allexport
source ./.env
set +o allexport

echo "env loaded"
# === Create backup dir if it doesn't exist ===
mkdir -p "$BACKUP_DIR"

# === Cleanup old backups ===
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

# === Run backup ===
docker exec "$DB_CONTAINER" /usr/bin/mysqldump -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" | gzip > "$BACKUP_DIR/db-backup-$DATE.sql.gz"
