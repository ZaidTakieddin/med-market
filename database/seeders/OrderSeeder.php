<?php

namespace Database\Seeders;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderCreationService;
use App\Domain\Orders\OrderStateTransitionService;
use App\Jobs\GenerateInvoiceJob;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function __construct(
        private OrderCreationService $orderCreationService,
        private OrderStateTransitionService $orderStateTransitionService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyers = Company::where('type', 'buyer')->get();
        $suppliers = Company::where('type', 'supplier')->get();
        $buyerUsers = User::where('role', 'buyer')->get();

        if ($buyers->isEmpty() || $suppliers->isEmpty() || $buyerUsers->isEmpty()) {
            $this->command->warn('لا توجد بيانات كافية. يرجى تشغيل CompanySeeder و UserSeeder أولاً.');
            return;
        }

        $buyer1 = $buyers->first();
        $buyer2 = $buyers->count() > 1 ? $buyers->skip(1)->first() : $buyer1;
        $buyer3 = $buyers->count() > 2 ? $buyers->skip(2)->first() : $buyer1;
        // Ensure orders attach to supplier companies with id 2 and 4
        $supplier1 = $suppliers->first();
        $supplier2 = $suppliers->firstWhere('id', 2) ?? $suppliers->skip(1)->first() ?? $supplier1;
        $supplier3 = $suppliers->skip(2)->first() ?? $supplier1;
        $supplier4 = $suppliers->firstWhere('id', 4) ?? $suppliers->skip(3)->first() ?? $supplier1;
        $buyerUser1 = $buyerUsers->first();
        $buyerUser2 = $buyerUsers->count() > 1 ? $buyerUsers->skip(1)->first() : $buyerUser1;
        $buyerUser3 = $buyerUsers->count() > 2 ? $buyerUsers->skip(2)->first() : $buyerUser1;

        // Get products from each supplier (2 and 4 must have orders)
        $productsSupplier1 = Product::where('supplier_company_id', $supplier1->id)
            ->where('is_active', true)
            ->limit(3)
            ->get()
            ->map(fn($product) => $product->fresh());
        $productsSupplier2 = Product::where('supplier_company_id', $supplier2->id)
            ->where('is_active', true)
            ->limit(2)
            ->get()
            ->map(fn($product) => $product->fresh());
        $productsSupplier3 = Product::where('supplier_company_id', $supplier3->id)
            ->where('is_active', true)
            ->limit(2)
            ->get()
            ->map(fn($product) => $product->fresh());
        $productsSupplier4 = Product::where('supplier_company_id', $supplier4->id)
            ->where('is_active', true)
            ->limit(2)
            ->get()
            ->map(fn($product) => $product->fresh());

        if ($productsSupplier1->isEmpty()) {
            $this->command->warn('لا توجد منتجات. يرجى تشغيل ProductSeeder أولاً.');
            return;
        }

        $product1 = $productsSupplier1->first();
        $product2 = $productsSupplier1->skip(1)->first() ?? $product1;
        $product2a = $productsSupplier2->first() ?? $product1;
        $product3a = $productsSupplier3->first() ?? $product1;
        $product4a = $productsSupplier4->first() ?? $product1;

        $orders = [
            [
                'buyer_company_id' => $buyer1->id,
                'supplier_company_id' => $supplier1->id,
                'created_by' => $buyerUser1->id,
                'status' => OrderStatus::PENDING,
                'items' => [
                    [
                        'product_id' => $product1->id,
                        'quantity' => 10,
                        'unit_price' => (float) $product1->price,
                    ],
                ],
                'notes' => 'طلب عاجل',
            ],
            [
                'buyer_company_id' => $buyer1->id,
                'supplier_company_id' => $supplier1->id,
                'created_by' => $buyerUser1->id,
                'status' => OrderStatus::ACCEPTED, // Will be accepted to generate invoice
                'items' => [
                    [
                        'product_id' => $product1->id,
                        'quantity' => 5,
                        'unit_price' => (float) $product1->price,
                    ],
                    [
                        'product_id' => $product2->id,
                        'quantity' => 5,
                        'unit_price' => (float) $product2->price,
                    ],
                ],
                'notes' => 'طلب منتظم',
            ],
            [
                'buyer_company_id' => $buyer2->id,
                'supplier_company_id' => $supplier2->id,
                'created_by' => $buyerUser2->id,
                'status' => OrderStatus::PENDING,
                'items' => [
                    [
                        'product_id' => $product2a->id,
                        'quantity' => 3,
                        'unit_price' => (float) $product2a->price,
                    ],
                ],
                'notes' => 'طلب من المورد الثاني',
            ],
            [
                'buyer_company_id' => $buyer3->id,
                'supplier_company_id' => $supplier3->id,
                'created_by' => $buyerUser3->id,
                'status' => OrderStatus::PENDING,
                'items' => [
                    [
                        'product_id' => $product3a->id,
                        'quantity' => 2,
                        'unit_price' => (float) $product3a->price,
                    ],
                ],
                'notes' => 'طلب من المورد الثالث',
            ],
            [
                'buyer_company_id' => $buyer1->id,
                'supplier_company_id' => $supplier4->id,
                'created_by' => $buyerUser1->id,
                'status' => OrderStatus::PENDING,
                'items' => [
                    [
                        'product_id' => $product4a->id,
                        'quantity' => 4,
                        'unit_price' => (float) $product4a->price,
                    ],
                ],
                'notes' => 'طلب من المورد الرابع (supplier id 4)',
            ],
        ];

        foreach ($orders as $index => $orderData) {
            $items = $orderData['items'];
            $targetStatus = $orderData['status'];
            unset($orderData['items']);
            unset($orderData['status']);

            try {
                $order = $this->orderCreationService->create(
                    buyerCompanyId: $orderData['buyer_company_id'],
                    supplierCompanyId: $orderData['supplier_company_id'],
                    createdBy: $orderData['created_by'],
                    items: $items,
                    notes: $orderData['notes'] ?? null,
                    taxRate: 15
                );

                // Update status if needed (for accepted order) using OrderStateTransitionService
                if ($targetStatus === OrderStatus::ACCEPTED) {
                    $this->orderStateTransitionService->transition(
                        order: $order,
                        newStatus: OrderStatus::ACCEPTED,
                        userId: $orderData['created_by'],
                        notes: 'Order accepted via seeder'
                    );

                    // Assign a driver from the supplier company to the order
                    $driver = User::where('role', 'driver')
                        ->where('company_id', $order->supplier_company_id)
                        ->first();
                    if ($driver) {
                        $order->update(['driver_id' => $driver->id]);
                    }

                    // Generate invoice synchronously for seeder (queue workers may not be running)
                    // Refresh order to get latest data
                    $order->refresh();
                    $order->load(['buyerCompany', 'supplierCompany', 'creator', 'items.product', 'driver']);
                    GenerateInvoiceJob::dispatchSync($order);
                }

                $orderNum = $index + 1;
                $invoiceInfo = '';
                if ($targetStatus === OrderStatus::ACCEPTED) {
                    $invoice = \App\Models\Invoice::where('order_id', $order->id)->first();
                    $invoiceInfo = $invoice ? " (فاتورة: {$invoice->invoice_number})" : '';
                }
                $this->command->info("تم إنشاء الطلب #{$orderNum} بنجاح: {$order->order_number}{$invoiceInfo}");
            } catch (\Exception $e) {
                $orderNum = $index + 1;
                $this->command->error("فشل إنشاء الطلب #{$orderNum}: " . $e->getMessage());
            }
        }

        $this->command->info('تم إنشاء الطلبات بنجاح.');
    }
}
