# Deployment Guide - Med Market

This guide covers deploying the Med Market Laravel application to free hosting platforms.

## 🚀 Free Hosting Options

### Recommended Platforms

1. **Render** (Recommended)

    - Free tier: 750 hours/month
    - PostgreSQL support
    - Auto-deploy from Git
    - [render.com](https://render.com)

2. **Railway**

    - Free tier: $5 credit/month
    - PostgreSQL support
    - Easy deployment
    - [railway.app](https://railway.app)

3. **Fly.io**
    - Free tier: 3 shared VMs
    - PostgreSQL support
    - Global deployment
    - [fly.io](https://fly.io)

## 📋 Pre-Deployment Checklist

-   [ ] Database configured (Neon PostgreSQL)
-   [ ] Environment variables documented
-   [ ] Storage symlink configured
-   [ ] Migrations ready
-   [ ] Queue workers configured (if needed)

## 🔧 Environment Variables

Set these in your hosting platform's environment variables:

### Required Variables

```env
APP_NAME="Med Market"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://your-app-url.onrender.com

# Trust proxies for HTTPS detection (Render, Cloudflare, etc.)
TRUSTED_PROXIES=*

DB_CONNECTION=pgsql
DB_HOST=your-neon-host-pooler.c-2.eu-central-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=your-password
DB_SSLMODE=require

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

### Optional Variables

```env
# Seeder connection (only needed if running seeders)
DB_SEEDER_HOST=your-neon-host-direct.c-2.eu-central-1.aws.neon.tech
DB_SEEDER_PORT=5432
DB_SEEDER_DATABASE=neondb
DB_SEEDER_USERNAME=neondb_owner
DB_SEEDER_PASSWORD=your-password

# Mail configuration (if sending emails)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@medmarket.com
MAIL_FROM_NAME="Med Market"
```

## 🎯 Platform-Specific Instructions

### Render.com

#### 1. Create a New Web Service

1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click "New +" → "Web Service"
3. Connect your Git repository
4. Configure the service:

**Environment:** Docker

**Dockerfile:** The project includes a `Dockerfile` that will be automatically detected by Render.

**Build Settings:**

-   Dockerfile Path: `Dockerfile` (default)
-   Docker Context: `.` (root directory)

**Environment Variables:**
Add all required variables from the list above.

#### 2. How the Dockerfile Works

The included `Dockerfile`:

-   Uses PHP 8.2 with Apache web server
-   Installs all required PHP extensions (PostgreSQL, GD, etc.)
-   Installs Composer and dependencies
-   Sets up proper file permissions
-   Configures Apache for Laravel
-   Runs migrations and caches configuration on startup via `docker-entrypoint.sh`

#### 3. Alternative: Using render.yaml (Without Docker)

If you prefer not to use Docker, you can use `render.yaml`:

```yaml
services:
    - type: web
      name: med-market-api
      env: php
      buildCommand: composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link
      startCommand: php artisan serve --host=0.0.0.0 --port=$PORT
      envVars:
          - key: APP_ENV
            value: production
          - key: APP_DEBUG
            value: false
```

**Note:** If using `render.yaml` without Docker, you may need to ensure PHP extensions are available or use a PHP buildpack.

#### 4. Post-Deployment

The Dockerfile automatically:

-   Runs migrations (`php artisan migrate --force`)
-   Caches configuration, routes, and views
-   Creates storage symlink
-   Generates Swagger documentation

No manual steps required after deployment!

```bash
php artisan migrate --force
```

Or add to build command:

```bash
composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Railway.app

#### 1. Create New Project

1. Go to [Railway Dashboard](https://railway.app)
2. Click "New Project" → "Deploy from GitHub repo"
3. Select your repository

#### 2. Configure Environment

Railway will auto-detect Laravel. Set environment variables in the dashboard.

#### 3. Add `railway.json` (Optional)

```json
{
    "$schema": "https://railway.app/railway.schema.json",
    "build": {
        "builder": "NIXPACKS",
        "buildCommand": "composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"
    },
    "deploy": {
        "startCommand": "php artisan serve --host=0.0.0.0 --port=$PORT",
        "restartPolicyType": "ON_FAILURE",
        "restartPolicyMaxRetries": 10
    }
}
```

### Fly.io

#### 1. Install Fly CLI

```bash
curl -L https://fly.io/install.sh | sh
```

#### 2. Initialize Fly App

```bash
fly launch
```

#### 3. Create `fly.toml`

```toml
app = "med-market-api"
primary_region = "iad"

[build]
  builder = "paketobuildpacks/builder:base"

[env]
  APP_ENV = "production"
  APP_DEBUG = "false"

[http_service]
  internal_port = 8000
  force_https = true
  auto_stop_machines = true
  auto_start_machines = true
  min_machines_running = 0
  processes = ["app"]

[[services]]
  http_checks = []
  internal_port = 8000
  processes = ["app"]
  protocol = "tcp"
  script_checks = []
```

#### 4. Deploy

```bash
fly deploy
```

## 🔐 Security Checklist

-   [ ] `APP_DEBUG=false` in production
-   [ ] `APP_ENV=production`
-   [ ] Strong `APP_KEY` generated
-   [ ] Database credentials secure
-   [ ] SSL/HTTPS enabled
-   [ ] CORS configured (if needed)
-   [ ] Rate limiting enabled

## 📦 Post-Deployment Steps

1. **Run Migrations**

    ```bash
    php artisan migrate --force
    ```

2. **Create Storage Link**

    ```bash
    php artisan storage:link
    ```

3. **Cache Configuration**

    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

4. **Seed Database (Optional)**

    ```bash
    php artisan db:seed --force
    ```

5. **Generate Swagger Documentation**
    ```bash
    php artisan l5-swagger:generate
    ```

## 🔍 Health Check Endpoint

Laravel includes a health check endpoint at `/up`. Configure your hosting platform to use this for health checks.

## 📊 Monitoring

-   Check logs: `php artisan pail` (development) or view hosting platform logs
-   Monitor database connections
-   Set up error tracking (Sentry, Bugsnag, etc.)

## 🐛 Troubleshooting

### Common Issues

1. **500 Error**

    - Check `APP_DEBUG=true` temporarily to see errors
    - Verify all environment variables are set
    - Check database connection

2. **Database Connection Failed**

    - Verify Neon connection string
    - Check SSL mode (`require` for Neon)
    - Verify firewall/network access

3. **Storage Permission Errors**

    - Ensure `storage/` and `bootstrap/cache/` are writable
    - Run `chmod -R 775 storage bootstrap/cache`

4. **Migration Errors**
    - Check transaction settings (Neon pooling)
    - Verify database user permissions

## 📝 Notes

-   **Neon Database**: Use pooled connection (`-pooler`) for production API
-   **Queue Workers**: If using queues, set up a separate worker process
-   **File Storage**: Consider using S3 or similar for production file storage
-   **Caching**: Use Redis or Memcached for better performance in production

## 🔗 Useful Links

-   [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
-   [Render Laravel Guide](https://render.com/docs/deploy-laravel)
-   [Railway Laravel Guide](https://docs.railway.app/guides/laravel)
-   [Fly.io Laravel Guide](https://fly.io/docs/laravel/)
