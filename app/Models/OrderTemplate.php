<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\OrderTemplateFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'buyer_company_id',
        'created_by',
        'name',
        'notes',
    ];

    /**
     * Get the buyer company that owns the template.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    /**
     * Get the user who created the template.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Template line items.
     *
     * @return HasMany<OrderTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderTemplateItem::class);
    }

    /**
     * The products in this template (via items).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_template_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
