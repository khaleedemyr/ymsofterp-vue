<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemBom extends Model
{
    use HasFactory;

    protected $table = 'item_bom';

    protected $fillable = [
        'item_id',
        'material_item_id',
        'qty',
        'unit_id',
        'stock_cut',
    ];

    protected $casts = [
        'stock_cut' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function materialItem()
    {
        return $this->belongsTo(Item::class, 'material_item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
} 