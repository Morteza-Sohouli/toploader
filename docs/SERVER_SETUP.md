# Deploy Topload on a new Linux server

This guide describes the Docker Compose deployment in this repository. Run commands from the repository root. Replace example domains, paths, and passwords with your own values.

## 1. Prepare the server

- Install Docker Engine with the Docker Compose plugin and Git. Confirm `docker compose version` works.
- Point the public application hostname (for example, `uploads.example.com`) at this server. Point any WordPress download hostnames here only if this proxy is meant to serve them. Every hostname placed in `SSL_DOMAINS` must resolve to this server for the initial Let's Encrypt HTTP challenge.
- Allow inbound TCP ports **80** and **443**. The proxy claims both ports, so stop any existing web server using them.
- Provision enough disk for MySQL, `data/` (temporary resumable uploads and sessions), `server/uploads/` (finished app uploads), and the mounted WordPress uploads. A large upload may temporarily occupy both `data/` and `server/uploads/`.
- Clone the repository on the server and `cd` into it. Copy the existing database and upload files as well if this is a migration; cloning the code alone does not migrate data.

## 2. Know which configuration file does what

| File | What to configure | Takes effect |
| --- | --- | --- |
| `.env` at repository root | Compose variables: database credentials, app URL, SSL names, upload size, signing secret, and host WordPress paths | Recreate affected containers with `docker compose up -d`; rebuild only if another file changed |
| `docker-compose.yml` | Services, host ports, bind mounts, and which `.env` variables enter containers | `docker compose up -d` |
| `client/src/api/config.ts` | `API_BASE_URL`, the browser's public API URL | Rebuild the client image: `docker compose up -d --build client` |
| `server/config/wp-domains.json` | WordPress hostname to **container** upload directory mapping | Restart `server` so nginx locations are regenerated |
| `proxy/nginx.conf` | HTTPS routing to the client, API, files, and tusd | `docker compose exec proxy nginx -t` then `docker compose exec proxy nginx -s reload` |
| `server/nginx-default.conf` | nginx rules inside the PHP server container | Rebuild and recreate `server` |
| `server/Dockerfile`, `server/config/performance.ini` | Active PHP upload and runtime limits | Rebuild and recreate `server` |
| `ssl/cert.pem`, `ssl/key.pem` | Certificate and private key used by the proxy | Reload `proxy` after replacement |

**WordPress map duplication:** `config/wp-domains.json` at the repository root is a separate tracked file, but the current Compose setup does not mount it into the server. The app and the server startup script use `server/config/wp-domains.json`. Edit that server file for this deployment. Do not edit generated `server/nginx-internal-wp.conf` by hand.

`server/config/database.php` already reads the database environment variables passed by Compose; normally it needs no edit. `server/config/php-upload-config.ini` is not copied or mounted by the current Dockerfile, so changing it alone does not alter the running PHP limits.

## 3. Create `.env`

Use `.env.exmaple` as a list of older settings, then create a real `.env` in the repository root. The example omits `MAX_FILE_SIZE`, which Compose requires. For example:

```dotenv
MYSQL_ROOT_PASSWORD=replace-with-a-strong-root-password
MYSQL_DATABASE=topload
MYSQL_USER=topload
MYSQL_PASSWORD=replace-with-a-strong-app-password

# Public origin, without /api or a trailing slash.
UPLOAD_URL=https://uploads.example.com
# Integer byte count; this is the tusd limit. 30 GiB is shown here.
MAX_FILE_SIZE=32212254720
SECURE_LINK_SECRET=replace-with-at-least-32-random-characters
APP_DEBUG=false

# Comma-separated names on the certificate. No spaces.
SSL_DOMAINS=uploads.example.com
SSL_EMAIL=admin@example.com

# Absolute paths on the Linux host, not paths inside a container.
WP_UPLOADS_PATH_1=/srv/wordpress/site1/wp-content/uploads
WP_UPLOADS_PATH_2=/srv/wordpress/site2/wp-content/uploads
WP_UPLOADS_PATH_3=/srv/wordpress/site3/wp-content/uploads
# Optional: if omitted, domain4 mounts the same host path as domain3.
WP_UPLOADS_PATH_4=/srv/wordpress/site4/wp-content/uploads
```

Generate the signing secret with `openssl rand -hex 32`. Keep the existing `SECURE_LINK_SECRET` when migrating an installation: changing it invalidates existing signed download links. Keep `.env` private and out of Git. `UPLOAD_URL` must be the public HTTPS origin used for native upload links and allowed browser origin. The maximum file size is also limited by `server/src/Controllers/FileController.php` (30 GiB) and the PHP settings in `server/Dockerfile`; changing only `MAX_FILE_SIZE` will not raise those limits.

## 4. Align the WordPress mounts and domain map

