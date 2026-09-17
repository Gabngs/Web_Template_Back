# syntax=docker/dockerfile:1

# ── Stage 1: Build ────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS build

RUN apk add --no-cache git unzip libpng-dev libzip-dev freetype-dev libjpeg-turbo-dev autoconf g++ make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd opcache pcntl bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Solo los manifiestos primero: esta capa (y la descarga de paquetes) se
# cachea mientras composer.json/composer.lock no cambien, sin importar
# qué código de la app se toque después.
COPY composer.json composer.lock ./

RUN --mount=type=secret,id=github_token \
    if [ -s /run/secrets/github_token ]; then \
      export COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$(cat /run/secrets/github_token)\"}}"; \
    fi; \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .

RUN composer dump-autoload --no-dev --optimize

# ── Stage 2: Runtime ──────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache nginx supervisor bash libpng libzip freetype libjpeg-turbo \
    && mkdir -p /run/nginx /var/log/supervisor

# Extensiones PHP del stage build
COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d    /usr/local/etc/php/conf.d

# Código de la aplicación
COPY --from=build /var/www /var/www

# Configs de runtime
COPY docker/nginx.conf        /etc/nginx/nginx.conf
COPY docker/supervisord.conf  /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/entrypoint.sh     /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

RUN mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/framework/testing \
      storage/logs \
      storage/keys \
      storage/api-docs \
      bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 755 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
