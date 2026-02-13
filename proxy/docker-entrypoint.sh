#!/bin/sh
# If certs exist, start nginx. Else obtain Let's Encrypt cert for all domains then start nginx.
set -e
SSL_DIR="/etc/nginx/ssl"
CERT="$SSL_DIR/cert.pem"
KEY="$SSL_DIR/key.pem"

if [ -f "$CERT" ] && [ -f "$KEY" ]; then
  exec nginx -g "daemon off;"
fi

if [ -z "$SSL_DOMAINS" ] || [ -z "$SSL_EMAIL" ]; then
  echo "No SSL certificates found at $SSL_DIR."
  echo "Set SSL_DOMAINS (comma-separated) and SSL_EMAIL in .env (domains must point to this server), then run: docker compose up -d"
  echo "Ensure port 80 is free so Let's Encrypt can verify your domains."
  exit 1
fi

# Convert comma-separated SSL_DOMAINS to "-d domain1 -d domain2 ..."
DOMAIN_FLAGS=""
OLD_IFS="$IFS"
IFS=','
for d in $SSL_DOMAINS; do
  DOMAIN_FLAGS="$DOMAIN_FLAGS -d $d"
done
IFS="$OLD_IFS"

FIRST_DOMAIN=$(echo "$SSL_DOMAINS" | cut -d',' -f1)

echo "Obtaining Let's Encrypt certificate for $SSL_DOMAINS ..."
certbot certonly --standalone $DOMAIN_FLAGS --email "$SSL_EMAIL" \
  --agree-tos --non-interactive --preferred-challenges http

echo "Copying certificates to $SSL_DIR ..."
cp "/etc/letsencrypt/live/$FIRST_DOMAIN/fullchain.pem" "$CERT"
cp "/etc/letsencrypt/live/$FIRST_DOMAIN/privkey.pem" "$KEY"

echo "Starting nginx..."
exec nginx -g "daemon off;"
