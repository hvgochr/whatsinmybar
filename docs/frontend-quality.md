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
make check-web
```

`pnpm check` installs the Chromium browser needed by Playwright inside the same disposable Docker container before running smoke tests. `make` reserves a separate
container IP so the trusted development SSR server can keep running. Manual
`docker compose run --rm web` commands below need the prefix
`WEB_CONTAINER_IP=172.30.71.4`; `docker compose exec web` does not.

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

GitHub Actions calls `.github/workflows/frontend.yml` from the top-level
`docker.yml` validation graph on every pull request and push to `main`.

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

Recipe and avatar rendering includes intrinsic dimensions, appropriate loading
and decoding hints, display-size guidance, and visible fallbacks. Uploaded files
are normalized by the existing backend image pipeline, but the frontend does
not yet provide `srcset` candidates or request viewport-specific variants.
Browsers therefore download the same processed asset at every display size;
this is not complete responsive image delivery.

`/sitemap.xml` is generated from anonymous API requests. It contains static
public discovery routes, public categories, anonymously visible recipes, and
the public profiles referenced by those recipes. It cannot include private,
archived, moderated, or alcohol-restricted recipes that the anonymous API does
not return. Each upstream request has a five-second timeout and retries are
disabled; any upstream failure returns 503 instead of a partial sitemap. Private
routes also emit `noindex, nofollow` metadata and an `X-Robots-Tag` response
header.

## Real session integration

The ordinary Playwright suite uses fixtures for UI/error scenarios; it does not
validate real refresh rotation. Run the additional suite against the running
Docker API, PostgreSQL, Nuxt and Caddy (development data only):

```bash
make check-integration
```

`SESSION_TEST_BASE_URL` overrides the default `http://caddy`. Vite explicitly
allows only that Docker service hostname in addition to its safe defaults. The
target rebuilds/starts and seeds the development stack, gives the test run a
unique abuse-counter namespace, and restores the normal API container even when
Playwright fails. The suite creates one unique `session_*` adult and one unique
minor account in the development database and, when necessary,
published zero-proof `Sitemap Pagination` recipes until the anonymous collection
spans at least two API pages; it does not delete existing data. The sitemap check
then compares the first and last real API pages with `/sitemap.xml` and verifies
that the seeded alcoholic recipe stays absent. The session checks cover six
concurrent refresh requests, real predecessor expiry, concurrent 401 recovery
in shared/independent API clients, three browser tabs plus independent SSR
requests, cache headers, two-user isolation and password-change
notification/revocation, a race between password revocation and refresh, and
explicit logout notification across tabs. A real release journey additionally
covers anonymous/minor/adult visibility, aggregate recipe creation, PNG upload
and protected delivery, publication, idempotent favorites, comment creation,
reporting, and administrator moderation. It waits 11 real seconds to prove
that an old token cannot refresh indefinitely. Chromium is tested;
Firefox/WebKit, production HTTPS cookies, extended offline suspension and
responses delayed beyond the grace window require separate acceptance testing.

Unit tests cover transient/network/rate-limit/timeout classification, effective
cancellation deadlines, late responses, state/cache coordination and the SSR
cookie jar. Fixture E2E tests additionally cover SSR refresh 503 and timeout
fallback while preserving the HttpOnly cookie. Symfony authentication tests
include an actual competing PostgreSQL lock and successful retry after timeout.
