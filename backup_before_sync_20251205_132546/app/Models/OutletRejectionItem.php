<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletRejectionItem extends Model
{
    use HasFactory;

    protected $table = 'outlet_rejection_items';

    protected $fillable = [
        'outlet_rejection_id',
        'item_id',
        'unit_id',
        'qty_rejected',
        'qty_received',
        'rejection_reason',
        'item_condition',
        'condition_notes',
        'mac_cost'
    ];

    protected $casts = [
        'qty_rejected' => 'decimal:2',
        'qty_received' => 'decimal:2',
        'mac_cost' => 'decimal:2',
    ];

    // Relationships
    public function outletRejection()
    {
        return $this->belongsTo(OutletRejection::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // Scopes
    public function scopeByCondition($query, $condition)
    {
        return $query->where('item_condition', $condition);
    }

    public function scopeGoodCondition($query)
    {
        return $query->where('item_condition', 'good');
    }

    public function scopeDamagedCondition($query)
    {
        return $query->where('item_condition', 'damaged');
    }

    public function scopeExpiredCondition($query)
    {
        return $query->where('item_condition', 'expired');
    }

    // Methods
    public function getValue()
    {
        return $this->qty_received * $this->mac_cost;
    }

    public function getConditionLabel()
    {
        $labels = [
            'good' => 'Baik',
            'damaged' => 'Rusak',
            'expired' => 'Kadaluarsa',
            'other' => 'Lainnya'
        ];

        return $labels[$this->item_condition] ?? $this->item_condition;
    }

    public function getConditionColor()
    {
        $colors = [
            'good' => 'green',
            'damaged' => 'red',
            'expired' => 'orange',
            'other' => 'gray'
        ];

        return $colors[$this->item_condition] ?? 'gray';
    }

    public function canBeReceived()
    {
        return $this->qty_rejected > 0 && $this->qty_received <= $this->qty_rejected;
    }

    public function getRemainingQty()
    {
        return max(0, $this->qty_rejected - $this->qty_received);
    }
}
