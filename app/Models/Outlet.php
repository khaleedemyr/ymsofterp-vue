<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Outlet extends Model {
    use HasFactory;
    
    protected $table = 'tbl_data_outlet';
    protected $primaryKey = 'id_outlet';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'nama_outlet',
        'lokasi',
        'qr_code',
        'lat',
        'long',
        'keterangan',
        'region_id',
        'status',
        'url_places',
        'sn',
        'activation_code',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => 'string'
    ];

    protected $appends = ['name'];

    // Accessor to get status text
    public function getStatusTextAttribute()
    {
        return $this->status === 'A' ? 'Active' : 'Inactive';
    }

    // Relationships
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

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

    public function getNameAttribute()
    {
        return $this->nama_outlet;
    }

    // Scope to get active records
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
} 