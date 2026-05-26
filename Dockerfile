FROM php:8.2-fpm-alpine

# System dependencies — includes Node.js and npm for running build commands
RUN apk add --no-cache \
    git curl zip unzip gettext nodejs npm \
    libpng-dev libxml2-dev libzip-dev \
    oniguruma-dev icu-dev \
    mysql-client

# Allow Composer / Git operations against the bind-mounted repo path used by
# app, queue, scheduler, and reverb containers in dev/staging deployments.
RUN git config --system --add safe.directory /var/www/html

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

# Declare VITE build args so they are available at build time
ARG VITE_APP_NAME
ENV VITE_APP_NAME=${VITE_APP_NAME}
ARG VITE_APP_ENV
ENV VITE_APP_ENV=${VITE_APP_ENV}
ARG VITE_API_URL
ENV VITE_API_URL=${VITE_API_URL}
ARG VITE_REVERB_APP_KEY
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ARG VITE_REVERB_HOST
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST}
ARG VITE_REVERB_PORT
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT}
ARG VITE_REVERB_SCHEME
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

# Copy full source before building — VITE_* env vars and all config files must be
# present at build time so the compiled JS bundle receives the correct values.
COPY . .
RUN npm ci && npm run build

# PHP-FPM pool — listen on TCP 9000 for inter-container FastCGI (nginx → app)
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/zzz-app.conf /usr/local/etc/php-fpm.d/zzz-app.conf

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public \
    && composer run-script post-autoload-dump --verbose \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
