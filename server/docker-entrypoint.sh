#!/bin/bash
set -e

# When ./server is bind-mounted over /var/www/html, vendor is missing on the host.
# Install dependencies at runtime so vendor/ exists before Apache starts.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/ not found; running composer install..."
    composer install --no-dev --classmap-authoritative
fi

exec apache2-foreground
