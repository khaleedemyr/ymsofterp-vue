<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    protected $table = 'asset_depreciations';
    
    protected $fillable = [
        'asset_id',
        'purchase_price',
        'useful_life',
        'depreciation_method',
        'depreciation_rate',
        'annual_depreciation',
        'current_value',
        'accumulated_depreciation',
        'last_calculated_date',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'useful_life' => 'integer',
        'depreciation_rate' => 'decimal:4',
        'annual_depreciation' => 'decimal:2',
        'current_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'last_calculated_date' => 'date',
        'asset_id' => 'integer',
    ];

    /**
     * Get the asset for this depreciation
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Calculate depreciation rate
     */
    public function calculateDepreciationRate(): float
    {
        if ($this->useful_life > 0) {
            return 1 / $this->useful_life;
        }
        return 0;
    }

    /**
     * Calculate annual depreciation
     */
    public function calculateAnnualDepreciation(): float
    {
        if ($this->useful_life > 0) {
            return $this->purchase_price / $this->useful_life;
        }
        return 0;
    }

    /**
     * Calculate current value based on years used
     */
    public function calculateCurrentValue(int $yearsUsed): float
    {
        $annualDepreciation = $this->calculateAnnualDepreciation();
        $accumulatedDepreciation = $annualDepreciation * $yearsUsed;
        return max(0, $this->purchase_price - $accumulatedDepreciation);
    }
}

