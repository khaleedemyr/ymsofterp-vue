<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Departemen extends Model
{
    use HasFactory;

    protected $table = 'departemens';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nama_departemen',
        'kode_departemen',
        'deskripsi',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Accessor untuk status text
    public function getStatusTextAttribute()
    {
        return $this->status === 'A' ? 'Aktif' : 'Non-Aktif';
    }

    // Scope untuk departemen aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    // Relationship dengan Area
    public function areas()
    {
        return $this->hasMany(Area::class, 'departemen_id', 'id');
    }
}
