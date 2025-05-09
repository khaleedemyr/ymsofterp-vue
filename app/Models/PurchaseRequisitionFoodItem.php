<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionFoodItem extends Model
{
    use HasFactory;

    protected $table = 'pr_food_items';

    protected $fillable = [
        'pr_food_id',
        'item_id',
        'qty',
        'unit',
        'note',
        'arrival_date',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisitionFood::class, 'pr_food_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
} 