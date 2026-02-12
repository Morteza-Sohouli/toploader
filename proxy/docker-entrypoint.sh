#!/bin/sh
# If certs exist, start nginx. Else obtain Let's Encrypt cert then start nginx.
set -e
SSL_DIR="/etc/nginx/ssl"
CERT="$SSL_DIR/cert.pem"
KEY="$SSL_DIR/key.pem"

if [ -f "$CERT" ] && [ -f "$KEY" ]; then
  exec nginx -g "daemon off;"
fi

if [ -z "$SSL_DOMAIN" ] || [ -z "$SSL_EMAIL" ]; then
  echo "No SSL certificates found at $SSL_DIR."
  echo "Set SSL_DOMAIN and SSL_EMAIL in .env (domain must point to this server), then run: docker compose up -d"
  echo "Ensure port 80 is free so Let's Encrypt can verify your domain."
  exit 1
fi

echo "Obtaining Let's Encrypt certificate for $SSL_DOMAIN ..."
certbot certonly --standalone -d "$SSL_DOMAIN" --email "$SSL_EMAIL" \
  --agree-tos --non-interactive --preferred-challenges http

echo "Copying certificates to $SSL_DIR ..."
cp "/etc/letsencrypt/live/$SSL_DOMAIN/fullchain.pem" "$CERT"
cp "/etc/letsencrypt/live/$SSL_DOMAIN/privkey.pem" "$KEY"

echo "Starting nginx..."
exec nginx -g "daemon off;"
