<?php

namespace App\Models;

use App\Constants\OfferType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_company_id',
        'name',
        'description',
        'offer_type',
        'discount_value',
        'quantity_required',
        'quantity_free',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'quantity_required' => 'integer',
        'quantity_free' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the supplier company that owns this offer.
     */
    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get products associated with this offer.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'offer_products')
            ->withPivot(['quantity_required', 'quantity_free', 'discount_override'])
            ->withTimestamps();
    }

    /**
     * Check if offer is currently active (within date range and is_active).
     * When end_date is null, offer has no time limit (active indefinitely after start_date).
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        if (!$now->gte($this->start_date)) {
            return false;
        }

        return $this->end_date === null || $now->lte($this->end_date);
    }

    /**
     * Check if offer type is valid.
     */
    public function isValidOfferType(): bool
    {
        return OfferType::isValid($this->offer_type);
    }

}
