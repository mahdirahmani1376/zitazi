#!/bin/bash
LOGFILE=/var/log/sync.log

truncate -s 0 /var/log/*.log
truncate -s 0 /var/log/*/*.log

echo "=== Sync started at $(date) ===" >> $LOGFILE

docker exec -it zitazi-php php artisan db:seed; \
docker exec -it zitazi-php php artisan app:sync-zitazi; \
docker exec -it zitazi-php php artisan batch:sync-zitazi-products; \
docker exec -it zitazi-php php artisan app:sync-satreh

echo "=== Sync finished at $(date) ===" >> $LOGFILE
