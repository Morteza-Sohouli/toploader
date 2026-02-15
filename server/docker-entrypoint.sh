#!/bin/bash
set -e

# When ./server is bind-mounted over /var/www/html, vendor is missing on the host.
# Install dependencies at runtime so vendor/ exists before nginx starts.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/ not found; running composer install..."
    composer install --no-dev --classmap-authoritative
fi

# Start PHP-FPM in the background (nginx will proxy to 127.0.0.1:9000)
php-fpm &

# nginx as PID 1 so it receives signals
exec nginx -g "daemon off;"
