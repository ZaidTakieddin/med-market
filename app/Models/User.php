<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'company_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the company this user belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get orders created by this user.
     */
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    /**
     * Get stock movements made by this user.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get order status logs created by this user.
     */
    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    /**
     * Get cart for this user.
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Get the user's current cart (one-to-one).
     */
    public function currentCart(): HasOne
    {
        return $this->hasOne(Cart::class)->latestOfMany();
    }

    /**
     * Get orders assigned to this driver.
     */
    public function driverOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is supplier.
     */
    public function isSupplier(): bool
    {
        return $this->role === 'supplier';
    }

    /**
     * Check if user is buyer (pharmacist).
     */
    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    /**
     * Check if user is driver.
     */
    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    /**
     * Check if user is a salesman (supplier-associated or independent).
     */
    public function isSalesman(): bool
    {
        return $this->role === 'salesman';
    }

    /**
     * Check if user is a supplier-associated salesman.
     */
    public function isSupplierSalesman(): bool
    {
        if (!$this->isSalesman()) {
            return false;
        }

        return $this->company && $this->company->isSupplier();
    }

    /**
     * Check if user is an independent salesman (has own buyer company).
     */
    public function isIndependentSalesman(): bool
    {
        if (!$this->isSalesman()) {
            return false;
        }

        return $this->company && $this->company->isBuyer();
    }

    /**
     * Check if user can buy (buyer or salesman).
     */
    public function canBuy(): bool
    {
        return $this->isBuyer() || $this->isSalesman();
    }

    /**
     * Get regions assigned to this driver.
     */
    public function driverRegions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'driver_regions', 'driver_id', 'region_id')
            ->withTimestamps();
    }

    /**
     * Check if driver serves a specific region.
     */
    public function servesRegion(int $regionId): bool
    {
        if (!$this->isDriver()) {
            return false;
        }

        return $this->driverRegions()->where('region_id', $regionId)->exists();
    }

    /**
     * Get regions assigned to this salesman.
     */
    public function salesmanRegions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'salesman_regions', 'salesman_id', 'region_id')
            ->withTimestamps();
    }

    /**
     * Get pharmacy clients assigned to this salesman.
     */
    public function salesmanClients(): HasMany
    {
        return $this->hasMany(SalesmanClient::class, 'salesman_user_id');
    }

    /**
     * Get orders placed by this salesman on behalf of others.
     */
    public function salesmanOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'placed_by_salesman_id');
    }
}
