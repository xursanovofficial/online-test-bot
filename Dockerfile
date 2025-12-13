ARG PHP_VERSION=8.3
ARG COMPOSER_VERSION=2.7

# BASE STAGE
FROM php:${PHP_VERSION}-fpm-alpine AS build

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
    gd \
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
FROM php:${PHP_VERSION}-fpm-alpine AS production

WORKDIR /var/www

# Install runtime dependencies only
RUN apk add --no-cache \
    nginx \
    libzip \
    libpng \
    oniguruma \
    postgresql-libs \
    icu-libs \
    zip \
    unzip \
    sqlite \
    sqlite-libs

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Create non-root user
RUN addgroup -S appgroup && adduser -S appuser -G appgroup

# Copy built application
COPY --from=builder --chown=appuser:appgroup /var/www .
COPY ./.docker/local.ini $PHP_INI_DIR/conf.d/custom.ini
COPY ./.docker/nginx.conf /etc/nginx/nginx.conf
COPY ./.docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf


# Configure permissions and directories for nginx
RUN chown -R appuser:appgroup /var/www && \
chmod -R 755 /var/www && \
mkdir -p /var/run/nginx && \
mkdir -p /var/log/nginx && \
mkdir -p /var/lib/nginx && \
chown -R appuser:appgroup /var/run/nginx && \
chown -R appuser:appgroup /var/log/nginx && \
chown -R appuser:appgroup /var/lib/nginx

EXPOSE 80

# Set the entrypoint script
COPY ./.docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

USER appuser

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]