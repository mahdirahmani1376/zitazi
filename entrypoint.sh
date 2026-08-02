#!/usr/bin/env bash
set -e

chown -R ${USER}:${USER} storage bootstrap/cache
chmod -R 2775 storage bootstrap/cache

umask 0002

exec "$@"