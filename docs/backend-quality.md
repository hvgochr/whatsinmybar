# Backend Quality Baseline

The Symfony backend quality baseline lives in `apps/api`.

## Tools

- PHPUnit for automated tests.
- PHPStan with Symfony and Doctrine extensions for static analysis.
- PHP-CS-Fixer for coding standards.
- Composer audit for dependency advisory checks.
- Doctrine schema validation and migrations dry-run for database checks.

## Local Commands

Run commands from the API container:

```bash
docker compose run --rm api composer check
```

Individual checks:

```bash
docker compose run --rm api composer check:composer
docker compose run --rm api composer security:audit
docker compose run --rm api composer cs:check
docker compose run --rm api composer cs:fix
docker compose run --rm api composer phpstan
docker compose run --rm api composer test
docker compose run --rm api composer db:create
docker compose run --rm api composer db:migrate
docker compose run --rm api composer db:validate
docker compose run --rm api composer migrations:dry-run
```

## CI

GitHub Actions calls `.github/workflows/backend.yml` from the top-level
`docker.yml` validation graph on every pull request and push to `main`.

The CI job starts PostgreSQL 16 and runs:

```text
composer validate --strict
composer install
composer audit
PHP-CS-Fixer dry-run
PHPStan
PHPUnit
doctrine:database:create
doctrine:migrations:migrate
doctrine:schema:validate
doctrine:migrations:migrate --dry-run
```
