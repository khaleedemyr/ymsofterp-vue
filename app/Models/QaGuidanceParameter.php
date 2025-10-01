<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QaGuidanceParameter extends Model
{
    use HasFactory;

    protected $table = 'qa_guidance_parameters';
    
    protected $fillable = [
        'guidance_id',
        'parameter_pemeriksaan',
        'parameter_id',
        'point'
    ];

    protected $casts = [
        'point' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship dengan QaGuidance (header)
    public function guidance()
    {
        return $this->belongsTo(QaGuidance::class, 'guidance_id');
    }

    // Relationship dengan QaParameter
    public function parameter()
    {
        return $this->belongsTo(QaParameter::class, 'parameter_id');
    }
}
