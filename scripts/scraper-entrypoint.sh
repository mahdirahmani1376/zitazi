#!/usr/bin/env bash
set -e

# Dev scraper entrypoint:
#   1. Start Xvfb so headful Chromium has a display (headful passes the
#      Cloudflare "Just a moment" challenge that blocks headless Chromium).
#   2. Run the scraper daemon.
#
# The Chrome profile is fully container-local (anonymous volume bound to
# CHROME_USER_DATA_DIR), so the host never sees or locks it.

# The dev image does not bake node_modules in (./scraper is bind-mounted and
# shadows /app), so install dependencies if the mounted host dir has none.
if [ ! -d /app/node_modules ] || [ -z "$(ls -A /app/node_modules 2>/dev/null)" ]; then
    echo "[entrypoint] node_modules missing; installing dependencies..."
    npm install --no-audit --no-fund
fi

# Start Xvfb on :99 if not already running.
if [ -n "${DISPLAY}" ] && ! pgrep -f "Xvfb ${DISPLAY}" >/dev/null 2>&1; then
    echo "[entrypoint] starting Xvfb on ${DISPLAY}..."
    rm -f "/tmp/.X${DISPLAY#:}-lock"
    Xvfb "${DISPLAY}" -screen 0 1280x1024x24 -nolisten tcp >/tmp/xvfb.log 2>&1 &
    sleep 2
fi

# Start x11vnc so the host can watch the headful Chromium. The compose file
# binds the VNC port to 127.0.0.1 on the host, so exposing it without a
# password is safe for local dev. Kill a stale x11vnc holding port 5900.
if [ -n "${DISPLAY}" ] && ! pgrep -f "x11vnc -display ${DISPLAY}" >/dev/null 2>&1; then
    echo "[entrypoint] starting x11vnc on :5900..."
    pkill -f "x11vnc -display ${DISPLAY}" 2>/dev/null || true
    x11vnc -display "${DISPLAY}" \
        -forever \
        -shared \
        -rfbport 5900 \
        -nopw \
        -quiet \
        >/tmp/x11vnc.log 2>&1 &
    sleep 1
fi

# Start websockify (noVNC) so the headful Chromium can be watched from a
# browser tab at http://localhost:6080/vnc.html. It serves the noVNC static
# files and proxies WebSocket to x11vnc on :5900.
if [ -n "${DISPLAY}" ] && ! pgrep -f "websockify --web=/usr/share/novnc" >/dev/null 2>&1; then
    echo "[entrypoint] starting websockify (noVNC) on :6080..."
    pkill -f "websockify --web=/usr/share/novnc" 2>/dev/null || true
    websockify --web=/usr/share/novnc 6080 localhost:5900 >/tmp/websockify.log 2>&1 &
    sleep 1
fi

echo "[entrypoint] starting scraper daemon..."
exec node daemon.js
