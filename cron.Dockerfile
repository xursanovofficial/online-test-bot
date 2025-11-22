ARG PHP_VERSION=8.3
ARG COMPOSER_VERSION=2.7

# BASE STAGE
FROM php:${PHP_VERSION}-cli-alpine AS build

WORKDIR /var/www

# Install system dependencies
RUN apk add --no-cache --virtual .build-deps \
    libzip-dev \
    libpng-dev \
    oniguruma-dev \
    postgresql-dev \
    autoconf \
    g++ \
    make \
    pkgconfig \
    icu-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure intl
RUN docker-php-ext-install \
    intl \
    pdo \
    pdo_pgsql \
    bcmath \
    zip \
    mbstring \
    sockets


# BUILD STAGE
FROM composer:${COMPOSER_VERSION} AS builder

WORKDIR /var/www

# Install dependencies
COPY composer.* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=ext-gd --ignore-platform-req=ext-sockets

# Copy application
COPY . .
RUN composer dump-autoload --optimize --no-dev


# PRODUCTION STAGE
FROM php:${PHP_VERSION}-cli-alpine AS production

WORKDIR /var/www

RUN apk add --no-cache \
    nginx \
    libzip \
    libpng \
    oniguruma \
    postgresql-libs \
    icu-libs \
    zip \
    unzip

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Create non-root user
RUN addgroup -S appgroup && adduser -S appuser -G appgroup

# Copy built PHP extensions and config
COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Copy built application
COPY --from=builder --chown=appuser:appgroup /var/www .
COPY ./.docker/local.ini $PHP_INI_DIR/conf.d/custom.ini

# Ensure cron runs as root but application files are owned by appuser
USER root
COPY ./.docker/crontab /etc/crontabs/root
RUN mkdir -p /root/.cache/crontab

# Start cron in the foreground
CMD ["crond", "-f"]