# Frontend Quality Baseline

The Nuxt frontend quality baseline lives in `apps/web`.

## Tools

- ESLint with the Nuxt ESLint module.
- Nuxt typecheck through `vue-tsc`.
- Vitest for unit/component tests.
- Playwright for browser smoke tests.
- Nuxt build validation.

## Local Commands

Run commands from the web container:

```bash
docker compose run --rm web pnpm check
```

`pnpm check` installs the Chromium browser needed by Playwright inside the same disposable Docker container before running smoke tests.

Individual checks:

```bash
docker compose run --rm web pnpm lint
docker compose run --rm web pnpm typecheck
docker compose run --rm web pnpm test:unit
docker compose run --rm web pnpm build
docker compose run --rm web pnpm test:e2e
```

Playwright requires browser binaries. In disposable Docker containers, use:

```bash
docker compose run --rm web pnpm test:e2e:install
```

## CI

GitHub Actions runs `.github/workflows/frontend.yml` on pull requests and pushes to `main` when frontend files change.

The CI job runs:

```text
pnpm install --frozen-lockfile
ESLint
Nuxt typecheck
Vitest
Nuxt build
Playwright Chromium smoke tests
```
