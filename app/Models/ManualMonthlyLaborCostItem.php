<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualMonthlyLaborCostItem extends Model
{
    protected $table = 'manual_monthly_labor_cost_items';

    protected $fillable = [
        'manual_monthly_labor_cost_id',
        'outlet_id',
        'labor_cost_value',
        'labor_cost_percent',
    ];

    protected $casts = [
        'labor_cost_value' => 'decimal:2',
        'labor_cost_percent' => 'decimal:4',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(ManualMonthlyLaborCost::class, 'manual_monthly_labor_cost_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
