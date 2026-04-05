<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesmanClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'salesman_user_id',
        'salesman_company_id',
        'pharmacy_company_id',
        'is_active',
        'assigned_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the salesman user (for supplier-associated salesmen).
     */
    public function salesmanUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_user_id');
    }

    /**
     * Get the salesman company (for independent salesmen).
     */
    public function salesmanCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'salesman_company_id');
    }

    /**
     * Get the pharmacy company.
     */
    public function pharmacyCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'pharmacy_company_id');
    }
}
