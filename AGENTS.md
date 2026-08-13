# WhatsInMyBar repository guidance

## Project overview

WhatsInMyBar is an English-language social cocktail recipe application.

The repository is a Docker-first monorepo:

- `apps/api`: PHP 8.4, Symfony 8.1, API Platform 4.3, Doctrine ORM and PostgreSQL.
- `apps/web`: Nuxt 4, Vue 3, TypeScript, Tailwind CSS and shadcn-nuxt.
- `docs`: functional specifications, API contract, quality baselines and operations.
- `infra`: Docker images and Caddy configuration.
- `compose.yaml`: local development environment.
- `compose.prod.yaml`: production-oriented environment.

Run Codex from the repository root unless the task is explicitly limited to one
application.

## Read before changing code

Read only the documentation relevant to the task:

- `README.md`: repository overview and primary commands.
- `docs/specifications.md`: product scope and business rules.
- `docs/api.md`: internal HTTP API contract.
- `docs/docker-dev.md`: local Docker environment.
- `docs/docker-prod.md`: production deployment.
- `docs/backend-quality.md`: backend checks.
- `docs/frontend-quality.md`: frontend checks.

Before modifying `apps/api`, read `apps/api/AGENTS.md`.

Before modifying `apps/web`, read `apps/web/AGENTS.md`.

For a cross-stack change, read both application-specific instruction files.

The existing implementation and tests describe current behavior. Documentation
may also describe planned behavior. Do not assume a documented feature already
exists without inspecting the code.

Do not silently resolve an open decision from `docs/specifications.md` when it
materially affects product behavior, security or the public API contract.

## Development environment

Use Docker Compose from the repository root.

Common commands:

```bash
make up
make down
make logs
make seed
make check-api
make check-web
make check
```

The application entrypoint is:

```
http://localhost:8080
```

Do not install project PHP, Composer, Node.js or PostgreSQL dependencies directly
on the host unless explicitly requested.

Do not run docker compose down -v unless explicitly requested. It removes
persistent development data.

## Working agreements

Before editing:

- Inspect the relevant implementation, tests and documentation.
- Check the current Git status and preserve unrelated user changes.
- Identify whether the task affects the API contract, database schema,
- security rules or both applications.

While editing:

- Keep changes scoped to the requested outcome.
- Follow existing patterns unless the task explicitly calls for a refactor.
- Do not add or upgrade production dependencies without explaining why.
- Do not update lockfiles unless the dependency graph intentionally changes.
- Add or update tests for behavior changes.
- Update documentation when behavior, configuration or the API contract changes.
- Never weaken authorization, alcohol restrictions, validation or upload protections to make a test pass.
- Never commit credentials, local environment files or production secrets.
- Do not commit, push or open a pull request unless explicitly requested.

## Security invariants

The backend is the source of truth for authorization and content visibility.

In particular:

- Anonymous users must never access alcoholic recipes.
- Authenticated users under 18 must never access alcoholic recipes.
- Alcohol restrictions apply to collections, search results and item access.
- Frontend filtering and route middleware are not authorization boundaries.
- Admin operations must always be protected server-side.
- Uploaded files must be validated before storage.
- Long-lived authentication tokens must not be stored in localStorage.

Treat changes to authentication, authorization, alcohol visibility, moderation,
uploads and serialization as security-sensitive.

## Validation strategy

During implementation, run the smallest relevant checks first.

Before finishing, run the complete check for each modified application when
feasible:

```bash
make check-api
make check-web
```

For cross-stack changes:

```bash
make check
```

If infrastructure or production container files change, also run:

```bash
make check-containers
```

Before reporting completion:

1. Review the complete diff.
2. Check for unrelated or generated files.
3. Report the commands that passed.
4. Explicitly report checks that could not be run and why.

## Code review rules

When reviewing changes, prioritize:

- authorization and alcohol-visibility regressions;
- API contract incompatibilities;
- missing database migrations;
- unsafe file uploads or secret exposure;
- SSR/client inconsistencies;
- missing tests for changed behavior;
- deployment configuration regressions.

Leave deterministic formatting and linting checks to the configured tools.
