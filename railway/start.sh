#!/bin/sh
set -eu

PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Wait for MySQL, install on the first boot, write the config, apply schema upgrades.
php /opt/osticket/railway/setup.php

# osTicket's scheduled tasks (mail fetching, overdue tickets, cleanup) every 5 minutes.
(
  while :; do
    sleep 300
    runuser -u www-data -- php /var/www/html/api/cron.php >/dev/null 2>&1 || echo "[osticket-cron] cron.php failed" >&2
  done
) &

exec apache2-foreground
