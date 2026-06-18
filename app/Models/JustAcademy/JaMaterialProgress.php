<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaMaterialProgress extends Model
{
    protected $table = 'ja_material_progress';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'material_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(JaProgramMaterial::class, 'material_id');
    }
}
