<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetDisposalItem extends Model
{
    protected $table = 'asset_disposal_items';
    protected $guarded = [];

    protected $casts = [
        'sale_price' => 'decimal:2',
    ];

    public function disposal()
    {
        return $this->belongsTo(AssetDisposal::class, 'disposal_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
