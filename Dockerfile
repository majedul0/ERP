# syntax=docker/dockerfile:1

###############################################################################
# Stage 1 — build the frontend
#
# A PHP image with Node added, not a plain Node image. `npm run build` runs
# Vite, whose Wayfinder plugin shells out to `php artisan wayfinder:generate
# --with-form` to write resources/js/{actions,routes}. Those are gitignored, so
# a fresh checkout does not have them, and the build fails outright without PHP
# and an installed vendor/ to boot the application.
###############################################################################
FROM php:8.4-cli-alpine AS assets

# pcntl is here because laravel/horizon declares ext-pcntl as a platform
# requirement — `composer install` refuses to resolve without it, even though
# nothing in this stage ever runs a queue worker.
#
# Nothing is uninstalled afterwards: this stage is a throwaway builder that
# never ships, so trimming it buys nothing and risks removing a shared library
# that intl or zip still needs at run time.
RUN apk add --no-cache nodejs npm git unzip $PHPIZE_DEPS icu-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install intl bcmath zip pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first: a change to application source then reuses these layers.
COPY composer.json composer.lock ./
COPY database/ database/
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# `.env.example` only exists so artisan can boot during the build — Wayfinder
# needs a bootable application to read the route table. It is deleted below;
# the real environment lives on the VPS and is read at container start.
RUN cp .env.example .env \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && npm run build \
    && rm -rf node_modules .env storage/framework/views/*.php

###############################################################################
# Stage 2 — runtime
#
# nginx and php-fpm share this container under supervisor. Splitting them would
# mean the nginx container could not see public/ to serve static assets without
# a shared volume populated at run time — more moving parts than the separation
# is worth for one application on one box. Postgres, Redis, Horizon and the
# scheduler are all separate containers.
###############################################################################
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache nginx supervisor postgresql-libs icu-libs oniguruma libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql bcmath zip intl opcache pcntl \
    # REDIS_CLIENT=phpredis: the app talks to Redis through the C extension, so
    # cache, sessions, queues and the invoice-number locks all need it present.
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear \
    # Horizon needs pcntl and posix for signal handling. Fail the build here
    # rather than discovering it when queues silently stop processing.
    && php -m | grep -qx pcntl \
    && php -m | grep -qx posix \
    && php -m | grep -qx redis

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --from=assets --chown=www-data:www-data /app /var/www/html

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /var/log/supervisor /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://127.0.0.1/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
