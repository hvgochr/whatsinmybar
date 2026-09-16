SHELL := /bin/sh

COMPOSE := docker compose
WEB_CHECK_COMPOSE := WEB_CONTAINER_IP=172.30.71.4 docker compose
PROD_COMPOSE := API_IMAGE=whatsinmybar-api:check WEB_IMAGE=whatsinmybar-web:check CADDY_PROXY_IP=192.0.2.2 docker compose --env-file .env.production.example -f compose.prod.yaml

.PHONY: up down logs ps seed \
	check check-api check-web check-containers \
	test-api lint-api analyse-api \
	test-web lint-web typecheck-web build-web e2e-web check-integration

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
	$(WEB_CHECK_COMPOSE) run --rm web pnpm check

check-containers:
	$(PROD_COMPOSE) config --quiet
	docker build --target production --file infra/docker/api/Dockerfile --tag whatsinmybar-api:check .
	docker run --rm --network none --volume "$(CURDIR)/infra/docker/api/test-upload-routing.php:/tmp/test-upload-routing.php:ro" --entrypoint php whatsinmybar-api:check /tmp/test-upload-routing.php
	docker run --rm --network none --volume "$(CURDIR)/infra/docker/api/test-upload-routing.php:/tmp/test-upload-routing.php:ro" --volume "$(CURDIR)/infra/docker/api/Caddyfile:/tmp/development.Caddyfile:ro" --entrypoint php whatsinmybar-api:check /tmp/test-upload-routing.php /tmp/development.Caddyfile 80
	docker run --rm --network none --volume "$(CURDIR)/infra/docker/api/test-proxy-headers.php:/tmp/test-proxy-headers.php:ro" --volume "$(CURDIR)/infra/caddy/Caddyfile:/tmp/edge.Caddyfile:ro" --volume "$(CURDIR)/infra/caddy/Caddyfile.prod:/tmp/edge.prod.Caddyfile:ro" --entrypoint php whatsinmybar-api:check /tmp/test-proxy-headers.php
	docker build --target production --file infra/docker/web/Dockerfile --tag whatsinmybar-web:check .

test-api:
	$(COMPOSE) run --rm api composer test

lint-api:
	$(COMPOSE) run --rm api composer cs:check

analyse-api:
	$(COMPOSE) run --rm api composer phpstan

test-web:
	$(WEB_CHECK_COMPOSE) run --rm web pnpm test:unit

lint-web:
	$(WEB_CHECK_COMPOSE) run --rm web pnpm lint

typecheck-web:
	$(WEB_CHECK_COMPOSE) run --rm web pnpm typecheck

build-web:
	$(WEB_CHECK_COMPOSE) run --rm web pnpm build

e2e-web:
	$(WEB_CHECK_COMPOSE) run --rm web pnpm test:e2e:install

check-integration: up seed
	@set -eu; \
	restore_api() { $(COMPOSE) up -d --no-deps --force-recreate --wait --wait-timeout 120 api; }; \
	trap restore_api EXIT; \
	integration_secret="release-acceptance-$$(date +%s)-$$$$"; \
	APP_SECRET="$$integration_secret" $(COMPOSE) up -d --no-deps --force-recreate --wait --wait-timeout 120 api; \
	$(COMPOSE) exec -T web pnpm exec playwright install --with-deps chromium; \
	$(COMPOSE) exec -T web pnpm exec playwright test --config playwright.integration.config.ts
