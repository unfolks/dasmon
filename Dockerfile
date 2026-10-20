FROM dunglas/frankenphp:1-php8.5-bookworm AS base

RUN install-php-extensions pdo_pgsql intl zip bcmath opcache pcntl mbstring

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /app

FROM base AS development

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

FROM base AS dependencies

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js svelte.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=dependencies /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    HTTP_PORT=8080

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && setcap -r /usr/local/bin/frankenphp

COPY --from=dependencies /app /app
COPY --from=frontend /app/public/build /app/public/build
COPY docker/frankenphp/production.ini /usr/local/etc/php/conf.d/99-app.ini
COPY --chmod=755 docker/frankenphp/entrypoint.sh /usr/local/bin/app-entrypoint

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && ln -s /app/storage/app/public /app/public/storage \
    && chown -R www-data:www-data storage bootstrap/cache /data /config

USER www-data

EXPOSE 8080

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
