<?php

namespace App\Domain\Orders;

use App\Exceptions\DomainException;
use App\Models\OrderTemplate;
use App\Models\OrderTemplateItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderTemplateService
{
    public function __construct(
        private OrderCreationService $orderCreationService,
        private \App\Domain\Pricing\PricingService $pricingService
    ) {}

    /**
     * Create a template with its line items (one transaction).
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     *
     * @throws DomainException When items are empty or a product is unavailable
     */
    public function create(User $user, int $buyerCompanyId, string $name, array $items, ?string $notes = null): OrderTemplate
    {
        $this->assertItems($items);

        return DB::transaction(function () use ($user, $buyerCompanyId, $name, $items, $notes): OrderTemplate {
            /** @var OrderTemplate */
            $template = OrderTemplate::create([
                'buyer_company_id' => $buyerCompanyId,
                'created_by' => $user->id,
                'name' => $name,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $template->items()->create([
                    'product_id' => (int) $item['product_id'],
                    'quantity' => max(1, (int) $item['quantity']),
                ]);
            }

            return $template->refresh();
        });
    }

    /**
     * Replace template attributes and line items.
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function update(OrderTemplate $template, ?string $name, ?string $notes, array $items): OrderTemplate
    {
        $this->assertItems($items);

        return DB::transaction(function () use ($template, $name, $notes, $items): OrderTemplate {
            if ($name !== null) {
                $template->name = $name;
            }
            if ($notes !== null) {
                $template->notes = $notes;
            }
            $template->save();

            $template->items()->delete();
            foreach ($items as $item) {
                $template->items()->create([
                    'product_id' => (int) $item['product_id'],
                    'quantity' => max(1, (int) $item['quantity']),
                ]);
            }

            return $template->refresh();
        });
    }

    public function delete(OrderTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Place fresh orders from a template, grouped per supplier, at current
     * server-resolved prices — one order per supplier inside one transaction.
     *
     * @return list<\App\Models\Order>
     *
     * @throws DomainException When the buyer cannot reach a supplier or credit fails
     */
    public function reorder(OrderTemplate $template, User $buyerUser, ?string $paymentMethod = null): array
    {
        /** @var Collection<int, OrderTemplateItem> $items */
        $items = $template->items()->with('product')->get();

        if ($items->isEmpty()) {
            throw new DomainException('This template has no items.');
        }

        // Group quantities per supplier.
        $bySupplier = [];
        foreach ($items as $item) {
            $supplierId = (int) $item->product->supplier_company_id;
            $bySupplier[$supplierId][] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $item->product,
            ];
        }

        $created = [];

        $buyerCompany = $template->company;
        if ($buyerCompany === null) {
            throw new DomainException('The template\'s buyer company no longer exists.');
        }

        DB::transaction(function () use (&$created, $template, $buyerUser, $paymentMethod, $bySupplier, $buyerCompany): void {
            foreach ($bySupplier as $supplierId => $rows) {
                // Catalog visibility still applies on reorder.
                $supplierCompany = \App\Models\Company::find($supplierId);
                if ($supplierCompany === null || ! $this->pricingService->canBuyerSeeSupplier($supplierCompany, $buyerCompany)) {
                    throw new DomainException("This template contains products from a supplier that is no longer available ({$supplierId}).");
                }

                // Prices are always resolved server-side in the buyer's context.
                $resolved = [];
                foreach ($rows as $row) {
                    $priceResult = $this->pricingService->resolve($row['product'], $buyerCompany, $row['quantity']);
                    $resolved[] = [
                        'product_id' => $row['product_id'],
                        'quantity' => $row['quantity'],
                        'unit_price' => $priceResult->unitPrice,
                        'pricing_type' => $priceResult->pricingType,
                    ];
                }

                $created[] = $this->orderCreationService->create(
                    buyerCompanyId: (int) $template->buyer_company_id,
                    supplierCompanyId: (int) $supplierId,
                    createdBy: $buyerUser->id,
                    items: $resolved,
                    notes: "Reorder from template: {$template->name}",
                    paymentMethod: $paymentMethod,
                    placedBySalesmanId: $buyerUser->isSalesman() ? $buyerUser->id : null,
                );
            }
        });

        return $created;
    }

    /**
     * @param  list<array{product_id: mixed, quantity: mixed}>  $items
     */
    private function assertItems(array $items): void
    {
        if ($items === []) {
            throw new DomainException('A template needs at least one item.');
        }

        foreach ($items as $item) {
            $product = Product::query()
                ->whereKey((int) $item['product_id'])
                ->where('is_active', true)
                ->first();

            if ($product === null) {
                throw new DomainException("Product {$item['product_id']} does not exist or is inactive.");
            }
        }
    }
}
