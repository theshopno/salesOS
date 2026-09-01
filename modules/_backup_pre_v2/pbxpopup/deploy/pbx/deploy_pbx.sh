#!/bin/bash
# Deploys the pbxpopup bridge (CRM <-> eCare PBX) onto the PBX box itself.
# Additive only: installs new packages and adds new, isolated files/services.
# Does NOT touch extensions.conf/pjsip.conf (under active concurrent editing).
set -euo pipefail

echo "== installing packages =="
apt-get update -qq
apt-get install -y -qq ffmpeg inotify-tools php8.4-fpm > /dev/null

echo "== php-fpm pool: run as asterisk so it can read recordings/CDR + write cache =="
POOL=/etc/php/8.4/fpm/pool.d/www.conf
sed -i 's/^user = .*/user = asterisk/' "$POOL"
sed -i 's/^group = .*/group = asterisk/' "$POOL"
sed -i 's/^listen.owner = .*/listen.owner = asterisk/' "$POOL"
sed -i 's/^listen.group = .*/listen.group = asterisk/' "$POOL"
systemctl restart php8.4-fpm

echo "== bridge webroot =="
mkdir -p /var/www/bridge
cp /root/pbxpopup_build/bridge.php /var/www/bridge/bridge.php
chown -R asterisk:asterisk /var/www/bridge

echo "== mp3 cache dir =="
mkdir -p /var/spool/asterisk/mp3_cache
chown -R asterisk:asterisk /var/spool/asterisk/mp3_cache

echo "== cdr csv read access =="
chmod o+rx /var/log/asterisk 2>/dev/null || true
chmod o+r /var/log/asterisk/cdr-csv/Master.csv 2>/dev/null || true
# belt-and-suspenders: asterisk group already owns cdr-csv dir in stock configs,
# but grant explicitly in case ownership differs.
chgrp -R asterisk /var/log/asterisk/cdr-csv 2>/dev/null || true
chmod g+rx /var/log/asterisk/cdr-csv 2>/dev/null || true
chmod g+r /var/log/asterisk/cdr-csv/Master.csv 2>/dev/null || true

echo "== watcher script + service =="
cp /root/pbxpopup_build/mp3_cache_watcher.sh /usr/local/bin/mp3_cache_watcher.sh
chmod +x /usr/local/bin/mp3_cache_watcher.sh
cp /root/pbxpopup_build/pbx-mp3-cache.service /etc/systemd/system/pbx-mp3-cache.service
systemctl daemon-reload
systemctl enable --now pbx-mp3-cache.service

echo "== cache cleanup cron =="
cp /root/pbxpopup_build/cleanup_mp3_cache.sh /usr/local/bin/cleanup_mp3_cache.sh
chmod +x /usr/local/bin/cleanup_mp3_cache.sh
cp /root/pbxpopup_build/mp3-cache-cleanup.cron /etc/cron.d/mp3-cache-cleanup
chmod 644 /etc/cron.d/mp3-cache-cleanup

echo "== caddy site =="
if ! grep -q "ecare-pbx.uddoktayon.com" /etc/caddy/Caddyfile; then
  cat >> /etc/caddy/Caddyfile <<'CADDYEOF'

ecare-pbx.uddoktayon.com {
	root * /var/www/bridge
	php_fastcgi unix//run/php/php8.4-fpm.sock
	file_server
}
CADDYEOF
fi
systemctl reload caddy || systemctl restart caddy

echo "== firewall =="
apt-get install -y -qq ufw > /dev/null
ufw allow 22/tcp    comment 'admin ssh (LAN/Tailscale only reaches this - router does not forward 22)'
ufw allow 80/tcp    comment 'caddy ACME http-01 challenge'
ufw allow 443/tcp   comment 'pbxpopup bridge (HTTPS)'
ufw allow 5060/udp  comment 'SIP trunk + extensions'
ufw allow 10000:20000/udp comment 'RTP media'
ufw --force enable

echo "== done =="
systemctl status pbx-mp3-cache.service --no-pager -l | head -10
systemctl status caddy --no-pager -l | head -6
ufw status
