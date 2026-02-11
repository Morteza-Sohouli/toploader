# SSL certificates for HTTPS

The reverse proxy in `proxy/nginx.conf` expects these files in `ssl/`:

- `cert.pem` – certificate (full chain)
- `key.pem` – private key

## Docker Compose (recommended): certbot + cron renewal

Certbot runs in containers. Initial cert is obtained with a one-off service; renewal runs **daily at 03:00** via cron inside the `certbot-renew` container.

### 1. Set domain and email in `.env`

```env
SSL_DOMAIN=yourdomain.com
SSL_EMAIL=you@example.com
```

Also set `VITE_API_BASE_URL=https://yourdomain.com/api` and `UPLOAD_URL=https://yourdomain.com` for the app.

### 2. Get the first certificate (one-off)

Your domain must point to this host. **Stop the proxy** so port 80 is free, then run:

```bash
docker compose stop proxy
docker compose --profile ssl-init run --rm -p 80:80 certbot-init
docker compose up -d
```

This obtains a Let's Encrypt cert and writes `ssl/cert.pem` and `ssl/key.pem`. Then start the full stack (including the renewal cron).

### 3. Normal run (with renewal cron)

```bash
docker compose up -d --build
```

- **proxy** – nginx with HTTPS, serves `/.well-known/acme-challenge/` for renewals.
- **certbot-renew** – runs cron inside the container; every day at 03:00 it runs `certbot renew --webroot`, copies new certs to `ssl/`, and reloads nginx.

No host cron or scripts needed; renewal is fully in Docker.

---

## Alternative: Linux scripts on the host

If you prefer certbot on the host instead of in Docker:

```bash
chmod +x ssl/setup-ssl.sh ssl/renew-ssl.sh
docker compose stop proxy
./ssl/setup-ssl.sh yourdomain.com you@example.com
docker compose up -d proxy
```

For renewal (e.g. in host cron after `certbot renew`): `./ssl/renew-ssl.sh yourdomain.com`.

---

## Other CAs

Put your full chain in `ssl/cert.pem` and the private key in `ssl/key.pem`, then start the proxy. The `certbot-renew` service will only renew Let's Encrypt certs; leave it stopped if you use another CA.

## Using the HTTPS proxy

1. Ensure `ssl/cert.pem` and `ssl/key.pem` exist (Docker certbot-init or manual).
2. In `.env`: `VITE_API_BASE_URL=https://yourdomain.com/api`, `UPLOAD_URL=https://yourdomain.com`.
3. Use the **production** client (no `command` override for the `client` service in `docker-compose.yml`), then:
   ```bash
   docker compose up -d --build
   ```
4. Open **https://yourdomain.com**. HTTP on port 80 redirects to HTTPS.
