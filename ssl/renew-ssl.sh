#!/usr/bin/env bash
# Copy existing Let's Encrypt certs into ssl/ and reload nginx.
# Use after "certbot renew" (e.g. in a cron job), or to install certs for a domain
# that was renewed elsewhere.
#
# Usage:
#   ./ssl/renew-ssl.sh yourdomain.com
#
# Example cron (renew daily, then copy and reload):
#   0 3 * * * certbot renew --quiet && /path/to/topload/ssl/renew-ssl.sh yourdomain.com

set -e

if [ -z "$1" ]; then
  echo "Usage: $0 DOMAIN"
  echo "  DOMAIN  e.g. app.example.com (must match /etc/letsencrypt/live/DOMAIN)"
  exit 1
fi

DOMAIN="$1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SSL_DIR="$SCRIPT_DIR"
LIVE_DIR="/etc/letsencrypt/live/$DOMAIN"

if [ ! -d "$LIVE_DIR" ]; then
  echo "Error: $LIVE_DIR not found."
  exit 1
fi

sudo cp "$LIVE_DIR/fullchain.pem" "$SSL_DIR/cert.pem"
sudo cp "$LIVE_DIR/privkey.pem" "$SSL_DIR/key.pem"
sudo chown "$(whoami)" "$SSL_DIR/cert.pem" "$SSL_DIR/key.pem"
sudo chmod 644 "$SSL_DIR/cert.pem"
sudo chmod 600 "$SSL_DIR/key.pem"

cd "$PROJECT_ROOT"
if docker compose ps proxy 2>/dev/null | grep -q Up; then
  echo "Reloading nginx ..."
  docker compose exec proxy nginx -s reload
else
  echo "Proxy not running; certs updated. Start with: docker compose up -d proxy"
fi
