# =============================================
# AI Marketing SaaS — Production Dockerfile
# Multi-stage build for minimal attack surface
# =============================================

# ---- Stage 1: Base OS with PHP 8.4 + extensions ----
FROM php:8.4-fpm-alpine3.20 AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    redis \
    postgresql-client \
    mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    gd \
    zip \
    bcmath \
    intl \
    opcache \
    sockets \
    pcntl \
    && pecl install redis igbinary \
    && docker-php-ext-enable redis igbinary

# Configure PHP for production
COPY docker/php/production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini

# ---- Stage 2: Composer dependencies ----
FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --ignore-platform-req=ext-gd \
    && composer clear-cache

# ---- Stage 3: Frontend assets ----
FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev
COPY . .
RUN npm run build

# ---- Stage 4: Production image ----
FROM base AS production

# Create non-root user
RUN addgroup -g 1000 -S appgroup && \
    adduser -u 1000 -S appuser -G appgroup

WORKDIR /var/www/html

# Copy application code
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY --chown=appuser:appgroup . .

# Set permissions
RUN chown -R appuser:appgroup /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy nginx + supervisor configs
COPY docker/nginx/production.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

# Expose port
EXPOSE 80 443

# Switch to non-root user
USER appuser

# Entry point
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]