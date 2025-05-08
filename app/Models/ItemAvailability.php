<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemAvailability extends Model
{
    use HasFactory;

    protected $table = 'item_availabilities';

    protected $fillable = [
        'item_id',
        'availability_type',
        'region_id',
        'outlet_id',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
} 