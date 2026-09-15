# What's In My Bar

What's In My Bar is an English-language social cocktail recipe application.
The monorepo contains a Symfony/API Platform backend, a Nuxt frontend, and
Docker infrastructure for local development and a future single-VPS
deployment.

## Repository

```text
apps/api/       Symfony 8.1, API Platform, Doctrine
apps/web/       Nuxt 4, Vue 3, Tailwind, shadcn-vue primitives
docs/           specifications, API contract, quality, and operations
infra/          Docker and Caddy configuration
compose.yaml    development stack
compose.prod.yaml
                production stack
```

## Development

Start the development stack:

```bash
docker compose up --build
```

Open:

```text
http://localhost:8080
```

Initialize the database and optional development data:

```bash
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec api php bin/console app:seed:dev
```

See [Docker development](docs/docker-dev.md) for service URLs, volumes, and
common commands.

## Quality

Backend:

```bash
docker compose run --rm api composer check
```

Frontend:

```bash
make check-web
```

GitHub Actions runs path-filtered checks on pull requests. On every push to
`main`, `docker.yml` reuses the backend, frontend and container CI workflows;
all three must pass on that commit before image publication.

## AI-assisted development

Repository-specific guidance for coding agents is defined in:

- [`AGENTS.md`](AGENTS.md) for monorepo-wide instructions;
- [`apps/api/AGENTS.md`](apps/api/AGENTS.md) for backend conventions;
- [`apps/web/AGENTS.md`](apps/web/AGENTS.md) for frontend conventions.

Common development and validation commands are exposed through the root
`Makefile`.

The committed `.codex/config.toml` contains project-scoped, non-secret Codex
configuration. Personal preferences, credentials and reusable MCP servers must
remain in the developer's local Codex configuration.

## Documentation

- [Functional and technical specifications](docs/specifications.md)
- [Internal API contract](docs/api.md)
- [Account lifecycle recommendations](docs/account-lifecycle.md)
- [Docker development](docs/docker-dev.md)
- [Docker production deployment](docs/docker-prod.md)
- [Backend quality baseline](docs/backend-quality.md)
- [Frontend quality baseline](docs/frontend-quality.md)

## Production Status

The production setup targets the shared OVH VPS and Caddy proxy, with two
GHCR images and persistent uploads/abuse counters. Like GameSentry,
`docker.yml` validates and builds images, then `deploy.yml` deploys over SSH.
Before any VPS write, deployment skips releases that no longer match `main`. Backups, Docker cleanup and maintenance timers are managed on the VPS.

See [the production runbook](docs/docker-prod.md) for settings, the host backup
example, rollback and restoration. Configure and verify the VPS before enabling
production deployment.
