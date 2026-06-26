<?php

namespace Database\Factories;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 5000);
        $tax = round($subtotal * 0.15, 2);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'order_id' => Order::factory(),
            'buyer_company_id' => Company::factory(),
            'supplier_company_id' => Company::factory()->supplier(),
            'status' => InvoiceStatus::GENERATED,
            'payment_status' => PaymentStatus::PENDING,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $tax,
            'paid_amount' => 0,
            'file_path' => 'invoices/'.fake()->uuid().'.txt',
            'file_format' => 'txt',
            'generated_at' => now(),
        ];
    }
}
