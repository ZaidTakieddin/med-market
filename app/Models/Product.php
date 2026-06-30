<?php

namespace App\Models;

use App\Constants\DiscountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
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
        'price',
        'wholesale_price',
        'wholesale_min_qty',
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
        'wholesale_price' => 'decimal:2',
        'wholesale_min_qty' => 'integer',
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
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the category for this product.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the manufacturer for this product.
     *
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Get order items for this product.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get stock movements for this product.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get offers associated with this product.
     *
     * @return BelongsToMany<Offer, $this>
     */
    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'offer_products')
            ->withPivot(['quantity_required', 'quantity_free', 'discount_override'])
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
            if ($this->discount_type === DiscountType::PERCENTAGE) {
                return max(0, $price * (1 - (min($this->discount, 100) / 100)));
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

    /**
     * Get the effective wholesale price.
     * Returns wholesale_price if set, otherwise null (no wholesale available).
     */
    public function getEffectiveWholesalePrice(): ?float
    {
        return $this->wholesale_price !== null ? (float) $this->wholesale_price : null;
    }

    /**
     * Get the final wholesale price.
     * Wholesale prices are pre-negotiated and do NOT receive product-level retail discounts.
     */
    public function getFinalWholesalePrice(): ?float
    {
        return $this->getEffectiveWholesalePrice();
    }

    /**
     * Check if a given quantity qualifies for wholesale pricing.
     */
    public function qualifiesForWholesale(int $quantity): bool
    {
        if ($this->wholesale_price === null || $this->wholesale_min_qty === null) {
            return false;
        }

        return $quantity >= $this->wholesale_min_qty;
    }

    /**
     * Get price lists for this product.
     *
     * @return HasMany<PriceList, $this>
     */
    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    /**
     * Get all images for this product, ordered for display.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Get the product's primary (cover) image.
     *
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
