/root/.acme.sh/acme.sh --issue \
    --dns dns_cf \
    -d zitazi-monitoring.ir \
    -d '*.zitazi-monitoring.ir'

/root/.acme.sh/acme.sh --install-cert \
-d zitazi-monitoring.ir \
--reloadcmd "docker exec zitazi-nginx nginx -s reload"