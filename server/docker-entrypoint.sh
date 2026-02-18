#!/bin/bash
set -e

# When ./server is bind-mounted over /var/www/html, vendor is missing on the host.
# Install dependencies at runtime so vendor/ exists before nginx starts.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/ not found; running composer install..."
    composer install --no-dev
fi

# Regenerate autoload so new classes under src/ are loadable (bind-mount can have newer code than vendor).
composer dump-autoload --no-dev

# Generate nginx internal locations for WP uploads from wp-domains.json (or wp-config.json)
if [ -f /var/www/html/scripts/generate-nginx-internal-wp.php ]; then
    php /var/www/html/scripts/generate-nginx-internal-wp.php || true
fi

# Clean up stale TUS partial uploads (older than 24h) every hour
(
    while true; do
        sleep 3600
        find /data/tus-data -maxdepth 1 -type f -mmin +1440 -delete 2>/dev/null || true
    done
) &

# Start PHP-FPM in the background (nginx will proxy to 127.0.0.1:9000)
php-fpm &

# nginx as PID 1 so it receives signals
exec nginx -g "daemon off;"
