<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaCategory extends Model
{
    use HasFactory;

    protected $table = 'qa_categories';
    
    protected $fillable = [
        'kode_categories',
        'categories',
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
