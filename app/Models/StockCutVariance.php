<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCutVariance extends Model
{
    protected $table = 'stock_cut_variances';

    protected $fillable = [
        'stock_cut_log_id',
        'outlet_id',
        'warehouse_outlet_id',
        'inventory_item_id',
        'item_id',
        'tanggal',
        'type_filter',
        'qty_needed',
        'qty_available_before',
        'qty_shortfall',
        'qty_after',
        'cost_per_small',
        'value_booked',
        'shortfall_value_info',
        'executed_by',
        'status',
        'closed_at',
        'closed_via',
        'closed_reference_type',
        'closed_reference_id',
        'closed_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'qty_needed' => 'float',
        'qty_available_before' => 'float',
        'qty_shortfall' => 'float',
        'qty_after' => 'float',
        'cost_per_small' => 'float',
        'value_booked' => 'float',
        'shortfall_value_info' => 'float',
        'closed_at' => 'datetime',
    ];

    public function stockCutLog()
    {
        return $this->belongsTo(StockCutLog::class, 'stock_cut_log_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
}
