<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetInventoryItem extends Model
{
    protected $table = 'asset_inventory_items';
    protected $fillable = ['item_id', 'small_unit_id', 'medium_unit_id', 'large_unit_id'];

    public function item() { return $this->belongsTo(Item::class); }
    public function stocks() { return $this->hasMany(AssetInventoryStock::class, 'inventory_item_id'); }
}