The four WordPress mounts in `docker-compose.yml` have fixed container targets `/var/www/wp-uploads/domain1` through `domain4`. Set their host source paths with `WP_UPLOADS_PATH_1` through `_4` in `.env`. Make sure the host directories already exist and are readable by the server container; a typo in a bind mount can otherwise create an empty directory.

Edit `server/config/wp-domains.json` so each **download request hostname** points to the matching **container target**, for example:

```json
{
  "files1.example.com": "/var/www/wp-uploads/domain1",
  "files2.example.com": "/var/www/wp-uploads/domain2",
  "files3.example.com": "/var/www/wp-uploads/domain3",
  "files4.example.com": "/var/www/wp-uploads/domain4"
}
```

If two hostnames share storage, their map values may point to the same container directory. `WP_UPLOADS_PATH_4` defaults to the `_3` host path when omitted, although its container target remains `domain4`. Ensure each hostname, `.env` mount, and JSON path agree. WordPress upload paths are served through `/wp-content/uploads/...` or `/uploads/...` on the mapped hostname.

If WordPress integration is unused, the current Compose file still requires `WP_UPLOADS_PATH_1` through `_3`. Set them to existing, dedicated directories or remove the unused mounts and map entries from Compose and the JSON file.

## 5. Set the frontend URL and restore data

Set `API_BASE_URL` in `client/src/api/config.ts` to `https://uploads.example.com/api`. The client is built by Vite into static files, so the root `.env` does **not** change this URL at runtime. The app derives its tus endpoint from the same URL.

Create `data/` and `server/uploads/` before starting. They must be writable by container UID **33** (`www-data`); the server entrypoint fixes ownership of `data/tus-data` and `data/sessions`, but not the whole `server/uploads` tree. Keep the WordPress source directories readable by UID 33. On a Linux host, for example:

```bash
mkdir -p data/tus-data data/sessions server/uploads
sudo chown -R 33:33 data server/uploads
chmod 600 .env
```

For a migration, restore the previous `server/uploads/` contents, any required `data/` contents, and the MySQL database. Reapply UID 33 ownership to restored writable files. On server startup, `server/scripts/initialize-database.php` waits for MySQL and creates any missing application tables. It does not alter existing tables or migrate existing data, so restore a known-good database dump when moving an installation.

To import a dump after MySQL is healthy, use a shell on the server (the file path below is on the host):

```bash
docker compose up -d mysql
docker compose ps mysql
docker compose exec -T mysql sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < backup.sql
```

## 6. Start and verify

Check the Compose file, then build and start the stack:

```bash
docker compose config --quiet
docker compose up -d --build
docker compose ps
docker compose logs --tail=100 proxy server tusd mysql certbot-renew
```

On a first start without `ssl/cert.pem` and `ssl/key.pem`, the proxy container requests a Let's Encrypt certificate for `SSL_DOMAINS`. DNS and port 80 must work at this point. The `certbot-renew` container schedules renewal and reloads the proxy. If you supply your own certificate, place its full chain in `ssl/cert.pem` and its private key in `ssl/key.pem` before starting; keep `certbot-renew` stopped because it only manages Let's Encrypt certificates.

Changing `SSL_DOMAINS` after a certificate already exists does not automatically request a replacement on proxy restart. Reissue a certificate covering the new names before directing those names to the HTTPS proxy.

Open `https://uploads.example.com/` and check the app. The API root should answer at `https://uploads.example.com/api/`. Test login and a small upload, then test a mapped WordPress file URL if applicable. For troubleshooting, use `docker compose logs --tail=100 <service>` and check that the frontend URL, mount paths, DNS, and certificate names agree.

## 7. Changes and backups

- After changing `.env` or host mounts: `docker compose up -d`.
- After changing the frontend API URL: `docker compose up -d --build client`.
- After changing `server/config/wp-domains.json`: `docker compose restart server`; inspect `docker compose logs server` for the generated nginx locations.
- After changing PHP or server nginx config: `docker compose up -d --build server`.
- After changing `proxy/nginx.conf`: `docker compose exec proxy nginx -t` followed by `docker compose exec proxy nginx -s reload`.

Back up the MySQL named volume (`mysql_data`), `server/uploads/`, the WordPress upload sources, `.env`, and `ssl/`. Preserve `SECURE_LINK_SECRET` during restores. Do not use `docker compose down -v` during routine updates; it deletes the named database and Let's Encrypt volumes.

## Current deployment cautions

The Compose file publishes MySQL (`3306`), phpMyAdmin (`8081`), the API server (`3000`), and the frontend (`8080`) on all host interfaces as well as proxy ports 80/443. Restrict these ports with a firewall or bind them to localhost in Compose before exposing a production server. Both `server` and `certbot-renew` mount the Docker socket, which gives those containers powerful access to the host; review that design before using this deployment with untrusted users. Image tags `mysql` and `tusproject/tusd:latest` are unpinned and may change on rebuild.
