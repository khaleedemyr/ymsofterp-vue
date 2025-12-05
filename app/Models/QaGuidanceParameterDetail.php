<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaGuidanceParameterDetail extends Model
{
    use HasFactory;

    protected $table = 'qa_guidance_parameter_details';
    
    protected $fillable = [
        'category_parameter_id',
        'parameter_id',
        'point'
    ];

    protected $casts = [
        'point' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship dengan QaGuidanceCategoryParameter
    public function categoryParameter()
    {
        return $this->belongsTo(QaGuidanceCategoryParameter::class, 'category_parameter_id');
    }

    // Relationship dengan QaParameter
    public function parameter()
    {
        return $this->belongsTo(QaParameter::class, 'parameter_id');
    }
}
