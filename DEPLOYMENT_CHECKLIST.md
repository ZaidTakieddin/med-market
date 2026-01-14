# Deployment Checklist

Use this checklist before deploying to production.

## Pre-Deployment

- [ ] All environment variables documented in `.env.example`
- [ ] Database migrations tested locally
- [ ] Database seeders tested (if using)
- [ ] API endpoints tested
- [ ] Swagger documentation generated (`php artisan l5-swagger:generate`)
- [ ] Storage symlink created (`php artisan storage:link`)
- [ ] `.env` file configured with production values
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] Strong `APP_KEY` generated (`php artisan key:generate`)
- [ ] Dockerfile tested locally (optional but recommended)
- [ ] Git repository pushed with all deployment files

## Environment Variables to Set

### Required
- [ ] `APP_NAME`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` (generated)
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` (your production URL)
- [ ] `DB_CONNECTION=pgsql`
- [ ] `DB_HOST` (Neon pooled endpoint)
- [ ] `DB_PORT=5432`
- [ ] `DB_DATABASE`
- [ ] `DB_USERNAME`
- [ ] `DB_PASSWORD`
- [ ] `DB_SSLMODE=require`

### Optional (but recommended)
- [ ] `SESSION_DRIVER=database`
- [ ] `QUEUE_CONNECTION=database`
- [ ] `CACHE_STORE=database`
- [ ] `LOG_LEVEL=error` (production)
- [ ] `SANCTUM_STATEFUL_DOMAINS` (if using SPA)

## Post-Deployment

- [ ] Run migrations: `php artisan migrate --force`
- [ ] Cache configuration: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`
- [ ] Create storage link: `php artisan storage:link`
- [ ] Generate Swagger docs: `php artisan l5-swagger:generate`
- [ ] Test health endpoint: `GET /up`
- [ ] Test API endpoints
- [ ] Verify database connection
- [ ] Check logs for errors

## Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] Strong passwords for database
- [ ] HTTPS enabled
- [ ] CORS configured (if needed)
- [ ] Rate limiting enabled
- [ ] Environment variables secured (not in code)
- [ ] `.env` file not committed to Git

## Monitoring

- [ ] Error logging configured
- [ ] Health check endpoint working (`/up`)
- [ ] Database connection monitoring
- [ ] Application logs accessible
- [ ] Performance monitoring (optional)

## Quick Commands

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage symlink
php artisan storage:link

# Generate Swagger docs
php artisan l5-swagger:generate

# Clear all caches (if needed)
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

