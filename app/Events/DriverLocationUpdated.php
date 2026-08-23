<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Turns GPS pings into true live tracking: every ping is pushed to the owning
     * supplier's feed so dispatch screens update without polling.
     */
    public function __construct(
        public User $driver,
        public float $latitude,
        public float $longitude,
        public \Carbon\CarbonInterface $recordedAt
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("supplier.{$this->driver->company_id}");
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driver->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recorded_at' => $this->recordedAt->toIso8601String(),
        ];
    }
}
