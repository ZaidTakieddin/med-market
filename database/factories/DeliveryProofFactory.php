<?php

namespace Database\Factories;

use App\Models\DeliveryProof;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryProof>
 */
class DeliveryProofFactory extends Factory
{
    protected $model = DeliveryProof::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'type' => 'photo',
            'disk' => 'public',
            'path' => 'delivery-proofs/'.fake()->uuid().'.jpg',
            'url' => null,
            'notes' => null,
        ];
    }
}
