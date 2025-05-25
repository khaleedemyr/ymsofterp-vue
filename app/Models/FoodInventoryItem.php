<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Unit;

class FoodInventoryItem extends Model
{
    protected $table = 'food_inventory_items';
    protected $fillable = [
        'item_id',
        'small_unit_id',
        'medium_unit_id',
        'large_unit_id',
        'created_at',
        'updated_at',
    ];

    public function smallUnit() {
        return $this->belongsTo(Unit::class, 'small_unit_id');
    }
    public function mediumUnit() {
        return $this->belongsTo(Unit::class, 'medium_unit_id');
    }
    public function largeUnit() {
        return $this->belongsTo(Unit::class, 'large_unit_id');
    }
}
