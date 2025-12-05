<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubDivisi extends Model
{
    use HasFactory;

    protected $table = 'tbl_data_sub_divisi';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id_divisi', 'nama_sub_divisi', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'id_divisi', 'id');
    }

    // Scope to get active records
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
} 