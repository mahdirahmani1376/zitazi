#!/usr/bin/env bash

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
