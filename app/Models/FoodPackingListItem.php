<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPackingListItem extends Model
{
    protected $table = 'food_packing_list_items';
    protected $guarded = [];

    public function packingList()
    {
        return $this->belongsTo(FoodPackingList::class, 'packing_list_id');
    }

    public function floorOrderItem()
    {
        return $this->belongsTo(FoodFloorOrderItem::class, 'food_floor_order_item_id');
    }
} 