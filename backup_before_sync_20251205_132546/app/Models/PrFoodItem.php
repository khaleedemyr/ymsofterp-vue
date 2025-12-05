<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrFoodItem extends Model
{
    use HasFactory;

    protected $table = 'pr_food_items';
    protected $fillable = [
        'pr_food_id', 'item_id', 'qty', 'unit', 'note', 'arrival_date'
    ];

    public function prFood()
    {
        return $this->belongsTo(PrFood::class, 'pr_food_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function butcherPrFood()
    {
        return $this->belongsTo(PrFood::class, 'pr_food_id');
    }
} 