<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A batch (lot) of stock for a product, used for FEFO allocation. Products that have
 * never received a batch are not batch-tracked; the first receipt starts tracking.
 * A single row with a null batch_number holds unbatched stock.
 *
 * @property int $quantity
 *
 * @mixin \Illuminate\Database\Query\Builder
 */
class ProductBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ProductBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expiry_date' => 'date',
    ];

    /**
     * Get the product this batch belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * FEFO order: earliest expiry first (never-expiring last), oldest receipt as tiebreak.
     *
     * @param  Builder<ProductBatch>  $query
     */
    public function scopeFefo(Builder $query): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        // SQLite lacks "NULLS LAST"; emulate with an ordered CASE expression.
        if ($driver === 'sqlite') {
            $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date');
        } else {
            $query->orderByRaw('expiry_date ASC NULLS LAST');
        }

        $query->orderBy('id');
    }
}
