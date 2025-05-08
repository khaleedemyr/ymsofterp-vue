<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Outlet extends Model {
    protected $table = 'tbl_data_outlet';
    protected $primaryKey = 'id_outlet';
    public $timestamps = false;

    protected $fillable = [
        'nama_outlet',
        'alamat',
        'kota',
        'telp',
        'status',
    ];

    public function itemAvailabilities()
    {
        return $this->hasMany(ItemAvailability::class, 'outlet_id', 'id_outlet');
    }

    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class, 'outlet_id', 'id_outlet');
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_availabilities', 'outlet_id', 'item_id', 'id_outlet', 'id');
    }
} 