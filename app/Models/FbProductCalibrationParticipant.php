<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FbProductCalibrationParticipant extends Model
{
    protected $fillable = [
        'calibration_id',
        'user_id',
        'user_name',
        'jabatan_name',
        'sort_order',
    ];

    public function calibration(): BelongsTo
    {
        return $this->belongsTo(FbProductCalibration::class, 'calibration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(FbProductCalibrationResult::class, 'participant_id');
    }
}
