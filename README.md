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
docker compose run --rm web pnpm check
```

GitHub Actions runs the backend and frontend checks independently on relevant
pull requests and pushes to `main`.

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
- [Docker development](docs/docker-dev.md)
- [Docker production deployment](docs/docker-prod.md)
- [Backend quality baseline](docs/backend-quality.md)
- [Frontend quality baseline](docs/frontend-quality.md)

## Production Status

The repository contains locally validated multi-stage production images,
Docker Compose orchestration, Caddy HTTPS routing, healthchecks, persistent
volumes, and a manual deployment runbook.

A public production launch still requires a VPS and domain, external backups,
monitoring, security hardening, a decision or implementation for S3-compatible
uploads, dynamic sitemap generation, and final refresh-token/logout hardening.
