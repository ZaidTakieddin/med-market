# Queue Workers Setup Guide

This document explains how to set up and run Laravel queue workers for background job processing in the Med Market B2B system.

## Overview

The application uses Laravel's queue system to process background jobs asynchronously. This improves response times and allows heavy operations to run in the background.

## Available Jobs

The following background jobs are implemented:

1. **SendOrderNotificationJob** - Sends email notifications when order status changes
   - Queue: `notifications`
   - Triggers: Automatically dispatched from `OrderStateTransitionService`

2. **GenerateInvoiceJob** - Generates invoices for orders
   - Queue: `invoices`
   - Triggers: Automatically dispatched when order status changes to `accepted`

3. **LowStockAlertJob** - Sends alerts when product stock is low
   - Queue: `alerts`
   - Triggers: Automatically dispatched from `StockMovementObserver` when low stock detected

4. **ExportOrdersJob** - Generates order export files (CSV/JSON)
   - Queue: `exports`
   - Triggers: Manually dispatched from controllers/endpoints

## Queue Configuration

The default queue connection is configured in `config/queue.php`:

```php
'default' => env('QUEUE_CONNECTION', 'database'),
```

### Available Queue Drivers

- **database** (default) - Uses database table `jobs` for queue storage
- **sync** - Runs jobs synchronously (for development/testing)
- **redis** - Uses Redis for queue storage (recommended for production)
- **sqs** - Amazon SQS (for AWS deployments)

### Environment Variables

Add to your `.env` file:

```env
# Queue Connection
QUEUE_CONNECTION=database

# Database Queue Connection (if using database driver)
# Queue workers MUST use unpooled (direct) connection for reliable transactions
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

# Redis Queue (if using redis driver)
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
```

**Important Notes**:

- **DB_QUEUE_HOST**: Use Neon's **direct** (unpooled) endpoint (without `-pooler` suffix)
- **DB_QUEUE_CONNECTION**: Set to `pgsql_queue` to use the unpooled connection (defaults to this)
- **ENABLE_QUEUE_WORKER**: Set to `true` for development (single container), `false` for production (separate Background Worker)

## Running Queue Workers

### Development (Local)

Run a single queue worker:

```bash
php artisan queue:work
```

Run worker for specific queue:

```bash
php artisan queue:work --queue=notifications
php artisan queue:work --queue=invoices
php artisan queue:work --queue=alerts
php artisan queue:work --queue=exports
```

Run worker for multiple queues (priority order):

```bash
php artisan queue:work --queue=notifications,invoices,alerts,exports
```

### Production

For production, use a process manager like Supervisor to keep workers running:

#### Supervisor Configuration

Create `/etc/supervisor/conf.d/med-market-worker.conf`:

```ini
[program:med-market-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/med-market/artisan queue:work database --queue=notifications,invoices,alerts,exports --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/med-market/storage/logs/worker.log
stopwaitsecs=3600
```

Then reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start med-market-worker:*
```

#### Systemd Service (Alternative)

Create `/etc/systemd/system/med-market-worker.service`:

```ini
[Unit]
Description=Med Market Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/med-market/artisan queue:work database --queue=notifications,invoices,alerts,exports --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable med-market-worker
sudo systemctl start med-market-worker
```

### Docker/Container

If using Docker, add queue worker to your `docker-compose.yml`:

```yaml
services:
  queue-worker:
    build: .
    command: php artisan queue:work database --queue=notifications,invoices,alerts,exports --sleep=3 --tries=3 --max-time=3600
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    restart: unless-stopped
    environment:
      - DB_QUEUE_HOST=${DB_QUEUE_HOST}
      - DB_QUEUE_PORT=${DB_QUEUE_PORT}
      - DB_QUEUE_DATABASE=${DB_QUEUE_DATABASE}
      - DB_QUEUE_USERNAME=${DB_QUEUE_USERNAME}
      - DB_QUEUE_PASSWORD=${DB_QUEUE_PASSWORD}
      - DB_QUEUE_CONNECTION=${DB_QUEUE_CONNECTION:-pgsql_queue}
