<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbProductCalibrationResult extends Model
{
    protected $fillable = [
        'calibration_id',
        'participant_id',
        'calibration_product_id',
        'presentation',
        'taste_profile',
        'portion_size',
        'recipe_compliance',
        'cooking_method',
        'texture',
        'temperature',
    ];

    public function calibration(): BelongsTo
    {
        return $this->belongsTo(FbProductCalibration::class, 'calibration_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FbProductCalibrationParticipant::class, 'participant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FbProductCalibrationProduct::class, 'calibration_product_id');
    }
}
