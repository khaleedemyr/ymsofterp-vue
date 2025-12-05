<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Area extends Model
{
    use HasFactory;

    protected $table = 'areas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nama_area',
        'kode_area',
        'departemen_id',
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

    // Scope untuk area aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    // Relationship dengan Departemen
    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id', 'id');
    }
}
