<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetManualMonthlyLostBreakageItem extends Model
{
    protected $table = 'asset_manual_monthly_lost_breakage_items';

    protected $fillable = [
        'asset_manual_monthly_lost_breakage_id',
        'outlet_id',
        'lost_breakage_value',
        'lost_breakage_percent',
    ];

    protected $casts = [
        'lost_breakage_value' => 'decimal:2',
        'lost_breakage_percent' => 'decimal:4',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(AssetManualMonthlyLostBreakage::class, 'asset_manual_monthly_lost_breakage_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
