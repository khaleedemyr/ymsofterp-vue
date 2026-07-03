<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualCogsDeviationCatcostItem extends Model
{
    protected $table = 'manual_cogs_deviation_catcost_items';

    protected $fillable = [
        'manual_cogs_deviation_catcost_id',
        'outlet_id',
        'cogs_value',
        'cogs_percent',
        'deviation_value',
        'deviation_percent',
        'catcost_value',
        'catcost_percent',
    ];

    protected $casts = [
        'cogs_value' => 'decimal:2',
        'cogs_percent' => 'decimal:4',
        'deviation_value' => 'decimal:2',
        'deviation_percent' => 'decimal:4',
        'catcost_value' => 'decimal:2',
        'catcost_percent' => 'decimal:4',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(ManualCogsDeviationCatcost::class, 'manual_cogs_deviation_catcost_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
