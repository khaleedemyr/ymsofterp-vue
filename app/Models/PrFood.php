<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WarehouseDivision;

class PrFood extends Model
{
    use HasFactory;

    protected $table = 'pr_foods';
    protected $fillable = [
        'pr_number', 'tanggal', 'status', 'requested_by', 'warehouse_id', 'warehouse_division_id',
        'assistant_ssd_manager_approved_at', 'assistant_ssd_manager_approved_by', 'assistant_ssd_manager_note',
        'ssd_manager_approved_at', 'ssd_manager_approved_by', 'ssd_manager_note',
        'vice_coo_approved_at', 'vice_coo_approved_by', 'vice_coo_note', 'description'
    ];

    public function items()
    {
        return $this->hasMany(PrFoodItem::class, 'pr_food_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseDivision()
    {
        return $this->belongsTo(WarehouseDivision::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assistantSsdManager()
    {
        return $this->belongsTo(User::class, 'assistant_ssd_manager_approved_by');
    }

    public function ssdManager()
    {
        return $this->belongsTo(User::class, 'ssd_manager_approved_by');
    }

    public function viceCoo()
    {
        return $this->belongsTo(User::class, 'vice_coo_approved_by');
    }
} 