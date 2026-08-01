FROM php:8.2-cli

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=10000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    zip \
    sqlite3 \
    libsqlite3-dev \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency manifests first for better layer caching
COPY composer.json composer.lock* ./
COPY package.json package-lock.json* ./

# Install PHP and Node dependencies
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && npm install

# Copy the rest of the application
COPY . .

# Build frontend assets
RUN npm run build

# Prepare SQLite database file and Laravel caches
RUN touch database/database.sqlite \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 10000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT}"]
