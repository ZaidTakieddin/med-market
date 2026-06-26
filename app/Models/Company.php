<?php

namespace App\Models;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\SupplierClassification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $type
 * @property string|null $supplier_classification
 * @property string|null $buyer_classification
 */
class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
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
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get products supplied by this company (if supplier).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_company_id');
    }

    /**
     * Get orders where this company is the buyer.
     *
     * @return HasMany<Order, $this>
     */
    public function buyerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_company_id');
    }

    /**
     * Get orders where this company is the supplier.
     *
     * @return HasMany<Order, $this>
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
        return $this->type === CompanyType::SUPPLIER;
    }

    /**
     * Check if company is a buyer (pharmacy).
     */
    public function isBuyer(): bool
    {
        return $this->type === CompanyType::BUYER;
    }

    /**
     * Check if supplier sells wholesale (wholesale or hybrid).
     */
    public function isWholesale(): bool
    {
        return in_array($this->supplier_classification, SupplierClassification::wholesaleCapable(), true);
    }

    /**
     * Check if supplier sells retail (retail or hybrid).
     */
    public function isRetail(): bool
    {
        return in_array($this->supplier_classification, SupplierClassification::retailCapable(), true);
    }

    /**
     * Check if supplier is hybrid (both wholesale and retail).
     */
    public function isHybrid(): bool
    {
        return $this->supplier_classification === SupplierClassification::HYBRID;
    }

    /**
     * Check if buyer is a pharmacy.
     */
    public function isPharmacy(): bool
    {
        return $this->buyer_classification === BuyerClassification::PHARMACY;
    }

    /**
     * Check if buyer is a salesman company.
     */
    public function isSalesmanCompany(): bool
    {
        return $this->buyer_classification === BuyerClassification::SALESMAN;
    }

    /**
     * Supplier classifications a viewer company is allowed to reach, or null when the
     * viewer has no supplier-visibility restriction (unclassified companies and
     * wholesale/hybrid suppliers).
     *
     * Rules: pharmacy buyers reach retail + hybrid; salesman buyers reach wholesale +
     * hybrid; a pure-retail supplier cannot reach other pure-retail suppliers.
     *
     * @return list<string>|null
     */
    public static function visibleSupplierClassificationsFor(?Company $viewer): ?array
    {
        if ($viewer === null) {
            return null;
        }

        return match (true) {
            $viewer->buyer_classification === BuyerClassification::PHARMACY => SupplierClassification::retailCapable(),
            $viewer->buyer_classification === BuyerClassification::SALESMAN => SupplierClassification::wholesaleCapable(),
            $viewer->supplier_classification === SupplierClassification::RETAIL => [SupplierClassification::WHOLESALE, SupplierClassification::HYBRID],
            default => null,
        };
    }

    /**
     * Scope companies to those visible to the given viewer. Buyer companies are always
     * visible; supplier companies are filtered by the viewer's allowed classifications,
     * and a company can always see itself. Admins and users without a company see all.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null || $viewer->isAdmin()) {
            return $query;
        }

        $viewerCompany = $viewer->company;
        if ($viewerCompany === null) {
            return $query;
        }

        $allowed = self::visibleSupplierClassificationsFor($viewerCompany);
        if ($allowed === null) {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($allowed, $viewerCompany) {
            $sub->where('type', '!=', CompanyType::SUPPLIER)
                ->orWhere('id', $viewerCompany->id)
                ->orWhereIn('supplier_classification', $allowed);
        });
    }

    /**
     * Check if this supplier can offer wholesale pricing to a given buyer.
     */
    public function canOfferWholesaleTo(Company $buyer): bool
    {
        if (! $this->isSupplier() || ! $this->isWholesale()) {
            return false;
        }

        return $buyer->isSalesmanCompany();
    }

    /**
     * Get offers created by this company (if supplier).
     *
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'supplier_company_id');
    }

    /**
     * Get carts for this company (if buyer).
     *
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get categories that this supplier deals with (auto-synced from products).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'supplier_categories')
            ->withTimestamps();
    }

    /**
     * Get manufacturers that this supplier deals with (auto-synced from products).
     *
     * @return BelongsToMany<Manufacturer, $this>
     */
    public function manufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class, 'supplier_manufacturers')
            ->withTimestamps();
    }

    /**
     * Get the region this company is located in.
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get price lists where this company is the supplier.
     *
     * @return HasMany<PriceList, $this>
     */
    public function supplierPriceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'supplier_company_id');
    }

    /**
     * Get price lists where this company is the buyer.
     *
     * @return HasMany<PriceList, $this>
     */
    public function buyerPriceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'buyer_company_id');
    }

    /**
     * Get buyer relationships where this company is the supplier.
     *
     * @return HasMany<BuyerSupplierRelationship, $this>
     */
    public function buyerRelationships(): HasMany
    {
        return $this->hasMany(BuyerSupplierRelationship::class, 'supplier_company_id');
    }

    /**
     * Get supplier relationships where this company is the buyer.
     *
     * @return HasMany<BuyerSupplierRelationship, $this>
     */
    public function supplierRelationships(): HasMany
    {
        return $this->hasMany(BuyerSupplierRelationship::class, 'buyer_company_id');
    }

    /**
     * Get salesman client entries where this company is the pharmacy.
     *
     * @return HasMany<SalesmanClient, $this>
     */
    public function salesmanAssignments(): HasMany
    {
        return $this->hasMany(SalesmanClient::class, 'pharmacy_company_id');
    }
}
