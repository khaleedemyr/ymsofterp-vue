<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionSubjectItem extends Model
{
    protected $fillable = [
        'inspection_subject_id',
        'name',
        'description',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(InspectionSubject::class);
    }
}
