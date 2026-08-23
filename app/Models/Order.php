<?php

namespace App\Models;

use App\Constants\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Scope the query to the orders a user may see, by role:
     * admins see everything; buyers/independent salesmen their company's purchases;
     * supplier salesmen their supplier's orders plus ones they placed; suppliers
     * their incoming orders; drivers only assigned deliveries.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isBuyer() || ($user->isSalesman() && $user->isIndependentSalesman())) {
            $query->where('buyer_company_id', $user->company_id);
        } elseif ($user->isSalesman() && $user->isSupplierSalesman()) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('supplier_company_id', $user->company_id)
                    ->orWhere('placed_by_salesman_id', $user->id);
            });
        } elseif ($user->isSupplier()) {
            $query->where('supplier_company_id', $user->company_id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        } else {
            // Unknown role: no rows.
            $query->whereKey(-1);
        }
    }

    protected $fillable = [
        'order_number',
        'buyer_company_id',
        'supplier_company_id',
        'created_by',
        'driver_id',
        'placed_by_salesman_id',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'payment_method',
        'payment_due_date',
        'notes',
        'accepted_at',
        'prepared_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'estimated_delivery_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_due_date' => 'date',
        'accepted_at' => 'datetime',
        'prepared_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
    ];

    /**
     * Delivery proof attachments (photos/signatures) for this order.
     *
     * @return HasMany<DeliveryProof, $this>
     */
    public function deliveryProofs(): HasMany
    {
        return $this->hasMany(DeliveryProof::class);
    }

    /**
     * Get the buyer company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    /**
     * Get the supplier company.
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the user who created the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get order items.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the driver assigned to this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get status logs for this order.
     *
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    /**
     * Get the invoice for this order.
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Check if order can transition to a new status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return OrderStatus::canTransition($this->status, $newStatus);
    }

    /**
     * Get the salesman who placed this order (if any).
     *
     * @return BelongsTo<User, $this>
     */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_salesman_id');
    }

    /**
     * Check if order is in a final state.
     */
    public function isFinalState(): bool
    {
        return in_array($this->status, [OrderStatus::DELIVERED, OrderStatus::CANCELLED], true);
    }

    /**
     * Check if order was placed by a salesman on behalf of a buyer.
     */
    public function isPlacedBySalesman(): bool
    {
        return $this->placed_by_salesman_id !== null;
    }
}
