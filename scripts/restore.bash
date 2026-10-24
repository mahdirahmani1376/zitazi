#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_BACKUP="$SCRIPT_DIR/latest_backup.sql.gz"

# Load Laravel .env file (relative to this script, not the working directory)
set -o allexport
source "$SCRIPT_DIR/../.env"
set +o allexport

# Reuse a locally cached backup if present, otherwise fetch the latest from the server
if [[ -f "$LOCAL_BACKUP" ]]; then
  echo "✅ Using cached backup: $LOCAL_BACKUP"
else
  # Find latest backup (last 7 days)
  LATEST_BACKUP=$(ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST \
      "find $REMOTE_BACKUP_DIR -type f -name '*.sql.gz' -mtime -7 | sort | tail -n 1")

  if [[ -z "$LATEST_BACKUP" ]]; then
    echo "❌ No backup found in last 7 days."$REMOTE_HOST
    exit 1
  fi

  echo "✅ Latest backup: $LATEST_BACKUP"

  # Download into the scripts directory
  scp -P $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST:"$LATEST_BACKUP" "$LOCAL_BACKUP"
fi

# Drop & recreate DB
docker exec -i "zitazi-mysql" mysql -u"root" -p"$DB_PASSWORD" \
    -e "DROP DATABASE IF EXISTS \`zitazi\`; CREATE DATABASE \`zitazi\`;"

# Restore backup
gunzip < "$LOCAL_BACKUP" | \
docker exec -i "zitazi-mysql" mysql -u"root" -p"$DB_PASSWORD" "zitazi"

echo "✅ Restore completed!"