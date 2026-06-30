<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImportError extends Model
{
    /** @use HasFactory<\Database\Factories\ProductImportErrorFactory> */
    use HasFactory;

    protected $fillable = [
        'product_import_id',
        'row_number',
        'sku',
        'message',
    ];

    protected $casts = [
        'row_number' => 'integer',
    ];

    /**
     * Get the import this error belongs to.
     *
     * @return BelongsTo<ProductImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }
}
