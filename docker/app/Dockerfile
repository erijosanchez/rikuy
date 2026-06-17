# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — build de assets de frontend (Vite + Vue + Inertia)
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY app/package.json app/package-lock.json ./
RUN npm ci
COPY app/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — runtime PHP-FPM + nginx (servido por supervisor)
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS app

# Extensiones de sistema + PHP necesarias para Postgres / Redis / colas
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql bcmath pcntl zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencias PHP primero (mejor cacheo de capas)
COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader --no-scripts

# Código de la app + assets ya compilados
COPY app/ ./
COPY --from=assets /build/public/build ./public/build

# Configs de servidor
COPY docker/app/nginx.conf /etc/nginx/sites-available/default
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
