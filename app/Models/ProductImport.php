<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImport extends Model
{
    /** @use HasFactory<\Database\Factories\ProductImportFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_company_id',
        'user_id',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'created_count',
        'updated_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the supplier company the products are imported into.
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the user who initiated the import.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the per-row errors recorded for this import.
     *
     * @return HasMany<ProductImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(ProductImportError::class);
    }
}
