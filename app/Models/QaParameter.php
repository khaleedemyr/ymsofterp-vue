<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaParameter extends Model
{
    use HasFactory;

    protected $table = 'qa_parameters';
    
    protected $fillable = [
        'kode_parameter',
        'parameter',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scope untuk data aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    // Scope untuk data non-aktif
    public function scopeInactive($query)
    {
        return $query->where('status', 'N');
    }
}
