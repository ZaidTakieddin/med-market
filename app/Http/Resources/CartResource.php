<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Cart
 */
class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();
        $itemsBySupplier = [];

        // Group items by supplier
        if ($this->relationLoaded('items') && $items->isNotEmpty()) {
            foreach ($items as $item) {
                // Product and supplierCompany should be loaded by controller
                $product = $item->product ?? null;
                $supplierId = $product->supplier_company_id ?? null;

                if ($supplierId) {
                    if (! isset($itemsBySupplier[$supplierId])) {
                        $supplierCompany = $product->supplierCompany ?? null;

                        $itemsBySupplier[$supplierId] = [
                            'supplier' => $supplierCompany ? new CompanyResource($supplierCompany) : null,
                            'items' => [],
                            'subtotal' => 0,
                        ];
                    }
                    $itemsBySupplier[$supplierId]['items'][] = new CartItemResource($item);
                    $itemsBySupplier[$supplierId]['subtotal'] += (float) $item->total_price;
                }
            }

            // Convert to indexed array and calculate totals for each supplier group
            $itemsBySupplier = array_values(array_map(function ($group) {
                return [
                    'supplier' => $group['supplier'],
                    'items' => $group['items'],
                    'subtotal' => round($group['subtotal'], 2),
                    'itemCount' => count($group['items']),
                ];
            }, $itemsBySupplier));
        }

        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'items' => CartItemResource::collection($items),
            'itemsBySupplier' => $itemsBySupplier,
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'totalAmount' => (float) $this->total_amount,
            'hasMultipleSuppliers' => $this->hasMultipleSuppliers(),
            'suppliersCount' => count($itemsBySupplier),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
