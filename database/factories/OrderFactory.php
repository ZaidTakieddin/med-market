<?php

namespace Database\Factories;

use App\Constants\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'buyer_company_id' => Company::factory()->pharmacy(),
            'supplier_company_id' => Company::factory()->supplier(),
            'created_by' => User::factory()->buyer(),
            'status' => OrderStatus::PENDING,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'payment_method' => 'cash',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::PENDING]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::ACCEPTED, 'accepted_at' => now()]);
    }

    public function prepared(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::PREPARED, 'prepared_at' => now()]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::SHIPPED, 'shipped_at' => now()]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::DELIVERED, 'delivered_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::CANCELLED, 'cancelled_at' => now()]);
    }
}
