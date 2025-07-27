#!/bin/bash

# === Config ===
DATE=$(date +%F)
BACKUP_DIR="/root/backup"
DB_CONTAINER="zitazi-mysql"
DB_NAME="zitazi"
DB_USER="root"
DB_PASSWORD="123"  # <-- Replace this with your actual MySQL root password
RETENTION_DAYS=7

# === Create backup dir if it doesn't exist ===
mkdir -p "$BACKUP_DIR"

# === Cleanup old backups ===
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

# === Run backup ===
docker exec "$DB_CONTAINER" /usr/bin/mysqldump -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" | gzip > "$BACKUP_DIR/db-backup-$DATE.sql.gz"
