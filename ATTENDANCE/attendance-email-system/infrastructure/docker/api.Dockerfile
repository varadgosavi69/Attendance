# syntax=docker/dockerfile:1
# Production PHP-FPM image for the Laravel API.
# Build context: attendance-email-system/api/

# ── Stage 1: Composer deps (no dev, classmap-optimized) ──────────────────────
FROM composer:2.8 AS deps

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# ── Stage 2: PHP-FPM runtime (no build tools, minimal layers) ────────────────
FROM php:8.3-fpm-alpine AS runtime

# Install runtime libs; compile extensions; remove build-only packages in one RUN
RUN apk add --no-cache \
        libpng libjpeg-turbo freetype oniguruma libxml2 curl \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring exif pcntl bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# OPcache: disable timestamp revalidation — code is immutable inside the image
RUN printf 'opcache.enable=1\nopcache.memory_consumption=256\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=20000\nopcache.revalidate_freq=0\nopcache.validate_timestamps=0\nopcache.fast_shutdown=1\n' \
    > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/api

COPY --from=deps /app .

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
