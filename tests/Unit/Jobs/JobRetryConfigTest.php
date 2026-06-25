<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExportOrdersJob;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\LowStockAlertJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class JobRetryConfigTest extends TestCase
{
    /**
     * Every queued job must declare retry/backoff/timeout, and its timeout must stay
     * below the database queue's retry_after — otherwise a slow job is released for a
     * parallel retry while still running (double-processing). This test guards that.
     *
     * @return list<object{tries: int, backoff: int, timeout: int}>
     */
    private function jobs(): array
    {
        return [
            new GenerateInvoiceJob(new Order),
            new SendOrderNotificationJob(new Order, 'pending', 'accepted'),
            new LowStockAlertJob(new Product),
            new ExportOrdersJob(new User),
        ];
    }

    public function test_jobs_declare_retry_backoff_and_timeout(): void
    {
        foreach ($this->jobs() as $job) {
            $name = $job::class;
            $this->assertGreaterThanOrEqual(1, $job->tries, "{$name} must allow at least one attempt");
            $this->assertGreaterThan(0, $job->backoff, "{$name} must define a retry backoff");
            $this->assertGreaterThan(0, $job->timeout, "{$name} must define a timeout");
        }
    }

    public function test_job_timeout_stays_below_queue_retry_after(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');

        foreach ($this->jobs() as $job) {
            $this->assertLessThan(
                $retryAfter,
                $job->timeout,
                $job::class." timeout ({$job->timeout}s) must be below retry_after ({$retryAfter}s) to avoid overlapping retries"
            );
        }
    }
}
