<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationHistory extends Model
{
    protected $table = 'asset_depreciation_history';
    
    protected $fillable = [
        'asset_id',
        'calculation_date',
        'purchase_price',
        'useful_life',
        'depreciation_amount',
        'accumulated_depreciation',
        'current_value',
        'years_used',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'purchase_price' => 'decimal:2',
        'useful_life' => 'integer',
        'depreciation_amount' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'current_value' => 'decimal:2',
        'years_used' => 'decimal:2',
        'asset_id' => 'integer',
    ];

    /**
     * Get the asset for this depreciation history
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}

