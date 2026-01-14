<?php

namespace App\Models;

use App\Domain\Orders\OrderState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'buyer_company_id',
        'supplier_company_id',
        'created_by',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'accepted_at',
        'prepared_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'prepared_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the buyer company.
     */
    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    /**
     * Get the supplier company.
     */
    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the user who created the order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get order items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get status logs for this order.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    /**
     * Check if order can transition to a new status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return OrderState::canTransition($this->status, $newStatus);
    }

    /**
     * Check if order is in a final state.
     */
    public function isFinalState(): bool
    {
        return in_array($this->status, [OrderState::DELIVERED, OrderState::CANCELLED], true);
    }
}
