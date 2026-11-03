# syntax=docker/dockerfile:1.7
# AT.urtg.uz — backend (Laravel / PHP-FPM) image
# Stages: base → dev (bind-mounted code, composer) | vendor → prod (code baked in)

FROM php:8.3-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl zip bcmath opcache pcntl redis \
    && apk add --no-cache fcgi tzdata

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html
EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --start-period=60s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------- development
FROM base AS dev

ARG UID=1000
ARG GID=1000
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN addgroup -g "${GID}" app && adduser -D -u "${UID}" -G app app
ENV COMPOSER_HOME=/tmp/composer
USER app

# ---------------------------------------------------------------- vendor deps
FROM composer:2 AS vendor

WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------- production
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
RUN rm -rf tests .env .env.* \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
       storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
ENV APP_OPTIMIZE=true
