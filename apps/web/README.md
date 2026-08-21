# whatsinmybar web

Nuxt frontend for the whatsinmybar application.

## Setup

Install dependencies:

```bash
pnpm install
```

Start the development server:

```bash
pnpm dev
```

In Docker development, use the root compose stack and open Caddy:

```bash
docker compose up
```

```text
http://localhost:8080
```

## API Client

The frontend API layer is exposed through:

```ts
const api = useApi()
const auth = useAuth()
```

`useApi()` provides typed endpoint groups for auth, account, recipes, comments, favorites, reports, and admin workflows.

`useAuth()` owns the browser session workflow:

- access token: Nuxt state, kept in memory only
- refresh token: server-managed HttpOnly cookie, never exposed to Nuxt code
- no token is written to `localStorage`
- `401` API responses trigger one refresh attempt, then the original request is replayed
- logout and failed refreshes revoke or clear the server-managed cookie

Runtime API URLs:

```text
NUXT_API_BASE_URL=http://api/api
NUXT_PUBLIC_API_BASE_URL=/api
```

`NUXT_API_BASE_URL` is used when Nuxt runs server-side. `NUXT_PUBLIC_API_BASE_URL` is used by the browser and should usually stay behind the reverse proxy.

## Quality

```bash
pnpm lint
pnpm typecheck
pnpm test:unit
pnpm build
pnpm test:e2e:install
```
