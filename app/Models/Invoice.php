<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'order_id',
        'buyer_company_id',
        'supplier_company_id',
        'status',
        'payment_status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'file_path',
        'file_format',
        'generated_at',
        'sent_at',
        'paid_at',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    /**
     * Get the order this invoice belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid' || $this->paid_amount >= $this->total_amount;
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        return ! $this->isPaid() && now()->isAfter($this->due_date);
    }

    /**
     * Get the invoice file content.
     */
    public function getFileContent(): ?string
    {
        if (! Storage::exists($this->file_path)) {
            return null;
        }

        return Storage::get($this->file_path);
    }

    /**
     * Get the invoice download URL.
     * Returns null for private storage (use download endpoint instead).
     */
    public function getDownloadUrl(): ?string
    {
        // For private storage, return null - use download endpoint instead
        // For public storage, you could return: Storage::url($this->file_path);
        return null;
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(?float $amount = null): void
    {
        $paidAmount = $amount ?? $this->total_amount;

        $this->update([
            'payment_status' => $paidAmount >= $this->total_amount ? 'paid' : 'partial',
            'paid_amount' => $paidAmount,
            'paid_at' => now(),
            'status' => 'paid',
        ]);
    }

    /**
     * Mark invoice as overdue.
     */
    public function markAsOverdue(): void
    {
        if (! $this->isPaid()) {
            $this->update([
                'payment_status' => 'overdue',
                'status' => 'overdue',
            ]);
        }
    }
}
