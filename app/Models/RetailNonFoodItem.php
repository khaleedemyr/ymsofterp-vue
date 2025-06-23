<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailNonFoodItem extends Model
{
    protected $table = 'retail_non_food_items';
    protected $guarded = [];
    protected $fillable = [
        'retail_non_food_id',
        'item_name',
        'qty',
        'unit',
        'price',
        'subtotal'
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function retailNonFood()
    {
        return $this->belongsTo(RetailNonFood::class);
    }
} 