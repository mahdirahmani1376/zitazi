/root/.acme.sh/acme.sh --issue \
    -d zitazi-crawler.ir \
    -d www.zitazi-crawler.ir \
    -d ws.zitazi-crawler.ir \
    -w /root/zitazi/src/public \
    --reloadcmd "docker exec zitazi-nginx nginx -s reload" \
    --force