<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetBrand extends Model
{
    protected $table = 'asset_brands';
    
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get active brands
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}

