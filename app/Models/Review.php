<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_PRODUCT = Product::class;

    public const TYPE_COMPANY = Company::class;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'order_id',
        'rating',
        'body',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * The reviewed entity (Product or Company).
     *
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the author.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The delivered order that verifies this review (when provided).
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Only publicly visible (approved) reviews.
     *
     * @param  Builder<self>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }
}
