<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Manufacturer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'country',
        'website',
        'email',
        'phone',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($manufacturer) {
            if (empty($manufacturer->slug)) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });

        static::updating(function ($manufacturer) {
            if ($manufacturer->isDirty('name') && empty($manufacturer->slug)) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });
    }

    /**
     * Get products from this manufacturer.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
