<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodStockBalance extends Model
{
    protected $table = 'food_stock_balances';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_id',
        'batch_number',
        'expiry_date',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:2'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 