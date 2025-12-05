<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicInspectionDetail extends Model
{
    protected $fillable = [
        'dynamic_inspection_id',
        'inspection_subject_id',
        'inspection_subject_item_id',
        'is_checked',
        'notes',
        'documentation_paths'
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'documentation_paths' => 'array'
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(DynamicInspection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(InspectionSubject::class, 'inspection_subject_id');
    }

    public function subjectItem(): BelongsTo
    {
        return $this->belongsTo(InspectionSubjectItem::class, 'inspection_subject_item_id');
    }
}
