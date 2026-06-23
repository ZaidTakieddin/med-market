<?php

namespace App\Models;

use App\Constants\PaymentTerms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerSupplierRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_company_id',
        'supplier_company_id',
        'payment_terms',
        'credit_limit',
        'current_balance',
        'is_approved',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Check if credit is available for a given amount.
     */
    public function hasCreditAvailable(float $amount): bool
    {
        if (! $this->is_approved) {
            return false;
        }

        if ($this->credit_limit === null) {
            return true;
        }

        return ($this->current_balance + $amount) <= (float) $this->credit_limit;
    }

    /**
     * Get the number of days for the payment terms.
     */
    public function getPaymentDays(): int
    {
        return PaymentTerms::getDays($this->payment_terms);
    }

    /**
     * Calculate the payment due date from a given order date.
     */
    public function calculateDueDate(\DateTimeInterface $orderDate): ?\Carbon\Carbon
    {
        $days = $this->getPaymentDays();
        if ($days === 0) {
            return null;
        }

        return \Carbon\Carbon::instance($orderDate)->addDays($days);
    }
}
