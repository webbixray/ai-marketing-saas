#!/bin/sh
# Docker Entrypoint Script
# =============================================
# Runs database migrations, cache optimization, and starts services

set -e

echo "=== AI Marketing SaaS — Container Starting ==="

# Wait for database
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database at $DB_HOST:$DB_PORT..."
    while ! nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; do
        sleep 2
    done
    echo "Database is ready!"
fi

# Wait for Redis
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis at $REDIS_HOST:$REDIS_PORT..."
    while ! nc -z "$REDIS_HOST" "$REDIS_PORT" 2>/dev/null; do
        sleep 2
    done
    echo "Redis is ready!"
fi

# Run migrations
echo "Running database migrations..."
php /var/www/html/artisan migrate --force --no-interaction || {
    echo "Migration failed!"
    exit 1
}

# Optimize application
echo "Optimizing application..."
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan view:cache
php /var/www/html/artisan event:cache

# Create storage link if needed
if [ ! -L /var/www/html/public/storage ]; then
    php /var/www/html/artisan storage:link
fi

# Set permissions
chown -R appuser:appgroup /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate SSL self-signed cert if not provided (for dev/testing only)
if [ ! -f /etc/nginx/ssl/fullchain.pem ] && [ "$APP_ENV" != "production" ]; then
    echo "Generating self-signed SSL certificate for development..."
    mkdir -p /etc/nginx/ssl
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/privkey.pem \
        -out /etc/nginx/ssl/fullchain.pem \
        -subj "/C=US/ST=State/L=City/O=Webbixray/CN=localhost"
    cp /etc/nginx/ssl/fullchain.pem /etc/nginx/ssl/chain.pem
fi

echo "=== Container Ready — Starting Services ==="

# Execute the main command
exec "$@"