<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security settings
    |--------------------------------------------------------------------------
    */

    // Comma-separated IPs allowed to reach admin-sensitive endpoints
    // (/users, /analytics/*, review moderation). Empty = disabled.
    'admin_ip_allowlist' => env('ADMIN_IP_ALLOWLIST', ''),

];
