FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    mariadb-client \
    redis \
    supervisor

# Clear cache
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY backend/ ./

# Copy existing application directory permissions
COPY --chown=www-data:www-data backend/ ./

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Create storage link
RUN php artisan storage:link || true

# Create necessary directories
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# Copy supervisor config
COPY docker/app/supervisord.conf /etc/supervisord.conf

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]