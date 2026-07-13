FROM dunglas/frankenphp:1-php8.4-bookworm

WORKDIR /app

RUN install-php-extensions \
    intl \
    opcache \
    pdo_pgsql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY infra/docker/api/Caddyfile /etc/frankenphp/Caddyfile

ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80

CMD ["sh", "-lc", "composer install --prefer-dist --no-interaction && frankenphp run --config /etc/frankenphp/Caddyfile"]
