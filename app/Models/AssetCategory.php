<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    protected $table = 'asset_categories';
    
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all assets for this category
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    /**
     * Get active assets for this category
     */
    public function activeAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id')
            ->where('status', 'Active');
    }
}

