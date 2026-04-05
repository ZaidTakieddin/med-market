<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'supplier_classification',
        'buyer_classification',
        'min_order_amount',
        'email',
        'phone',
        'address',
        'latitude',
        'longitude',
        'region_id',
        'address_line',
        'tax_id',
        'registration_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'min_order_amount' => 'decimal:2',
    ];

    /**
     * Get users belonging to this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get products supplied by this company (if supplier).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_company_id');
    }

    /**
     * Get orders where this company is the buyer.
     */
    public function buyerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_company_id');
    }

    /**
     * Get orders where this company is the supplier.
     */
    public function supplierOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'supplier_company_id');
    }

    /**
     * Check if company is a supplier.
     */
    public function isSupplier(): bool
    {
        return $this->type === 'supplier';
    }

    /**
     * Check if company is a buyer (pharmacy).
     */
    public function isBuyer(): bool
    {
        return $this->type === 'buyer';
    }

    /**
     * Check if supplier sells wholesale (wholesale or hybrid).
     */
    public function isWholesale(): bool
    {
        return in_array($this->supplier_classification, ['wholesale', 'hybrid'], true);
    }

    /**
     * Check if supplier sells retail (retail or hybrid).
     */
    public function isRetail(): bool
    {
        return in_array($this->supplier_classification, ['retail', 'hybrid'], true);
    }

    /**
     * Check if supplier is hybrid (both wholesale and retail).
     */
    public function isHybrid(): bool
    {
        return $this->supplier_classification === 'hybrid';
    }

    /**
     * Check if buyer is a pharmacy.
     */
    public function isPharmacy(): bool
    {
        return $this->buyer_classification === 'pharmacy';
    }

    /**
     * Check if buyer is a salesman company.
     */
    public function isSalesmanCompany(): bool
    {
        return $this->buyer_classification === 'salesman';
    }

    /**
     * Check if this supplier can offer wholesale pricing to a given buyer.
     */
    public function canOfferWholesaleTo(Company $buyer): bool
    {
        if (!$this->isSupplier() || !$this->isWholesale()) {
            return false;
        }

        return $buyer->isSalesmanCompany();
    }

    /**
     * Get offers created by this company (if supplier).
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'supplier_company_id');
    }

    /**
     * Get carts for this company (if buyer).
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get categories that this supplier deals with (auto-synced from products).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'supplier_categories')
            ->withTimestamps();
    }

    /**
     * Get manufacturers that this supplier deals with (auto-synced from products).
     */
    public function manufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class, 'supplier_manufacturers')
            ->withTimestamps();
    }

    /**
     * Get the region this company is located in.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get price lists where this company is the supplier.
     */
    public function supplierPriceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'supplier_company_id');
    }

    /**
     * Get price lists where this company is the buyer.
     */
    public function buyerPriceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'buyer_company_id');
    }

    /**
     * Get buyer relationships where this company is the supplier.
     */
    public function buyerRelationships(): HasMany
    {
        return $this->hasMany(BuyerSupplierRelationship::class, 'supplier_company_id');
    }

    /**
     * Get supplier relationships where this company is the buyer.
     */
    public function supplierRelationships(): HasMany
    {
        return $this->hasMany(BuyerSupplierRelationship::class, 'buyer_company_id');
    }

    /**
     * Get salesman client entries where this company is the pharmacy.
     */
    public function salesmanAssignments(): HasMany
    {
        return $this->hasMany(SalesmanClient::class, 'pharmacy_company_id');
    }
}

