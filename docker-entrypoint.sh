#!/bin/bash
set -e

# Wait for database to be ready (optional)
# You can add database connection check here if needed

# Run migrations (if enabled via RUN_MIGRATIONS env var)
# For production, it's safer to run migrations manually or via CI/CD
# Set RUN_MIGRATIONS=true to enable automatic migrations on container start
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force || echo "Migrations failed, continuing..."
else
    echo "Migrations skipped (set RUN_MIGRATIONS=true to enable automatic migrations)"
fi

# Clear config cache first to ensure fresh proxy settings are loaded
echo "Clearing config cache..."
php artisan config:clear || echo "Config clear failed, continuing..."

# Generate Swagger documentation (before caching to use correct HTTPS URLs)
echo "Generating Swagger documentation..."
php artisan l5-swagger:generate || echo "Swagger generation failed, continuing..."

# Cache configuration after Swagger generation
echo "Caching configuration..."
php artisan config:cache || echo "Config cache failed, continuing..."
php artisan route:cache || echo "Route cache failed, continuing..."
php artisan view:cache || echo "View cache failed, continuing..."

# Create storage symlink
echo "Creating storage symlink..."
php artisan storage:link || echo "Storage link already exists or failed"

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Configure Apache to use PORT environment variable (for Render)
if [ -n "$PORT" ]; then
    # Update Apache port configuration
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf
fi

# Start queue worker in background (for Render development)
# In production, use a separate Background Worker service on Render
if [ "$ENABLE_QUEUE_WORKER" = "true" ] || [ -z "$ENABLE_QUEUE_WORKER" ]; then
    echo "Starting queue worker..."
    chmod +x /var/www/html/docker/start-queue-worker.sh
    /var/www/html/docker/start-queue-worker.sh &
    echo "Queue worker started in background"
fi

# Start Apache
exec apache2-foreground
