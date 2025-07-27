#!/bin/bash
set -e

# Load Laravel .env file
set -o allexport
source ./.env
set +o allexport

# Find latest backup (last 7 days)
LATEST_BACKUP=$(ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST \
    "find $REMOTE_BACKUP_DIR -type f -name '*.sql.gz' -mtime -7 | sort | tail -n 1")

if [[ -z "$LATEST_BACKUP" ]]; then
  echo "❌ No backup found in last 7 days."DB_HOST
  exit 1
fi

echo "✅ Latest backup: $LATEST_BACKUP"

# Download
scp -P $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST:"$LATEST_BACKUP" "./latest_backup.sql.gz"

# Drop & recreate DB
docker exec -i "$CONTAINER_NAME" mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" \
    -e "DROP DATABASE IF EXISTS \`$DB_DATABASE\`; CREATE DATABASE \`$DB_DATABASE\`;"

# Restore backup
gunzip < "$HOME/Desktop/projects/zitazi/latest_backup.sql.gz" | \
docker exec -i "$CONTAINER_NAME" mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"

echo "✅ Restore completed!"
