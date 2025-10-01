<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaGuidanceCategory extends Model
{
    use HasFactory;

    protected $table = 'qa_guidance_categories';
    
    protected $fillable = [
        'guidance_id',
        'category_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship dengan QaGuidance
    public function guidance()
    {
        return $this->belongsTo(QaGuidance::class, 'guidance_id');
    }

    // Relationship dengan QaCategory
    public function category()
    {
        return $this->belongsTo(QaCategory::class, 'category_id');
    }

    // Relationship dengan QaGuidanceCategoryParameter (parameter pemeriksaan per category)
    public function parameters()
    {
        return $this->hasMany(QaGuidanceCategoryParameter::class, 'guidance_category_id');
    }
}
