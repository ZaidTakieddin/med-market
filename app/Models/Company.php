<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'email',
        'phone',
        'address',
        'tax_id',
        'registration_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}

