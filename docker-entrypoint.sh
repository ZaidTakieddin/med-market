#!/bin/bash
set -e

# Wait for database to be ready (optional)
# You can add database connection check here if needed

# Run migrations
# echo "Running migrations..."
# php artisan migrate --force || echo "Migrations failed, continuing..."

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

# Start Apache
exec apache2-foreground
