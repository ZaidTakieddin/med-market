#!/bin/bash

# Queue worker startup script for Render/Docker (runs inside the web container).
#
# `queue:work` intentionally EXITS on --max-time (a memory-leak guard) and on
# fatal errors. The loop below restarts it so the worker stays alive for the life
# of the container — without it, the worker would stop after ~1 hour (or on the
# first crash) and silently never resume.

echo "Starting queue workers..."

# Give the main application a moment to finish booting.
sleep 5

# Process every queue the app dispatches to (priority order).
# `imports` MUST be included — bulk product imports are dispatched there.
while true; do
    php artisan queue:work database \
        --queue=notifications,invoices,alerts,exports,imports \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --timeout=60 \
        --memory=128 \
        --verbose || true

    echo "Queue worker exited (max-time reached or crash); restarting in 5s..."
    sleep 5
done
