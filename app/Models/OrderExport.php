<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderExport extends Model
{
    /** @use HasFactory<\Database\Factories\OrderExportFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'status',
        'format',
        'filters',
        'file_path',
        'orders_count',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'orders_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who requested the export.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company the export is scoped to.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
