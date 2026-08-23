<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery estimation
    |--------------------------------------------------------------------------
    |
    | Average urban speed (km/h) used to convert supplier→buyer distance into a
    | travel-time estimate, plus a fixed handling buffer in minutes added on top
    | of every estimate.
    |
    */

    'avg_speed_kmh' => env('DELIVERY_AVG_SPEED_KMH', 30.0),

    'handling_buffer_minutes' => env('DELIVERY_HANDLING_BUFFER_MINUTES', 45),

];
