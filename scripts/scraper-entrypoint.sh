#!/usr/bin/env bash
set -e

# Dev scraper entrypoint:
#   1. Copy the host Chrome profile into the writable container volume
#      (one-time snapshot at container start).
#   2. Clean stale Chrome singleton locks from the copied profile.
#   3. Start Xvfb so headful Chromium has a display (headful passes the
#      Cloudflare "Just a moment" challenge that blocks headless Chromium).
#   4. Run the scraper daemon.
#
# Controlled by the same env vars the compose files set:
#   CHROME_USER_DATA_DIR - writable profile location
#   /host/chrome-profile - read-only host profile source (optional)

if [ -z "${CHROME_USER_DATA_DIR}" ]; then
    echo "[entrypoint] CHROME_USER_DATA_DIR is not set; skipping profile copy"
else
    mkdir -p "${CHROME_USER_DATA_DIR}"
    if [ -d /host/chrome-profile ] && [ "$(ls -A /host/chrome-profile 2>/dev/null)" ]; then
        echo "[entrypoint] copying host Chrome profile snapshot..."
        cp -a /host/chrome-profile/. "${CHROME_USER_DATA_DIR}/"
        rm -f "${CHROME_USER_DATA_DIR}"/Singleton*
    else
        echo "[entrypoint] /host/chrome-profile empty or missing; starting with fresh profile"
    fi
fi

# Start Xvfb on :99 if not already running.
if [ -n "${DISPLAY}" ] && ! pgrep -f "Xvfb ${DISPLAY}" >/dev/null 2>&1; then
    echo "[entrypoint] starting Xvfb on ${DISPLAY}..."
    rm -f "/tmp/.X${DISPLAY#:}-lock"
    Xvfb "${DISPLAY}" -screen 0 1280x1024x24 -nolisten tcp >/tmp/xvfb.log 2>&1 &
    sleep 2
fi

echo "[entrypoint] starting scraper daemon..."
exec node daemon.js
