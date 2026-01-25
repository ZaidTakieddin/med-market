#!/bin/bash
set -e

# Queue worker startup script for Render/Docker
# This script runs queue workers in the background

echo "Starting queue workers..."

# Wait a bit for the main application to be ready
sleep 5

# Start queue worker for all queues (priority order: notifications, invoices, alerts, exports)
# Using --sleep=3 to avoid excessive CPU usage when no jobs are available
# Using --tries=3 for retry attempts
# Using --max-time=3600 to restart worker every hour (prevents memory leaks)
php artisan queue:work database \
    --queue=notifications,invoices,alerts,exports \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --timeout=60 \
    --memory=128 \
    --verbose

