FROM php:8.3-fpm-alpine

# Install system dependencies + PHP extensions needed by Laravel
RUN apk add --no-cache \
    git curl zip unzip bash \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    oniguruma-dev libxml2-dev \
    && docker-php-ext-install \
        pdo pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        opcache

# Install Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# Copy composer files first for layer caching
COPY ../../api/composer.json ../../api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY ../../api .
RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
