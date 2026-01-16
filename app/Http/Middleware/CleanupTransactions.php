<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to cleanup failed transactions before processing requests.
 * 
 * This is especially important when using database cache with PostgreSQL,
 * as failed transactions can leave the connection in an aborted state,
 * causing subsequent cache operations (like throttling) to fail.
 */
class CleanupTransactions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->cleanupFailedTransactions();

        return $next($request);
    }

    /**
     * Cleanup failed transactions on all database connections.
     */
    private function cleanupFailedTransactions(): void
    {
        $connections = array_keys(config('database.connections', []));
        
        foreach ($connections as $connection) {
            try {
                // Check if we're in a transaction
                if (DB::connection($connection)->transactionLevel() > 0) {
                    // Try to rollback - this will clear any aborted transaction state
                    DB::connection($connection)->rollBack();
                }
            } catch (\Exception $e) {
                if (config('app.debug')) {
                    Log::debug('Transaction cleanup error', [
                        'connection' => $connection,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
