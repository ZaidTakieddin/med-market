<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTemplateItem extends Model
{
    protected $fillable = [
        'order_template_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the parent template.
     *
     * @return BelongsTo<OrderTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OrderTemplate::class, 'order_template_id');
    }

    /**
     * Get the product in this template line.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
