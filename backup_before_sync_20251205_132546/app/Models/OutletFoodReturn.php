<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletFoodReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'outlet_food_returns';

    protected $fillable = [
        'return_number',
        'outlet_food_good_receive_id',
        'outlet_id',
        'warehouse_outlet_id',
        'return_date',
        'notes',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function goodReceive()
    {
        return $this->belongsTo(OutletFoodGoodReceive::class, 'outlet_food_good_receive_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items()
    {
        return $this->hasMany(OutletFoodReturnItem::class, 'outlet_food_return_id');
    }
}
