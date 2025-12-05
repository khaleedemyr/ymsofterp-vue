<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceEvidence extends Model
{
    protected $fillable = [
        'task_id',
        'created_by',
        'notes'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTask::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenanceEvidencePhoto::class, 'evidence_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(MaintenanceEvidenceVideo::class, 'evidence_id');
    }
} 