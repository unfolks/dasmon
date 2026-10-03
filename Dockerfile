FROM dunglas/frankenphp:1-php8.5-bookworm

RUN install-php-extensions pdo_pgsql intl zip bcmath opcache pcntl \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /app

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
