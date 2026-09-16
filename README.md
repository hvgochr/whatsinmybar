# What's In My Bar

What's In My Bar is an English-language social cocktail application for
discovering, publishing, saving, discussing, and moderating recipes. It is a
Docker-first monorepo built as a production-oriented personal project rather
than a UI-only demonstration.

The public deployment endpoint is provisioned but still returns a deployment
pending page. A live-demo link will be added only after the real production
acceptance pass succeeds. The repository currently contains no representative
launch screenshots; fixture or placeholder captures are deliberately not
presented as production images.

## Product highlights

- server-rendered public recipe, category, and profile pages with canonical,
  OpenGraph, robots, and sitemap handling;
- account registration and short-lived JWT access, with a rotating 30-day
  refresh token in a host-only `HttpOnly` cookie;
- anonymous, minor, adult, owner, and administrator authorization paths, with
  alcohol visibility enforced by the API on collections and individual items;
- transactional aggregate recipe editing, image validation/re-encoding,
  publication and archive workflows;
- idempotent favorites, paginated threaded comments, reports, and moderation;
- PostgreSQL-backed data, persistent local uploads, abuse counters, backups,
  and a single-VPS deployment model.

Long-lived authentication credentials are never stored in `localStorage`. The
access token stays in Nuxt memory; only the non-sensitive light/dark theme
preference uses browser local storage. The refresh cookie uses `Path=/` so Nuxt
can restore the viewer during SSR; the API consumes it only for refresh/logout
and replaces it after a successful login.

## Architecture

```text
apps/api/          PHP 8.4, Symfony 8.1, API Platform 4.3, Doctrine ORM
apps/web/          Node.js 22, Nuxt 4, Vue 3, TypeScript, Tailwind CSS
docs/              product, API, security, quality, and operations guides
infra/             FrankenPHP images and Caddy routing
compose.yaml       local development stack
compose.prod.yaml  production application stack for the shared VPS proxy
```

Caddy is the single browser entrypoint. In development it is part of the local
Compose project. In production, API and Nuxt attach to an existing shared Caddy
network, while PostgreSQL and the Nuxt-to-API SSR path stay on private networks.
Production Compose publishes no application ports.

The backend remains the authorization boundary. Nuxt middleware and hidden UI
controls improve navigation but do not grant access. Recipe images are served
through a protected API route that rechecks the current recipe permissions;
avatars remain public.

## Run locally

Requirements: Docker Engine with Docker Compose v2 and GNU Make. Project PHP,
Composer, Node.js, pnpm, and PostgreSQL dependencies run in containers.

```bash
make up
make seed
```

Open <http://localhost:8080>. The idempotent development seed creates sample
accounts and zero-proof/alcoholic recipes; see
[`docs/docker-dev.md`](docs/docker-dev.md) for the development-only credentials
and service URLs.

Useful commands:

| Command | Purpose |
| --- | --- |
| `make up` / `make down` | Start or stop the development stack without deleting volumes |
| `make logs` / `make ps` | Inspect the local stack |
| `make seed` | Migrate and load idempotent development fixtures |
| `make check-api` | Composer validation/audit, style, PHPStan, PHPUnit, migrations, schema |
| `make check-web` | ESLint, typecheck, Vitest, Nuxt build, Playwright Chromium smoke tests |
| `make check` | Run both application baselines |
| `make check-containers` | Validate and build production images and routing helpers |
| `make check-integration` | Seed and run the real-stack release journey with isolated quotas |

The real-stack session and release journey is intentionally separate from the
fixture browser suite because it uses the running development database:

```bash
make check-integration
```

It exercises real registration/login/refresh/logout and SSR restoration,
concurrent refreshes, age-based visibility, authoring, upload, publication,
favorites, comments, moderation, and collections spanning multiple API pages.
It adds uniquely named development fixtures and never targets production data.

## Quality and delivery

Every pull request runs the complete backend, frontend, and production-container
validation graph. The stable `Release gate` job succeeds only when all three
jobs pass. A push to `main` can publish immutable SHA-tagged API and web images
to GHCR only after that gate. The container job migrates a disposable PostgreSQL
database, starts both production images, exercises shared-proxy routing, and
recreates stateful containers to verify database, upload, and abuse-counter
persistence.

The deployment workflow is implemented but the actual VPS, shared proxy,
off-site backup destination, monitoring, and production environment approvals
remain operator-managed. See the
[`production runbook`](docs/docker-prod.md) before enabling deployment.

## Documentation

- [Functional and technical specifications](docs/specifications.md)
- [Internal API contract](docs/api.md)
- [Docker development](docs/docker-dev.md)
- [Production deployment, backup, restore, and rollback](docs/docker-prod.md)
- [Abuse protection and proxy trust](docs/abuse-protection.md)
- [Upload validation and protected delivery](docs/uploads.md)
- [Backend quality baseline](docs/backend-quality.md)
- [Frontend quality and real-stack tests](docs/frontend-quality.md)
- [Public content readiness](docs/public-content.md)
- [Deferred account lifecycle features](docs/account-lifecycle.md)

## License

Released under the [MIT License](LICENSE).
