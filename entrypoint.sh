#!/usr/bin/env bash
set -e

umask 0002

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf