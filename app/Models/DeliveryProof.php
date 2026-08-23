<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryProof extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryProofFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'disk',
        'path',
        'url',
        'notes',
    ];

    /**
     * Get the order the proof belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who uploaded the proof.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
