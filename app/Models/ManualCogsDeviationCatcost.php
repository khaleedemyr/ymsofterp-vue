<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualCogsDeviationCatcost extends Model
{
    protected $table = 'manual_cogs_deviation_catcost';

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
        return $this->hasMany(ManualCogsDeviationCatcostItem::class, 'manual_cogs_deviation_catcost_id');
    }
}
