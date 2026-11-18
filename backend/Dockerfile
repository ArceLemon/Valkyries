# API KUMA BANA (Laravel). La même image sert l'API et le planificateur.

# --- 1. Dépendances PHP
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts --ignore-platform-reqs

# --- 2. Image d'exécution. libsodium et mbstring sont déjà dans l'image PHP officielle ;
#        on ajoute le pilote PostgreSQL (SQLite reste disponible pour les tests).
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get purge -y --auto-remove libpq-dev \
    && apt-get install -y --no-install-recommends libpq5 \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www
COPY --from=vendor /app /var/www
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /data \
    && chmod -R a+rw storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    DB_HOST=postgres \
    DB_PORT=5432 \
    DB_DATABASE=kumabana \
    DB_USERNAME=kumabana \
    KUMA_CLES_DIR=/data/cles \
    PHP_CLI_SERVER_WORKERS=4

VOLUME /data
EXPOSE 8000
ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
