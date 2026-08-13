# Frontend guidance

These instructions apply to `apps/web`.

## Stack

The frontend uses:

- Node.js 22;
- pnpm 11;
- Nuxt 4;
- Vue 3;
- TypeScript;
- Tailwind CSS 4;
- shadcn-nuxt and Reka UI primitives;
- Vitest;
- Playwright.

Use `pnpm`, not npm or Yarn. Preserve `pnpm-lock.yaml` unless dependencies
intentionally change.

Use the official Nuxt documentation and the configured Nuxt MCP server when
framework behavior is uncertain.

## Structure and conventions

Application source lives under `app/`.

Before introducing a new pattern, inspect the existing:

- pages and layouts;
- components and UI primitives;
- composables;
- middleware;
- API client and authentication handling;
- unit and E2E tests.

Follow established component naming and directory organization.

The shadcn-nuxt component prefix is `Ui`. Reuse existing UI primitives before
creating a parallel component abstraction.

Keep user-facing application content in English for V1.

## Nuxt and SSR

- Preserve server-side rendering for public discovery pages.
- Do not access browser-only globals during server rendering.
- Keep server-only values outside `runtimeConfig.public`.
- Use public runtime configuration only for values safe to expose to the client.
- Keep API calls behind the existing client or composable abstraction.
- Handle loading, empty, restricted and error states explicitly.
- Avoid hydration-dependent rendering differences.
- Add or update SEO metadata for public route changes where relevant.

## Authentication and authorization

- Do not store long-lived authentication tokens in `localStorage`.
- Preserve the existing refresh and error-handling flow.
- Treat route middleware as navigation behavior, not authorization.
- Do not expose restricted recipe data before checking the API response.
- Handle unauthorized, forbidden and alcohol-restricted responses deliberately.
- Never duplicate backend authorization logic as if it were authoritative.

## UI and accessibility

- Prefer existing Tailwind tokens and components.
- Keep forms keyboard accessible.
- Associate labels and validation messages with their fields.
- Preserve visible focus states.
- Give images meaningful alternative text when they convey information.
- Avoid adding a new UI dependency for a component already supported by the
  existing stack.

## Commands

Run frontend commands from the repository root through Docker Compose.

Full validation:

```bash
make check-web
```

Targeted checks:

```bash
make lint-web
make typecheck-web
make test-web
make build-web
make e2e-web
```

Run a specific Vitest file:

```bash
docker compose run --rm web pnpm vitest run tests/unit/path/to/file.spec.ts
```

## Testing expectations

- Use Vitest for composables, utilities and component behavior.
- Use Playwright for critical navigation and user flows.
- Add regression coverage for bug fixes when practical.
- Mock API boundaries intentionally in unit tests.
- Avoid brittle assertions tied only to implementation details.
- Run a Nuxt build when changing SSR behavior, routing, runtime configuration or
  application initialization.
