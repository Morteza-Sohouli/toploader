#!/bin/sh
# Run certbot renew (webroot), copy certs to /ssl, reload nginx in proxy container.
set -e
if [ -z "$SSL_DOMAINS" ]; then
  echo "SSL_DOMAINS not set" >&2
  exit 1
fi

# Let's Encrypt names the cert directory after the first domain
FIRST_DOMAIN=$(echo "$SSL_DOMAINS" | cut -d',' -f1)

certbot renew --webroot -w /var/www/certbot --quiet
cp "/etc/letsencrypt/live/$FIRST_DOMAIN/fullchain.pem" /ssl/cert.pem
cp "/etc/letsencrypt/live/$FIRST_DOMAIN/privkey.pem" /ssl/key.pem

# Reload nginx in the proxy container (same compose project)
CONTAINER=$(docker ps -q -f name=proxy | head -1)
if [ -n "$CONTAINER" ]; then
  docker exec "$CONTAINER" nginx -s reload
  echo "Nginx reloaded"
fi
