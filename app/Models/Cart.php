<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'subtotal',
        'tax_amount',
        'total_amount',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the user who owns this cart.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the buyer company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get cart items.
     *
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Calculate and update cart totals.
     */
    public function calculateTotals(float $taxRate = 0): void
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->tax_amount = $this->subtotal * ($taxRate / 100);
        $this->total_amount = $this->subtotal + $this->tax_amount;
        $this->save();
    }

    /**
     * Check if cart has items from multiple suppliers.
     */
    public function hasMultipleSuppliers(): bool
    {
        $supplierIds = $this->items()
            ->join('products', 'cart_items.product_id', '=', 'products.id')
            ->distinct()
            ->pluck('products.supplier_company_id')
            ->count();

        return $supplierIds > 1;
    }

    /**
     * Group cart items by supplier.
     *
     * @return array<int, array<int, CartItem>>
     */
    public function itemsBySupplier(): array
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $supplierId = $item->product->supplier_company_id;
            if (! isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [];
            }
            $grouped[$supplierId][] = $item;
        }

        return $grouped;
    }
}
