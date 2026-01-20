# Multi-stage Dockerfile for development and production

# ================================
# Base stage with common dependencies
# ================================
FROM php:8.4-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client \
    redis \
    supervisor

# Clear cache and install PHP extensions
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# ================================
# Development stage
# ================================
FROM base AS development

# Install Node.js and npm for development
RUN apk add --no-cache nodejs npm

# Copy application code
COPY backend/ ./
COPY --chown=www-data:www-data backend/ ./

# Install PHP dependencies (including dev dependencies)
RUN composer install --optimize-autoloader

# Create necessary directories
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# Copy supervisor config for development
COPY docker/app/supervisord.conf /etc/supervisord.conf

# Expose port and set command
EXPOSE 9000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# ================================
# Production stage
# ================================
FROM base AS production

# Copy application code
COPY backend/ ./
COPY --chown=www-data:www-data backend/ ./

# Install PHP dependencies (production only)
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

# Copy supervisor config for production
COPY docker/app/supervisord.conf /etc/supervisord.conf

# Security hardening for production
RUN apk del git \
    && addgroup -g 1001 -S webgroup \
    && adduser -u 1001 -S webuser -G webgroup

USER webuser

# Expose port and set command
EXPOSE 9000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]