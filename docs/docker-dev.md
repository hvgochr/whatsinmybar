# Docker Development Environment

The local development stack uses Docker Compose with:

- Caddy as the local reverse proxy;
- Symfony/API Platform running with FrankenPHP;
- Nuxt running in development mode;
- PostgreSQL 16.

## Services

```text
caddy     http://localhost:8080
web       http://localhost:3000
api       http://localhost:8000/api
postgres 127.0.0.1:5432
```

The recommended entrypoint during development is Caddy:

```text
http://localhost:8080
```

Caddy forwards:

- `/api`, `/uploads`, Symfony profiler routes, and Symfony assets to the API container;
- every other request to the Nuxt container.

## Start The Stack

```bash
docker compose up --build
```

Run in detached mode:

```bash
docker compose up --build -d
```

Stop the stack:

```bash
docker compose down
```

Stop the stack and remove persisted development data:

```bash
docker compose down -v
```

## Backend Commands

Run Symfony commands from the API container:

```bash
docker compose exec api php bin/console about
docker compose exec api php bin/console doctrine:database:create --if-not-exists
docker compose exec api php bin/console doctrine:migrations:migrate
```

Composer dependencies are installed automatically when the API container starts and are stored in the `api_vendor` Docker volume.

Seed local development data:

```bash
docker compose exec api php bin/console app:seed:dev
```

The seed adds ordered preparation steps to the published Negroni and Lime Soda,
including missing positions in previously loaded seed data. Rerunning it does
not duplicate steps, replace existing instructions or reset moderation status.

Seeded accounts use the password `very-secure-password`.

Development Compose uses the production login, registration and authenticated
write ceilings. The documented real-stack acceptance command supplies a
dedicated `APP_SECRET` so repeated runs get an isolated counter namespace while
the developer's normal persistent counters remain untouched.

The development seed is not an administrator bootstrap mechanism. To create a
real local administrator without placing a password in shell history, use a
temporary environment variable (or omit it and use the hidden interactive
prompt):

```bash
read -rs APP_ADMIN_PASSWORD
export APP_ADMIN_PASSWORD
docker compose exec -e APP_ADMIN_PASSWORD api php bin/console app:admin:bootstrap \
  --email=owner@example.com --username=owner --birth-date=1990-01-01
unset APP_ADMIN_PASSWORD
```

The command is idempotent for the exact email/username pair. It creates a new
account only when both identifiers are free, promotes an existing exact match
without changing its password, and refuses deleted or colliding accounts.

Uploaded files are stored in the `api_uploads` Docker volume mounted at:

```text
/app/public/uploads
```

## Frontend Commands

Nuxt reserves a fixed IP for trusted SSR requests. `make` frontend checks use
the separate `172.30.71.4` address so the development server can keep running.
For a manual disposable frontend command, prefix it with
`WEB_CONTAINER_IP=172.30.71.4`, for example:

```bash
WEB_CONTAINER_IP=172.30.71.4 docker compose run --rm web pnpm test:unit
```

Run disposable frontend commands sequentially. The check container is not a
trusted SSR proxy. Use `docker compose exec web` for real-stack session tests.
When upgrading an existing development network to the reserved subnet, run
`docker compose down` followed by `docker compose up -d` without `-v`.

Run Nuxt/pnpm commands from the web container:

```bash
docker compose exec web pnpm dev
docker compose exec web pnpm build
```

Node dependencies are installed automatically when the web container starts. Installed modules are stored in the `web_node_modules` Docker volume, and the pnpm store is stored in the `web_pnpm_store` Docker volume.

## Database

Development database settings:

```text
database: whatsinmybar
user: app
password: app
host from host machine: 127.0.0.1
port from host machine: 5432
host from containers: postgres
```

Symfony uses this container URL in Compose:

```text
postgresql://app:app@postgres:5432/whatsinmybar?serverVersion=16&charset=utf8
```

## Notes

- The committed Symfony `.env` keeps a localhost database URL for non-Docker usage.
- Docker overrides `DATABASE_URL` through Compose.
- Development disables only the refresh cookie's `Secure` attribute so the
  documented HTTP localhost entrypoint works; production always enables it.
- This stack is intentionally development-oriented.
- Production uses `compose.prod.yaml`, optimized image targets, no source bind mounts, and untracked secrets.
- See [`docs/docker-prod.md`](docker-prod.md) for the production build, migration, deployment, backup, and VPS checklist.
