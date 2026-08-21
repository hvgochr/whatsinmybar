# Docker Production Deployment

The production stack targets a single Linux VPS running Docker Compose.
It is intentionally separate from the development stack.

## Architecture

```text
Internet
   |
   v
Caddy :80/:443
   |------------> Nuxt :3000
   |
   `------------> FrankenPHP :8080
                         |
                         v
                    PostgreSQL :5432
```

Only Caddy publishes host ports. PostgreSQL, Nuxt, and the API remain
reachable only through Docker networks.

Production files:

- `compose.prod.yaml`: services, networks, volumes, healthchecks, and restart policies;
- `.env.prod.example`: required environment variable template;
- `infra/caddy/Caddyfile.prod`: automatic HTTPS and reverse proxy routing;
- `infra/docker/api/Dockerfile`: optimized Symfony/FrankenPHP production target;
- `infra/docker/web/Dockerfile`: built Nuxt/Nitro production target.

`.github/workflows/containers.yml` validates the production Compose and
Caddy configuration and builds both production image targets when relevant
infrastructure or dependency manifests change. It does not deploy anything.

## Prerequisites

- Docker Engine with the Compose v2 plugin;
- a domain whose A/AAAA records point to the VPS;
- inbound TCP ports 80 and 443 open;
- inbound UDP port 443 open for HTTP/3;
- enough disk space for images, PostgreSQL, uploads, Caddy data, and backups.

Do not expose PostgreSQL or the internal application ports through the VPS
firewall.

## Environment

Create the untracked production environment file:

```bash
cp .env.prod.example .env.prod
chmod 600 .env.prod
```

Generate independent application secrets:

```bash
openssl rand -hex 32
openssl rand -hex 32
openssl rand -base64 36
```

Use the first two values for `APP_SECRET` and `JWT_SECRET`. Use the third value
for `POSTGRES_PASSWORD`.

`DATABASE_URL` must contain the same PostgreSQL database, user, and password.
URL-encode the password before inserting it into the connection URL.

Set:

```text
APP_DOMAIN=the public hostname without https:// or a path
CORS_ALLOW_ORIGIN=an anchored regular expression for https://APP_DOMAIN
```

The API forces `Secure` on its HttpOnly refresh-token cookie in production.
Keep the browser API URL behind the same public HTTPS origin. Credentialed CORS
is enabled for the configured origin because login, refresh, and logout manage
that cookie; do not broaden `CORS_ALLOW_ORIGIN` to `*`.

Validate interpolation without starting containers:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml config --quiet
```

Never commit `.env.prod`.

## First Deployment

Build immutable application contents into the API and web images:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml build --pull
```

Start PostgreSQL and wait for it to become healthy:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml up -d --wait postgres
```

Run migrations as a one-off API container:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml run --rm api \
  php bin/console doctrine:migrations:migrate --no-interaction
```

Start the complete stack and wait for healthchecks:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml up -d --wait
```

Verify:

```bash
curl --fail --show-error --silent https://YOUR_DOMAIN/healthz
curl --fail --show-error --silent https://YOUR_DOMAIN/
curl --fail --show-error --silent \
  -H 'Accept: application/json' \
  'https://YOUR_DOMAIN/api/categories?pagination=false'
```

The health endpoint proves that public HTTPS reaches Caddy. Container
healthchecks separately cover PostgreSQL readiness, the FrankenPHP listener,
and the built Nuxt server.

## Application Updates

From the new checked-out revision:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml build --pull
docker compose --env-file .env.prod -f compose.prod.yaml up -d --wait postgres
docker compose --env-file .env.prod -f compose.prod.yaml run --rm api \
  php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.prod -f compose.prod.yaml up -d --wait
```

Database migrations must remain backward-compatible with the previous
application image whenever a zero-downtime update is required.

There is no automated deployment pipeline yet. A future registry-based
deployment should build tagged images in CI, pull an immutable tag on the VPS,
run migrations, and then recreate the application services.

## Operations

Inspect service state:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml ps
```

Follow logs:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml logs -f --tail=200
```

Run a Symfony command:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec api \
  php bin/console about
```

Stop containers without deleting production data:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml down
```

Do not use `docker compose down -v` in production. It deletes the named
PostgreSQL, upload, and Caddy volumes.

## Database Backups

Create a compressed logical backup on the VPS:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec -T postgres \
  sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
  | gzip > "whatsinmybar-$(date +%Y%m%d-%H%M%S).sql.gz"
```

Backups stored only on the VPS are not sufficient. Copy them to encrypted
off-site storage, define retention, and regularly test restoration.

Example restore into an empty target database:

```bash
gzip -dc BACKUP.sql.gz \
  | docker compose --env-file .env.prod -f compose.prod.yaml exec -T postgres \
      sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"'
```

Restoration is destructive when applied to a non-empty database. Validate the
target and retain the previous backup before running it.

## Upload Storage

The current API implementation uses local filesystem storage behind upload
interfaces. Production Compose persists `/app/public/uploads` in the
`api_uploads` named volume and routes `/uploads/*` through the API.

The V1 specification targets S3-compatible production storage. That adapter is
not implemented yet. Before public production use, either:

- implement and configure the S3-compatible adapter; or
- explicitly accept local uploads and add independent off-site backups for the
  `api_uploads` volume.

Switching to S3 will also require a migration plan for existing local objects
and a decision about public object URLs or signed delivery.

## VPS Work Still Required

The repository cannot configure or verify these items before a VPS and domain
exist:

- DNS records and real ACME certificate issuance;
- SSH hardening, non-root administration, and firewall policy;
- unattended operating-system security updates;
- off-site PostgreSQL and upload backups;
- monitoring, alerting, disk-space checks, and log retention;
- image registry, immutable release tags, and rollback automation;
- recovery testing after a simulated host failure.
