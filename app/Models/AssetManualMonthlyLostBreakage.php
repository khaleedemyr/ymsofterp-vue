<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetManualMonthlyLostBreakage extends Model
{
    protected $table = 'asset_manual_monthly_lost_breakage';

    protected $fillable = [
        'month',
        'year',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetManualMonthlyLostBreakageItem::class, 'asset_manual_monthly_lost_breakage_id');
    }
}
