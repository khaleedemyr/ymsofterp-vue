<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaProgramMaterial extends Model
{
    protected $table = 'ja_program_materials';

    protected $fillable = [
        'program_id',
        'title',
        'type',
        'file_path',
        'url',
        'sort_order',
        'is_pre_read',
        'is_active',
    ];

    protected $casts = [
        'is_pre_read' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(JaProgram::class, 'program_id');
    }
}
