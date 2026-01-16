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

    SESSION_DRIVER=database
    QUEUE_CONNECTION=database
    CACHE_STORE=file
    ```

    **Important**:

    - Generate `APP_KEY` with: `php artisan key:generate --show`
    - Use Neon's **pooled** connection (`-pooler` suffix) for `DB_HOST`
    - Set `APP_URL` to your Render URL (HTTPS)

4. **Deploy**

    Render will automatically:

    - Build Docker image
    - Run migrations
    - Cache configuration
    - Generate Swagger docs
    - Start the application

### Post-Deployment

-   Health check: `GET /up`
-   API docs: `GET /api/documentation`
-   API base: `GET /api/v1`

## 🔧 Dockerfile Details

The included `Dockerfile`:

-   Uses PHP 8.4 with Apache
-   Installs required PHP extensions (PostgreSQL, GD, etc.)
-   Runs migrations automatically on startup
-   Caches configuration, routes, and views
-   Generates Swagger documentation
-   Configures Apache for Laravel

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

-   **Neon Database**: Use pooled connection (`-pooler`) for production
-   **HTTPS**: Render provides HTTPS automatically
-   **Auto-Deploy**: Render auto-deploys on Git push
-   **Logs**: View logs in Render dashboard

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
```
