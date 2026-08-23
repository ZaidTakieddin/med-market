# Queue Workers Setup Guide

This document explains how to set up and run Laravel queue workers for background job processing in the Med Market B2B system.

## Overview

The application uses Laravel's queue system to process background jobs asynchronously. This improves response times and allows heavy operations to run in the background.

## Available Jobs

The following background jobs are implemented:

1. **SendOrderNotificationJob** - Sends order-status notifications (mail + in-app database channel) to buyer-side and supplier-side users
   - Queue: `notifications`
   - Triggers: Automatically dispatched from `OrderStateTransitionService`

2. **GenerateInvoiceJob** - Generates invoices for orders
   - Queue: `invoices`
   - Triggers: Automatically dispatched when order status changes to `accepted`

3. **LowStockAlertJob** - Sends low-stock alerts (mail + in-app database channel) to the supplying company's users
   - Queue: `alerts`
   - Triggers: Automatically dispatched from `StockMovementObserver` when low stock detected

4. **ExportOrdersJob** - Generates order export files (CSV/JSON) for a tracked `order_exports` record
   - Queue: `exports`
   - Triggers: Dispatched from `OrderExportService` via `POST /orders/exports`

5. **ImportProductsJob** - Coordinates a bulk product import (Excel/CSV)
   - Queue: `imports`
   - Triggers: Dispatched from `ProductImportController@store` after upload
   - Reads the file in bounded-memory chunks (maatwebsite/excel); each chunk runs as its
     own short queue job, then a chained **FinalizeProductImportJob** runs the one-time
     supplier taxonomy sync and records the final status. See the
     [Products domain README](../app/Domain/Products/README.md#bulk-import-excelcsv).

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

## Running the WebSocket Server (Laravel Reverb)

Realtime features (#10) broadcast over WebSockets via **Laravel Reverb**
(`BROADCAST_CONNECTION=reverb`). Run it alongside the queue worker:

```bash
php artisan reverb:start              # defaults to 0.0.0.0:8080
php artisan reverb:start --host=0.0.0.0 --port=8081
```

- Required env: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
  (generate with `php artisan key:generate`-style randomness; frontends need the same
  APP_KEY/PUSHER_* values in their Echo config).
- Behind a proxy, terminate TLS at the edge and set `REVERB_SCHEME=https`,
  or route `/app/*` websocket upgrades to Reverb.
- Channels are tenant-scoped and authorized in `routes/channels.php`
  (`supplier.{companyId}`, `buyer.{companyId}`, `driver.{driverId}`,
  `presence-company.{companyId}`); clients authenticate with their Sanctum token at
  `POST /api/v1/broadcasting/auth`.

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
php artisan queue:work --queue=notifications,invoices,alerts,exports,imports
```

### Production

For production, use a process manager like Supervisor to keep workers running:

#### Supervisor Configuration

Create `/etc/supervisor/conf.d/med-market-worker.conf`:

```ini
[program:med-market-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/med-market/artisan queue:work database --queue=notifications,invoices,alerts,exports,imports --sleep=3 --tries=3 --max-time=3600
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
ExecStart=/usr/bin/php /path/to/med-market/artisan queue:work database --queue=notifications,invoices,alerts,exports,imports --sleep=3 --tries=3 --max-time=3600

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
    command: php artisan queue:work database --queue=notifications,invoices,alerts,exports,imports --sleep=3 --tries=3 --max-time=3600
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

**Note**: For Render deployment, queue workers are included in the Dockerfile. Set `ENABLE_QUEUE_WORKER=true` to enable them in the same container, or use a separate Background Worker service. The in-container worker (`docker/start-queue-worker.sh`) runs `queue:work` inside a **restart loop** — `queue:work` exits on `--max-time` (hourly, to release memory) and on fatal errors, and the loop relaunches it so it stays alive for the life of the container. It listens on **all** queues including `imports`.

### Render.com / Cloud Platforms

For Render.com or similar platforms:

**Option 1: Single Container (works on the FREE tier)**
- Set `ENABLE_QUEUE_WORKER=true` in Web Service environment variables
- Queue worker runs automatically in the same container, in a restart loop
- ⚠️ **Free tier caveat**: a free Web Service spins down after ~15 min idle, which
  also pauses the in-container worker. Keep it warm by pinging `/up` every few
  minutes — either the optional `.github/workflows/keepalive.yml` (public repos
  only; see its header re: Actions minutes) or a free external pinger like
  UptimeRobot. Backlogged jobs still run once the container wakes.

**Option 2: Production (Separate Background Worker)**
- Create a separate **Background Worker** service
- **Command**: `php artisan queue:work database --queue=notifications,invoices,alerts,exports,imports --sleep=3 --tries=3 --max-time=3600`
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
php artisan queue:monitor notifications,invoices,alerts,exports,imports

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

All jobs declare `$tries`, `$backoff`, **`$timeout`**, and a `failed()` handler:

- **SendOrderNotificationJob**: 3 tries, 60s backoff, 45s timeout
- **GenerateInvoiceJob**: 3 tries, 120s backoff, 60s timeout
- **LowStockAlertJob**: 2 tries, 300s backoff, 45s timeout
- **ExportOrdersJob**: 2 tries, 300s backoff, 75s timeout
- **ImportProductsJob**: 1 try, 60s backoff, 60s timeout
- **FinalizeProductImportJob**: 1 try, 60s backoff, 60s timeout

The import jobs use **1 try** on purpose: re-running the coordinator would re-dispatch the
chunk chain and double-import. The per-chunk read jobs (maatwebsite) also run with a single
attempt and a 60s timeout (below `retry_after`), so a chunk can never be re-run in parallel.

Every job's `$timeout` is kept **below the queue's `retry_after` (90s,
`DB_QUEUE_RETRY_AFTER`)** so a hung job is killed before another worker can grab it for
a parallel retry — `tests/Unit/Jobs/JobRetryConfigTest.php` enforces this invariant. If
you raise a job's `$timeout` at or above `retry_after`, raise `retry_after` too.

`GenerateInvoiceJob` is **idempotent** — it upserts the invoice by `order_id`, so a retry
never produces a duplicate invoice. Failed jobs are recorded in the `failed_jobs` table
(created by the consolidated jobs migration) and can be retried manually.

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

