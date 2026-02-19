<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_company_id',
        'name',
        'description',
        'category_id',
        'manufacturer_id',
        'sku',
        'barcode',
        'unit',
        'price', // Legacy field - kept for backward compatibility
        'pharmacist_price',
        'customer_price',
        'discount',
        'discount_type',
        'stock_quantity',
        'min_stock_level',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'pharmacist_price' => 'decimal:2',
        'customer_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the supplier company that owns this product.
     */
    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the category for this product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the manufacturer for this product.
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Get order items for this product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get stock movements for this product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get offers associated with this product.
     */
    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'offer_products')
            ->withPivot(['quantity_required', 'quantity_free', 'discount_override', 'discount_type_override'])
            ->withTimestamps();
    }

    /**
     * Check if product is low on stock.
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    /**
     * Get the effective price for pharmacists (buyers).
     * Uses pharmacist_price if set, otherwise falls back to price.
     */
    public function getEffectivePharmacistPrice(): float
    {
        return (float) ($this->pharmacist_price ?? $this->price ?? 0);
    }

    /**
     * Get the effective price for end customers.
     * Uses customer_price if set, otherwise falls back to pharmacist_price or price.
     */
    public function getEffectiveCustomerPrice(): float
    {
        return (float) ($this->customer_price ?? $this->pharmacist_price ?? $this->price ?? 0);
    }

    /**
     * Calculate the final price after discount for pharmacists.
     */
    public function getFinalPharmacistPrice(): float
    {
        $price = $this->getEffectivePharmacistPrice();
        
        if ($this->discount && $this->discount_type) {
            if ($this->discount_type === 'percentage') {
                return $price * (1 - ($this->discount / 100));
            } else {
                return max(0, $price - $this->discount);
            }
        }
        
        return $price;
    }

    /**
     * Get the final price for end customers.
     * Product discount applies to pharmacist price only; customer price is returned as-is.
     */
    public function getFinalCustomerPrice(): float
    {
        return (float) $this->getEffectiveCustomerPrice();
    }
}
