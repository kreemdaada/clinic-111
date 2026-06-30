# syntax=docker/dockerfile:1

FROM composer:2.8 AS composer_binary

FROM dunglas/frankenphp:1.9.1-php8.3-bookworm AS php_base

RUN install-php-extensions \
    pdo_pgsql \
    bcmath \
    gd \
    zip \
    intl \
    pcntl

FROM php_base AS vendor

WORKDIR /app

COPY --from=composer_binary /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --classmap-authoritative \
    --no-scripts

COPY . .

ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

RUN composer dump-autoload \
    --classmap-authoritative \
    --no-dev \
    && php artisan package:discover --ansi

FROM php_base AS runtime

ARG OCI_IMAGE_SOURCE="https://github.com/kreemdaada/clinic-111"

LABEL org.opencontainers.image.source="${OCI_IMAGE_SOURCE}"

COPY deploy/php.ini /usr/local/etc/php/conf.d/99-dentalfinance.ini
COPY deploy/Caddyfile /etc/caddy/Caddyfile

WORKDIR /app

COPY --from=vendor /app /app

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 443

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