```

**Note**: For Render deployment, queue workers are included in the Dockerfile. Set `ENABLE_QUEUE_WORKER=true` to enable them in the same container, or use a separate Background Worker service.

### Render.com / Cloud Platforms

For Render.com or similar platforms:

**Option 1: Development (Single Container)**
- Set `ENABLE_QUEUE_WORKER=true` in Web Service environment variables
- Queue worker runs automatically in the same container

**Option 2: Production (Separate Background Worker)**
- Create a separate **Background Worker** service
- **Command**: `php artisan queue:work database --queue=notifications,invoices,alerts,exports --sleep=3 --tries=3 --max-time=3600`
- **Environment Variables**: Copy all from Web Service, including:
  - `DB_QUEUE_CONNECTION=pgsql_queue`
  - `DB_QUEUE_HOST` (unpooled endpoint)
  - `DB_QUEUE_PORT`, `DB_QUEUE_DATABASE`, `DB_QUEUE_USERNAME`, `DB_QUEUE_PASSWORD`
  - `DB_QUEUE_TABLE`, `DB_QUEUE`, `DB_QUEUE_RETRY_AFTER` (optional)
  - Set `ENABLE_QUEUE_WORKER=false` in Web Service to avoid duplicate workers
- **Instance Type**: Choose based on expected load
- **Auto-Deploy**: Enable if you want automatic deployments

## Queue Worker Options

Common options for `queue:work`:

- `--queue=name` - Process specific queue
- `--tries=3` - Number of retry attempts
- `--timeout=60` - Job timeout in seconds
- `--sleep=3` - Seconds to sleep when no jobs available
- `--max-time=3600` - Maximum time worker runs before restart
- `--memory=128` - Memory limit in MB
- `--stop-when-empty` - Stop when queue is empty (for testing)

## Monitoring Queue Workers

### Check Queue Status

```bash
# List all queues and their job counts
php artisan queue:monitor notifications,invoices,alerts,exports

# Check failed jobs
php artisan queue:failed
```

### View Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job-id}

# Retry all failed jobs
php artisan queue:retry all

# Delete failed job
php artisan queue:forget {job-id}

# Flush all failed jobs
php artisan queue:flush
```

### Logs

Queue worker logs are written to `storage/logs/laravel.log`. Monitor with:

```bash
tail -f storage/logs/laravel.log
```

## Job Retry Logic

All jobs implement retry logic:

- **SendOrderNotificationJob**: 3 tries, 60s backoff
- **GenerateInvoiceJob**: 3 tries, 120s backoff
- **LowStockAlertJob**: 2 tries, 300s backoff
- **ExportOrdersJob**: 2 tries, 300s backoff

Failed jobs are logged and can be retried manually.

## Best Practices

1. **Use Unpooled Connection**: Queue workers MUST use unpooled (direct) database connection for reliable transactions
   - Set `DB_QUEUE_HOST` to Neon's direct endpoint (without `-pooler` suffix)
   - Set `DB_QUEUE_CONNECTION=pgsql_queue` to use the unpooled connection
2. **Use Separate Queues**: Different queues for different job types allow better control and prioritization
3. **Monitor Workers**: Set up monitoring/alerts for worker failures
4. **Scale Workers**: Run multiple workers for high-traffic queues
5. **Use Redis in Production**: Database queue is fine for development, but Redis is recommended for production
6. **Set Appropriate Timeouts**: Ensure timeouts are sufficient for job execution
7. **Handle Failures**: Monitor failed jobs and investigate failures
8. **Restart Workers**: Restart workers after code deployments
9. **Separate Workers in Production**: Use separate Background Worker service in production instead of running in web container

## Troubleshooting

### Workers Not Processing Jobs

1. Check if workers are running: `ps aux | grep queue:work`
2. Check queue table: `SELECT * FROM jobs LIMIT 10;`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Restart workers: `php artisan queue:restart`

### Jobs Failing

1. Check failed jobs: `php artisan queue:failed`
2. Review error logs: `storage/logs/laravel.log`
3. Check job payload: Inspect `payload` column in `failed_jobs` table
4. Retry failed jobs: `php artisan queue:retry all`

### High Memory Usage

1. Reduce `--memory` limit
2. Reduce `--max-time` to restart workers more frequently
3. Optimize job code to use less memory
4. Use `--stop-when-empty` for batch processing

## Testing Jobs

To test jobs synchronously (without queue):

```env
QUEUE_CONNECTION=sync
```

Or dispatch jobs synchronously in tests:

```php
Queue::fake();

// Dispatch job
SendOrderNotificationJob::dispatch($order, $oldStatus, $newStatus);

// Assert job was dispatched
Queue::assertPushed(SendOrderNotificationJob::class);
```

## Production Checklist

- [ ] Configure queue connection (Redis recommended, or database with unpooled connection)
- [ ] Set `DB_QUEUE_CONNECTION=pgsql_queue` (or configure custom connection)
- [ ] Set `DB_QUEUE_HOST` to unpooled (direct) endpoint
- [ ] Set `DB_QUEUE_PORT`, `DB_QUEUE_DATABASE`, `DB_QUEUE_USERNAME`, `DB_QUEUE_PASSWORD`
- [ ] Set up Supervisor or systemd service (or separate Background Worker on Render)
- [ ] Configure appropriate number of workers
- [ ] Set `ENABLE_QUEUE_WORKER=false` in Web Service if using separate Background Worker
- [ ] Set up monitoring/alerts
- [ ] Configure log rotation
- [ ] Test job processing
- [ ] Set up failed job notifications
- [ ] Document worker restart procedures
- [ ] Set up health checks

