<?php

namespace Database\Seeders;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderCreationService;
use App\Domain\Orders\OrderStateTransitionService;
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
        $supplier1 = $suppliers->first();
        $buyerUser1 = $buyerUsers->first();

        // Get products from supplier1 and refresh to ensure they're loaded
        $products = Product::where('supplier_company_id', $supplier1->id)
            ->where('is_active', true)
            ->limit(3)
            ->get()
            ->map(fn($product) => $product->fresh());

        if ($products->isEmpty()) {
            $this->command->warn('لا توجد منتجات. يرجى تشغيل ProductSeeder أولاً.');
            return;
        }

        // Create sample orders with different statuses
        $product1 = $products->first();
        $product2 = $products->skip(1)->first() ?? $product1;

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
                'status' => OrderStatus::PENDING, // Start as pending, will be updated
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
                }

                $orderNum = $index + 1;
                $this->command->info("تم إنشاء الطلب #{$orderNum} بنجاح: {$order->order_number}");
            } catch (\Exception $e) {
                $orderNum = $index + 1;
                $this->command->error("فشل إنشاء الطلب #{$orderNum}: " . $e->getMessage());
            }
        }

        $this->command->info('تم إنشاء الطلبات بنجاح.');
    }
}
