FROM dunglas/frankenphp:latest

# Install system dependencies and required PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpq-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        pcntl \
        bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Increase PHP upload limits for FrankenPHP
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev in production; use --dev flag override for local)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy the rest of the application
COPY . .

# Run post-install scripts (package discovery, etc.)
RUN composer run-script post-autoload-dump

# Set correct permissions for Laravel storage and cache
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

RUN setcap -r /usr/local/bin/frankenphp
EXPOSE 8000
