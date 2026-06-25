<?php

namespace App\Models;

use App\Support\Concerns\CacheableReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Region extends Model
{
    /** @use HasFactory<\Database\Factories\RegionFactory> */
    use CacheableReference, HasFactory, SoftDeletes;

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'description',
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

        static::creating(function (Region $region) {
            if (empty($region->slug)) {
                $region->slug = Str::slug($region->name);
            }
        });

        static::updating(function (Region $region) {
            if ($region->isDirty('name') && empty($region->slug)) {
                $region->slug = Str::slug($region->name);
            }
        });
    }

    /**
     * Get the city this region belongs to.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get companies in this region.
     *
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Get drivers assigned to this region.
     *
     * @return BelongsToMany<User, $this>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'driver_regions', 'region_id', 'driver_id')
            ->where('role', 'driver')
            ->withTimestamps();
    }
}
