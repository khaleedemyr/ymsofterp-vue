<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPackingList extends Model
{
    protected $table = 'food_packing_lists';
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(FoodPackingListItem::class, 'packing_list_id');
    }

    public function floorOrder()
    {
        return $this->belongsTo(FoodFloorOrder::class, 'food_floor_order_id');
    }

    public function warehouseDivision()
    {
        return $this->belongsTo(WarehouseDivision::class, 'warehouse_division_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 