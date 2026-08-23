<?php

namespace App\Domain\Orders;

use App\Constants\OrderStatus;
use App\Constants\PaymentTerms;
use App\Domain\Companies\CreditService;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCreationService
{
    public function __construct(
        private CreditService $creditService
    ) {}

    /**
     * Create a new order with items.
     *
     * @param  array<int, array<string, mixed>>  $items  Array of ['product_id', 'quantity', 'unit_price']
     *
     * @throws \InvalidArgumentException If validation fails or net-term credit is unavailable
     */
    public function create(
        int $buyerCompanyId,
        int $supplierCompanyId,
        int $createdBy,
        array $items,
        ?string $notes = null,
        float $taxRate = 0.0,
        ?string $paymentMethod = null,
        ?int $placedBySalesmanId = null
    ): Order {
        if (empty($items)) {
            throw new DomainException('Order must have at least one item');
        }

        // Check if we're already in a transaction (e.g., from seeder)
        // If so, don't wrap in another transaction to avoid Neon pooling issues
        $inTransaction = DB::transactionLevel() > 0;

        // Generate unique order number
        // If in transaction, generate inside transaction to avoid query issues
        if ($inTransaction) {
            return $this->createOrderInTransaction($buyerCompanyId, $supplierCompanyId, $createdBy, $items, $notes, $taxRate, $paymentMethod, $placedBySalesmanId);
        }

        return DB::transaction(function () use ($buyerCompanyId, $supplierCompanyId, $createdBy, $items, $notes, $taxRate, $paymentMethod, $placedBySalesmanId) {
            return $this->createOrderInTransaction($buyerCompanyId, $supplierCompanyId, $createdBy, $items, $notes, $taxRate, $paymentMethod, $placedBySalesmanId);
        });
    }

    /**
     * Create order within a transaction (handles uniqueness check inside transaction).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createOrderInTransaction(
        int $buyerCompanyId,
        int $supplierCompanyId,
        int $createdBy,
        array $items,
        ?string $notes,
        float $taxRate,
        ?string $paymentMethod,
        ?int $placedBySalesmanId
    ): Order {
        // Generate unique order number - rely on database constraint for uniqueness
        // Retry on duplicate key exception (very rare with random suffix)
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $orderNumber = $this->generateOrderNumber();

                return $this->createOrderWithoutTransaction($buyerCompanyId, $supplierCompanyId, $createdBy, $items, $notes, $taxRate, $orderNumber, $paymentMethod, $placedBySalesmanId);
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if it's a unique constraint violation
                if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'unique constraint') || str_contains($e->getMessage(), 'duplicate key')) {
                    $attempt++;
                    if ($attempt >= $maxAttempts) {
                        throw new \RuntimeException('Failed to generate unique order number after '.$maxAttempts.' attempts');
                    }

                    // Retry with new order number
                    continue;
                }
                // Re-throw if it's a different error
                throw $e;
            }
        }

        throw new \RuntimeException('Failed to create order after '.$maxAttempts.' attempts');
    }

    /**
     * Create order without wrapping in transaction (for use when already in transaction).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createOrderWithoutTransaction(
        int $buyerCompanyId,
        int $supplierCompanyId,
        int $createdBy,
        array $items,
        ?string $notes,
        float $taxRate,
        string $orderNumber,
        ?string $paymentMethod,
        ?int $placedBySalesmanId
    ): Order {
        foreach ($items as $item) {
            $product = \App\Models\Product::find((int) $item['product_id']);
            if (! $product || $product->supplier_company_id !== $supplierCompanyId) {
                throw new DomainException(
                    "Product {$item['product_id']} does not belong to supplier {$supplierCompanyId}"
                );
            }
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;

        // Fail fast on insufficient net-term credit before creating the order.
        if ($paymentMethod && $paymentMethod !== PaymentTerms::CASH) {
            $this->creditService->assertAvailable($buyerCompanyId, $supplierCompanyId, $totalAmount);
        }

        // Create order
        $order = Order::create([
            'order_number' => $orderNumber,
            'buyer_company_id' => $buyerCompanyId,
            'supplier_company_id' => $supplierCompanyId,
            'created_by' => $createdBy,
            'placed_by_salesman_id' => $placedBySalesmanId,
            'status' => OrderStatus::PENDING,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'notes' => $notes,
        ]);

        foreach ($items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
                'pricing_type' => $item['pricing_type'] ?? 'retail',
                'notes' => $item['notes'] ?? null,
                'applied_offer_id' => $item['applied_offer_id'] ?? null,
            ]);
        }

        $this->applyPaymentTerms($order, $buyerCompanyId, $supplierCompanyId, $paymentMethod);

        // Reload order with items
        return $order->load('items');
    }

    /**
     * Set the order's payment method. For net-term orders, reserve buyer credit with
     * the supplier (rejecting the order if there is no approved relationship or the
     * credit limit would be exceeded) and set the payment due date.
     */
    private function applyPaymentTerms(Order $order, int $buyerCompanyId, int $supplierCompanyId, ?string $paymentMethod): void
    {
        $paymentMethod = $paymentMethod ?: PaymentTerms::CASH;
        $order->payment_method = $paymentMethod;

        if ($paymentMethod !== PaymentTerms::CASH) {
            $relationship = $this->creditService->reserve($buyerCompanyId, $supplierCompanyId, (float) $order->total_amount);
            $order->payment_due_date = $relationship->calculateDueDate(now());
        }

        $order->save();
    }

    /**
     * Generate a unique order number.
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = Str::upper(Str::random(6));

        return "{$prefix}-{$date}-{$random}";
    }
}
