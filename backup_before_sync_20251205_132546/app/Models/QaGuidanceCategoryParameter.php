<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaGuidanceCategoryParameter extends Model
{
    use HasFactory;

    protected $table = 'qa_guidance_category_parameters';
    
    protected $fillable = [
        'guidance_category_id',
        'parameter_pemeriksaan'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship dengan QaGuidanceCategory
    public function guidanceCategory()
    {
        return $this->belongsTo(QaGuidanceCategory::class, 'guidance_category_id');
    }

    // Relationship dengan QaGuidanceParameterDetail (multiple parameter + point per pemeriksaan)
    public function details()
    {
        return $this->hasMany(QaGuidanceParameterDetail::class, 'category_parameter_id');
    }
}
