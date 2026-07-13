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

- `/api`, Symfony profiler routes, and Symfony assets to the API container;
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

## Frontend Commands

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
- This stack is intentionally development-oriented. Production deployment should use a dedicated Compose file with production secrets, optimized images, and no bind mounts.
