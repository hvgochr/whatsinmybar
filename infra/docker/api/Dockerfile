FROM dunglas/frankenphp:1-php8.4-bookworm AS base

WORKDIR /app

RUN install-php-extensions \
    intl \
    opcache \
    pdo_pgsql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY infra/docker/api/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

ENV COMPOSER_ALLOW_SUPERUSER=1

FROM base AS development

COPY infra/docker/api/Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 80

CMD ["sh", "-lc", "composer install --prefer-dist --no-interaction && frankenphp run --config /etc/frankenphp/Caddyfile"]

FROM composer:2 AS production-vendor

WORKDIR /app

COPY apps/api/composer.json apps/api/composer.lock apps/api/symfony.lock ./

RUN composer install \
    --classmap-authoritative \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM base AS production

ENV APP_DEBUG=0
ENV APP_ENV=prod

COPY apps/api /app
COPY --from=production-vendor /app/vendor /app/vendor
COPY infra/docker/api/Caddyfile.prod /etc/frankenphp/Caddyfile

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev \
        --no-interaction \
        --no-scripts \
    && mkdir -p /app/public/uploads /app/var \
    && chown -R www-data:www-data /app/public/uploads /app/var

USER www-data

EXPOSE 8080

CMD ["sh", "-lc", "php bin/console cache:clear --env=prod --no-debug --no-interaction && frankenphp run --config /etc/frankenphp/Caddyfile"]
