# syntax=docker/dockerfile:1.7

FROM php:8.3-fpm-bookworm AS php-base

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        gettext-base \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        nginx \
        postgresql-client \
        sqlite3 \
        supervisor \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev \
    && php artisan package:discover --ansi

FROM node:20-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./vite.config.js
RUN npm run build

FROM php-base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=10000

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/nginx.conf.template /etc/nginx/templates/rgmo-irms.conf.template
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/rgmo-irms.conf

RUN chmod +x /var/www/html/docker/start.sh \
    && mkdir -p \
        /var/www/html/database \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data \
        /var/www/html/database \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

EXPOSE 10000

CMD ["/var/www/html/docker/start.sh"]
