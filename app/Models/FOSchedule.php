<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FOSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'fo_mode',
        'day',
        'open_time',
        'close_time'
    ];

    protected $table = 'fo_schedules';

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'fo_schedule_region', 'fo_schedule_id', 'region_id');
    }

    public function outlets()
    {
        return $this->belongsToMany(
            Outlet::class,
            'fo_schedule_outlet',
            'fo_schedule_id',
            'outlet_id',
            'id',
            'id_outlet'
        );
    }

    public function warehouseDivisions()
    {
        return $this->belongsToMany(WarehouseDivision::class, 'fo_schedule_warehouse_divisions', 'fo_schedule_id', 'warehouse_division_id');
    }
} 