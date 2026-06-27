#!/usr/bin/env bash

chown -R www-data:www-data src/storage src/bootstrap/cache
chmod -R 775 storage src/storage
chmod -R 775 storage src/bootstrap/cache

/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
