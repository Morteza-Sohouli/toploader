#!/usr/bin/env bash
# Obtain Let's Encrypt certificate and install into ssl/ for the nginx proxy.
# Run from project root. Requires: certbot, sudo (for certbot if not run as root).
#
# Usage:
#   ./ssl/setup-ssl.sh yourdomain.com [email@example.com]
#
# Before first run: stop the proxy so port 80 is free for certbot:
#   docker compose stop proxy

set -e

if [ -z "$1" ]; then
  echo "Usage: $0 DOMAIN [EMAIL]"
  echo "  DOMAIN  e.g. app.example.com"
  echo "  EMAIL  optional; used for Let's Encrypt expiry notices"
  exit 1
fi

DOMAIN="$1"
EMAIL="${2:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSL_DIR="$SCRIPT_DIR"
LIVE_DIR="/etc/letsencrypt/live/$DOMAIN"

certbot_extra=()
[ -n "$EMAIL" ] && certbot_extra=(--email "$EMAIL")

echo "Obtaining certificate for $DOMAIN ..."
sudo certbot certonly --standalone -d "$DOMAIN" --agree-tos --non-interactive "${certbot_extra[@]}"

echo "Copying certificates to $SSL_DIR ..."
sudo cp "$LIVE_DIR/fullchain.pem" "$SSL_DIR/cert.pem"
sudo cp "$LIVE_DIR/privkey.pem" "$SSL_DIR/key.pem"
sudo chown "$(whoami)" "$SSL_DIR/cert.pem" "$SSL_DIR/key.pem"
sudo chmod 644 "$SSL_DIR/cert.pem"
sudo chmod 600 "$SSL_DIR/key.pem"

echo "Done. Start or reload the proxy:"
echo "  docker compose up -d proxy"
echo "  # or if proxy is already running:"
echo "  docker compose exec proxy nginx -s reload"
