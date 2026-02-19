<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'product_id',
        'quantity_required',
        'quantity_free',
        'discount_override',
    ];

    protected $casts = [
        'quantity_required' => 'integer',
        'quantity_free' => 'integer',
        'discount_override' => 'decimal:2',
    ];

    /**
     * Get the offer.
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
