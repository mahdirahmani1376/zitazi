/root/.acme.sh/acme.sh --issue \
    --dns dns_cf \
    -d zitazi-crawler.ir \
    -d '*.zitazi-crawler.ir'

/root/.acme.sh/acme.sh --install-cert \
-d zitazi-crawler.ir \
--reloadcmd "docker exec zitazi-nginx nginx -s reload"