<?php

namespace App\Domain\Deliveries;

use App\Models\User;
use Illuminate\Support\Collection;

class DriverLocationService
{
    /**
     * Record a driver's current GPS position (a ping from the driver app) and push
     * it to the supplier feed over WebSockets for live tracking.
     */
    public function ping(User $driver, float $latitude, float $longitude): void
    {
        \App\Models\DriverLocation::query()->create([
            'driver_id' => $driver->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => $now = now(),
        ]);

        broadcast(new \App\Events\DriverLocationUpdated($driver, $latitude, $longitude, $now));
    }

    /**
     * The driver's latest known position, or null when they have never pinged.
     *
     * @return array{latitude: float, longitude: float, recordedAt: string}|null
     */
    public function latest(User $driver): ?array
    {
        /** @var \App\Models\DriverLocation|null $location */
        $location = $driver->locations()->latest('recorded_at')->first();

        if ($location === null) {
            return null;
        }

        return [
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'recordedAt' => $location->recorded_at->toIso8601String(),
        ];
    }

    /**
     * Latest position per driver for a set of drivers (fleet view), keyed by id.
     *
     * @param  Collection<int, User>  $drivers
     * @return array<int, array{latitude: float, longitude: float, recordedAt: string}>
     */
    public function latestForMany(Collection $drivers): array
    {
        $result = [];

        foreach ($drivers as $driver) {
            $latest = $this->latest($driver);
            if ($latest !== null) {
                $result[$driver->id] = $latest;
            }
        }

        return $result;
    }
}
