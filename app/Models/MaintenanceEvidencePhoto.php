<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MaintenanceEvidencePhoto extends Model
{
    protected $fillable = [
        'evidence_id',
        'path',
        'file_name',
        'file_type',
        'file_size'
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(MaintenanceEvidence::class, 'evidence_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
} 