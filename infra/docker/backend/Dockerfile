FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    linux-headers \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        pcntl \
        mbstring \
        dom \
        xml \
        xmlwriter \
        gd \
        zip \
        intl \
        opcache

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Get Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Custom PHP config
COPY infra/docker/backend/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# Create system user to run PHP-FPM
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

ARG COMPOSER_WITH_DEV=false

COPY backend/ /var/www/html/

RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    && if [ "$COMPOSER_WITH_DEV" = "true" ]; then \
        composer install --no-interaction --prefer-dist --no-scripts; \
    else \
        composer install --no-interaction --prefer-dist --no-dev --no-scripts; \
    fi \
    && composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && chown -R www:www storage bootstrap/cache vendor

USER www

EXPOSE 9000
CMD ["php-fpm"]

