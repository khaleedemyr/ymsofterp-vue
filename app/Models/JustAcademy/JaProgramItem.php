<?php

namespace App\Models\JustAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaProgramItem extends Model
{
    protected $table = 'ja_program_items';

    protected $fillable = [
        'program_id',
        'item_type',
        'material_id',
        'quiz_id',
        'sort_order',
        'is_required',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(JaProgram::class, 'program_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(JaMaterial::class, 'material_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(JaQuiz::class, 'quiz_id');
    }
}
