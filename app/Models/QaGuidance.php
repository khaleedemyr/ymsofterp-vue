<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaGuidance extends Model
{
    use HasFactory;

    protected $table = 'qa_guidances';
    
    protected $fillable = [
        'title',
        'departemen',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship dengan QaGuidanceCategory (pivot table)
    public function guidanceCategories()
    {
        return $this->hasMany(QaGuidanceCategory::class, 'guidance_id');
    }

    // Relationship dengan QaCategory (through pivot)
    public function categories()
    {
        return $this->belongsToMany(QaCategory::class, 'qa_guidance_categories', 'guidance_id', 'category_id');
    }

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
