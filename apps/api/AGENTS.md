# Backend guidance

These instructions apply to `apps/api`.

## Stack and sources of truth

The backend uses:

- PHP 8.4;
- Symfony 8.1;
- API Platform 4.3;
- Doctrine ORM 3.6;
- PostgreSQL 16;
- PHPUnit 13;
- PHPStan;
- PHP-CS-Fixer with the Symfony ruleset.

Use the installed versions and lockfile as the dependency source of truth.

For framework behavior, prefer current official documentation over patterns from
model training:

- Symfony 8.1: <https://symfony.com/doc/8.1/>
- API Platform: <https://api-platform.com/docs/>
- API Platform LLM index: <https://api-platform.com/docs/llms.txt>

Avoid legacy API Platform patterns when a current equivalent exists. In
particular, use `QueryParameter` for new collection parameters rather than the
legacy `#[ApiFilter]` pattern.

## Existing architecture

Follow the existing boundaries:

- Doctrine entities and enums contain persistent domain state.
- API Platform metadata exposes resource operations.
- Doctrine ORM extensions implement recipe search and visibility constraints.
- State processors handle API Platform write behavior where already established.
- Services contain reusable business operations.
- Symfony controllers expose explicit authentication, profile, workflow,
  moderation, upload, favorite and comment endpoints.
- Voters and security services enforce resource-level access.
- Tests are grouped by functional domain under `tests/`.

Do not replace an established controller, processor or extension pattern as an
unrelated refactor.

The public HTTP contract is documented in `docs/api.md`. Update it when routes,
payloads, query parameters, status codes or error shapes change.

## Domain invariants

Preserve these rules:

- Alcohol visibility is enforced in collection queries and item access.
- Anonymous users and authenticated users under 18 cannot access alcoholic
  recipes.
- An administrator override may supersede computed recipe alcohol status.
- Recipe ownership and admin permissions are enforced server-side.
- Favorite addition and removal are idempotent.
- A comment reply must belong to the same recipe as its parent.
- Soft-deleted or moderated content must not leak its original content publicly.
- Upload endpoints validate file type and size and use the storage abstraction.

Do not rely on frontend behavior to preserve any of these invariants.

## API Platform conventions

- Keep resource operations, normalization contexts and denormalization contexts
  explicit.
- Use state providers, processors or Doctrine extensions when they fit the
  established resource lifecycle.
- Use explicit controllers for workflow-style endpoints when that matches the
  current architecture.
- Avoid exposing entity fields accidentally through broad serialization groups.
- Keep collection filtering compatible with visibility restrictions.
- Preserve the documented API error format.
- Add functional tests for operations, authorization and serialization changes.

## Doctrine conventions

- Use attributes for entity mapping.
- Add a new migration for intentional schema changes.
- Do not edit existing committed migrations unless the task explicitly concerns
  an unreleased migration.
- Keep entity mapping, migrations and the resulting PostgreSQL schema aligned.
- Preserve indexes and unique constraints required by documented invariants.
- Consider query counts and N+1 behavior when changing relations or
  serialization.

## Commands

Run backend commands from the repository root through Docker Compose.

Full validation:

```bash
make check-api
```

Targeted checks:

```bash
make test-api
make lint-api
make analyse-api
```

Run a targeted PHPUnit test:

```bash
docker compose run --rm api php bin/phpunit tests/path/to/Test.php
```

Run Symfony commands:

```bash
docker compose run --rm api php bin/console <command>
```

Fix coding standards only when appropriate:

```bash
docker compose run --rm api composer cs:fix
```

After an entity or mapping change, run the full backend check so migrations and
schema validation are included.

## Testing expectations

- Prefer functional API tests for HTTP behavior and security boundaries.
- Add unit tests for isolated domain services and value transformations.
- Test both allowed and denied authorization paths.
- For alcohol-related behavior, cover anonymous, minor, adult and administrator
  access where relevant.
- Assert response payloads when serialization or the API contract changes.
- Reproduce a bug with a failing test before fixing it when practical.
