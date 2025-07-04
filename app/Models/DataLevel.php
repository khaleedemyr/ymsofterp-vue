<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataLevel extends Model
{
    use HasFactory;

    protected $table = 'tbl_data_level';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    protected $fillable = [
        'nama_level',
        'nilai_level',
        'nilai_public_holiday',
        'nilai_dasar_potongan_bpjs',
        'nilai_point',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'nilai_public_holiday' => 'integer',
        'nilai_dasar_potongan_bpjs' => 'integer',
        'nilai_point' => 'integer',
        'status' => 'string'
    ];

    // Accessor to get status text
    public function getStatusTextAttribute()
    {
        return $this->status === 'A' ? 'Active' : 'Inactive';
    }

    // Scope to get active records
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
} 