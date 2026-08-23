<?php

namespace App\Domain\Deliveries;

use App\Models\Order;
use Carbon\Carbon;

class EtaService
{
    /**
     * Estimate the delivery time for an order from the supplier/buyer coordinates
     * using a haversine distance and an average urban speed. Returns null when
     * either company lacks coordinates (the ETA is then simply unknown).
     */
    public function estimateForOrder(Order $order, Carbon $from): ?Carbon
    {
        $supplier = $order->supplierCompany;
        $buyer = $order->buyerCompany;

        if ($supplier?->latitude === null || $supplier->longitude === null
            || $buyer?->latitude === null || $buyer->longitude === null) {
            return null;
        }

        $distanceKm = $this->haversineKm(
            (float) $supplier->latitude,
            (float) $supplier->longitude,
            (float) $buyer->latitude,
            (float) $buyer->longitude,
        );

        $speed = max(1.0, (float) config('delivery.avg_speed_kmh', 30.0));
        $bufferMinutes = max(0, (int) config('delivery.handling_buffer_minutes', 45));

        $travelMinutes = (int) ceil(($distanceKm / $speed) * 60);

        return $from->copy()->addMinutes($travelMinutes + $bufferMinutes);
    }

    /**
     * Great-circle distance between two coordinates in kilometres.
     */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * asin(min(1.0, sqrt($a)));
    }
}
