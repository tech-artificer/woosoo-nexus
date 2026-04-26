FROM php:8.2-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    git curl zip unzip \
    libpng-dev libxml2-dev libzip-dev \
    oniguruma-dev icu-dev \
    mysql-client

# PHP extensions
RUN docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

# Redis PHP extension
RUN apk add --no-cache --virtual .build-deps autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .

RUN composer run-script post-autoload-dump 2>/dev/null || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
