<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminIpAllowlist
{
    /**
     * Restrict admin-sensitive routes to an allowlisted IP set when configured.
     * Empty/absent `ADMIN_IP_ALLOWLIST` disables the restriction entirely.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = array_filter(array_map(
            fn (string $ip): string => trim($ip),
            explode(',', (string) config('security.admin_ip_allowlist', '')),
        ));

        if ($allowlist !== [] && ! in_array($request->ip(), $allowlist, true)) {
            abort(403, 'This endpoint is not available from your network.');
        }

        return $next($request);
    }
}
