# Deployment Guide - Med Market

Quick deployment guide for Med Market Laravel application.

## 🚀 Deploy to Render (Recommended)

### Prerequisites

-   GitHub/GitLab repository with your code
-   Neon PostgreSQL database configured
-   Render account ([render.com](https://render.com))

### Steps

1. **Create Web Service**

    - Go to [Render Dashboard](https://dashboard.render.com)
    - Click "New +" → "Web Service"
    - Connect your Git repository

2. **Configure Service**

    - **Environment**: Docker
    - **Dockerfile**: Auto-detected (`Dockerfile` in root)
    - **Docker Context**: `.` (root directory)

3. **Set Environment Variables**

    Add these in Render's Environment tab:

    ```env
    APP_NAME="Med Market"
    APP_ENV=production
    APP_KEY=base64:your-generated-key-here
    APP_DEBUG=false
    APP_URL=https://your-app-name.onrender.com

    DB_CONNECTION=pgsql
    DB_HOST=your-neon-pooler-host.c-2.eu-central-1.aws.neon.tech
    DB_PORT=5432
    DB_DATABASE=neondb
    DB_USERNAME=neondb_owner
    DB_PASSWORD=your-password
    DB_SSLMODE=require

    # Queue Database Connection (Unpooled - Required for queue workers)
    # Use Neon's direct (non-pooler) endpoint for queue workers
    # Queue workers MUST use unpooled connection for reliable transactions
    DB_QUEUE_CONNECTION=pgsql_queue
    DB_QUEUE_HOST=your-neon-direct-host.c-2.eu-central-1.aws.neon.tech
    DB_QUEUE_PORT=5432
    DB_QUEUE_DATABASE=neondb
    DB_QUEUE_USERNAME=neondb_owner
    DB_QUEUE_PASSWORD=your-password
    DB_QUEUE_TABLE=jobs
    DB_QUEUE=default
    DB_QUEUE_RETRY_AFTER=90

    # Enable queue worker in Docker container (for development)
    # Set to false for production when using separate Background Worker service
    ENABLE_QUEUE_WORKER=true

    SESSION_DRIVER=database
    QUEUE_CONNECTION=database
    CACHE_STORE=file

    # Optional: Enable automatic migrations on container start (not recommended for production)
    # RUN_MIGRATIONS=true
    ```

    **Important**:

    - Generate `APP_KEY` with: `php artisan key:generate --show`
    - Use Neon's **pooled** connection (`-pooler` suffix) for `DB_HOST` (Web Service)
    - Use Neon's **direct** connection (without `-pooler` suffix) for `DB_QUEUE_HOST` (Queue Workers)
    - Set `APP_URL` to your Render URL (HTTPS)
    - Set `DB_QUEUE_CONNECTION=pgsql_queue` to use the unpooled connection for queue workers

4. **Deploy**

    Render will automatically:

    - Build Docker image
    - Cache configuration
    - Generate Swagger docs
    - Start the application

    **Note**: Migrations are NOT run automatically by default (safer for production). You have two options:

    **Option A: Run migrations manually** (Recommended for production):
    ```bash
    # SSH into your Render service or use Render Shell
    php artisan migrate --force
    ```

    **Option B: Enable automatic migrations** (For development/testing):
    Add environment variable:
    ```env
    RUN_MIGRATIONS=true
    ```
    This will run migrations automatically on every container start (not recommended for production).

### Post-Deployment

1. **Run Database Seeders** (if needed):
   ```bash
   php artisan db:seed
   ```
   This will seed:
   - Cities and regions
   - Companies (suppliers and buyers)
   - Categories and manufacturers
   - Users, products, offers, orders

2. **Sync Supplier Categories and Manufacturers** (if needed):
   ```bash
   php artisan suppliers:sync-categories-manufacturers
   ```
   This command syncs supplier categories and manufacturers from their products. Usually not needed as ProductObserver handles this automatically, but useful for initial data migration or fixing inconsistencies.

3. **Verify Deployment**:
   - Health check: `GET /up`
   - API docs: `GET /api/documentation`
   - API base: `GET /api/v1`

## 🔧 Dockerfile Details

The included `Dockerfile`:

-   Uses PHP 8.4 with Apache
-   Installs required PHP extensions (PostgreSQL, GD, etc.)
-   Caches configuration, routes, and views
-   Generates Swagger documentation
-   Configures Apache for Laravel
-   Includes queue worker script for background job processing
-   Migrations can be enabled via `RUN_MIGRATIONS=true` environment variable (disabled by default for production safety)

## 📦 Queue Workers

### Development Phase (Single Container)

For development, queue workers run in the same Docker container as the web server:

-   Set `ENABLE_QUEUE_WORKER=true` in environment variables
-   Queue worker starts automatically in the background
-   Processes all queues: `notifications`, `invoices`, `alerts`, `exports`

### Production (Recommended: Separate Background Worker)

For production, create a separate **Background Worker** service on Render:

1. **Create Background Worker Service**

    - Go to Render Dashboard → "New +" → "Background Worker"
    - Connect same Git repository
    - Use same Dockerfile

2. **Configure Background Worker**

    - **Command**: `php artisan queue:work database --queue=notifications,invoices,alerts,exports --sleep=3 --tries=3 --max-time=3600`
    - **Environment Variables**: Same as Web Service (copy from Web Service)
    - **Instance Type**: Choose based on expected load

3. **Set `ENABLE_QUEUE_WORKER=false`** in Web Service (to avoid duplicate workers)

### Database Connection for Queue Workers

**Important**: Queue workers **MUST** use **unpooled** (direct) connection:

-   **Why**: Queue workers maintain long-lived connections and need reliable transaction handling
-   **Pooled connections** are for short-lived HTTP requests
-   **Unpooled connections** handle transactions better for background jobs

**Configuration**:

-   Use Neon's **direct** endpoint (without `-pooler` suffix) for `DB_QUEUE_HOST`
-   Example: `ep-lingering-bush-ag3n7o6u.c-2.eu-central-1.aws.neon.tech` (not `-pooler.c-2...`)
-   Set `DB_QUEUE_HOST`, `DB_QUEUE_PORT`, `DB_QUEUE_DATABASE`, `DB_QUEUE_USERNAME`, `DB_QUEUE_PASSWORD`

See [QUEUE_WORKERS.md](./QUEUE_WORKERS.md) for detailed queue worker setup guide.

## 🔐 Security Checklist

-   [ ] `APP_DEBUG=false`
-   [ ] `APP_ENV=production`
-   [ ] `APP_KEY` generated and set
-   [ ] `APP_URL` set to HTTPS URL
-   [ ] Database credentials secure
-   [ ] All environment variables set

## 🐛 Troubleshooting

### 500 Error

-   Temporarily set `APP_DEBUG=true` to see errors
-   Check all environment variables are set
-   Verify database connection

### Database Connection Failed

-   Verify Neon connection string (use pooled endpoint)
-   Check `DB_SSLMODE=require`
-   Verify credentials

### Swagger Mixed Content Error

-   Ensure `APP_URL` is set to HTTPS URL
-   Verify proxy trust is configured (already in `bootstrap/app.php`)

### Storage Permission Errors

-   Dockerfile handles permissions automatically
-   If issues persist, check Render logs

## 📝 Notes

-   **Neon Database**:
    -   Use **pooled** connection (`-pooler`) for Web Service (`DB_HOST`)
    -   Use **unpooled** connection (direct) for Queue Workers (`DB_QUEUE_HOST`)
-   **HTTPS**: Render provides HTTPS automatically
-   **Auto-Deploy**: Render auto-deploys on Git push
-   **Logs**: View logs in Render dashboard
-   **Queue Workers**: For production, use separate Background Worker service

## 🔗 Quick Commands

```bash
# Generate APP_KEY
php artisan key:generate --show

# Clear caches (if needed)
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Regenerate Swagger docs
php artisan l5-swagger:generate

# Sync supplier categories and manufacturers (if needed)
php artisan suppliers:sync-categories-manufacturers

# Sync specific supplier
php artisan suppliers:sync-categories-manufacturers --supplier-id=1
```
