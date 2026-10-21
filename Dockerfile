# Netwells Hotspot Portal — single-container Laravel app server (FrankenPHP).
# The host nginx terminates TLS and reverse-proxies to this container on :80.
FROM dunglas/frankenphp:1-php8.4

# PHP extensions the app needs (SQLite + RouterOS API sockets + Laravel deps).
RUN install-php-extensions \
    pdo_sqlite \
    sockets \
    intl \
    zip \
    bcmath \
    opcache \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first (better layer caching).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress

# App source.
COPY . /app

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p /data \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /data

# FrankenPHP serves /app/public. SERVER_NAME=":80" => plain HTTP (TLS is on nginx).
ENV SERVER_NAME=":80"
EXPOSE 80
