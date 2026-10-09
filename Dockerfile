# syntax=docker/dockerfile:1
# Production image for Render (or any Docker host): PHP 8.3 + nginx via serversideup/php.

# ── 1. Front-end assets (Vite + Tailwind) ────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY app/Enums ./app/Enums
RUN npm run build

# ── 2. PHP dependencies ──────────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts --ignore-platform-reqs

# ── 3. Runtime ───────────────────────────────────────────────────────────────
FROM serversideup/php:8.3-fpm-nginx
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    SSL_MODE=off
USER root
RUN install-php-extensions gd intl zip
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
# Runs after the image's own Laravel automations (which already execute `php artisan migrate --force`).
COPY --chmod=755 docker/40-dems-dbcheck.sh /etc/entrypoint.d/40-dems-dbcheck.sh
COPY --chmod=755 docker/60-dems-seed.sh /etc/entrypoint.d/60-dems-seed.sh
USER www-data
EXPOSE 8080
