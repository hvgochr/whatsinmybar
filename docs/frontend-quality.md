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

## Frontend route map

The Nuxt application uses two visual shells. Public discovery, authentication,
profiles and personal content use the default layout. Administration uses a
separate responsive sidebar layout and remains protected by both frontend route
middleware and backend authorization.

| Area | Routes |
| --- | --- |
| Discovery | `/`, `/recipes`, `/recipes/:slug`, `/categories`, `/categories/:slug` |
| Authentication | `/login`, `/register`, `/logout` |
| Profiles and personal content | `/users/:username`, `/settings`; owner-only recipes and favorites live within the authenticated user's profile |
| Recipe authoring | `/recipes/new`, `/recipes/:slug/edit` |
| Administration | `/admin`, `/admin/users`, `/admin/recipes`, `/admin/ingredients`, `/admin/ingredients/new`, `/admin/ingredients/:slug/edit`, `/admin/categories`, `/admin/categories/new`, `/admin/categories/:slug/edit`, `/admin/comments`, `/admin/reports` |

Public recipe pages are server rendered. The API remains the authorization and
alcohol-visibility boundary; navigation guards and hidden controls are only user
experience affordances. Because the API does not expose a standalone global
comment collection, `/admin/comments` truthfully presents comments referenced by
the moderation-report feed instead of inventing an incomplete comment index.

The application design system uses shadcn-nuxt primitives, Hugeicons and
monochrome zinc-compatible semantic tokens. Theme preference supports light,
dark and system modes without changing the color of recipe photography.
