<?php

namespace Database\Factories;

use App\Constants\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusLog>
 */
class OrderStatusLogFactory extends Factory
{
    protected $model = OrderStatusLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'status_from' => OrderStatus::PENDING,
            'status_to' => OrderStatus::ACCEPTED,
            'notes' => fake()->sentence(),
            'metadata' => null,
        ];
    }
}
