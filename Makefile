SHELL := /bin/sh

COMPOSE := docker compose
PROD_COMPOSE := docker compose --env-file .env.prod.example -f compose.prod.yaml

.PHONY: up down logs ps seed \
	check check-api check-web check-containers \
	test-api lint-api analyse-api \
	test-web lint-web typecheck-web build-web e2e-web

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

seed:
	$(COMPOSE) exec api php bin/console doctrine:migrations:migrate --no-interaction
	$(COMPOSE) exec api php bin/console app:seed:dev

check: check-api check-web

check-api:
	$(COMPOSE) run --rm api composer check

check-web:
	$(COMPOSE) run --rm web pnpm check

check-containers:
	$(PROD_COMPOSE) config --quiet
	docker build --target production --file infra/docker/api/Dockerfile --tag whatsinmybar-api:check .
	docker build --target production --file infra/docker/web/Dockerfile --tag whatsinmybar-web:check .

test-api:
	$(COMPOSE) run --rm api composer test

lint-api:
	$(COMPOSE) run --rm api composer cs:check

analyse-api:
	$(COMPOSE) run --rm api composer phpstan

test-web:
	$(COMPOSE) run --rm web pnpm test:unit

lint-web:
	$(COMPOSE) run --rm web pnpm lint

typecheck-web:
	$(COMPOSE) run --rm web pnpm typecheck

build-web:
	$(COMPOSE) run --rm web pnpm build

e2e-web:
	$(COMPOSE) run --rm web pnpm test:e2e:install