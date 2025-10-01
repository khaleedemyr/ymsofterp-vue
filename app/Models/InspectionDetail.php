<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionDetail extends Model
{
    use HasFactory;

    protected $table = 'inspection_details';
    
    protected $fillable = [
        'inspection_id',
        'category_id',
        'parameter_pemeriksaan',
        'parameter_id',
        'point',
        'cleanliness_rating',
        'photo_paths',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'point' => 'integer',
        'photo_paths' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship dengan Inspection
    public function inspection()
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    // Relationship dengan QaCategory
    public function category()
    {
        return $this->belongsTo(QaCategory::class, 'category_id');
    }

    // Relationship dengan QaParameter
    public function parameter()
    {
        return $this->belongsTo(QaParameter::class, 'parameter_id');
    }

    // Relationship dengan User (creator)
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope untuk compliance
    public function scopeCompliance($query)
    {
        return $query->where('finding_type', 'Compliance');
    }

    // Scope untuk non-compliance
    public function scopeNonCompliance($query)
    {
        return $query->where('finding_type', 'Non-Compliance');
    }

    // Accessor untuk photo URL
    public function getPhotoUrlAttribute()
    {
        if ($this->photo_path) {
            return asset('storage/' . $this->photo_path);
        }
        return null;
    }
}
