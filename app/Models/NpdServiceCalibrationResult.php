<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpdServiceCalibrationResult extends Model
{
    protected $fillable = [
        'calibration_id',
        'participant_id',
        'calibration_product_id',
        'menu_knowledge',
        'menu_explanation',
        'suggestive_selling',
        'production_presentation',
        'serving_standard',
        'handling_guest_question',
    ];

    public function calibration(): BelongsTo
    {
        return $this->belongsTo(NpdServiceCalibration::class, 'calibration_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(NpdServiceCalibrationParticipant::class, 'participant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(NpdServiceCalibrationProduct::class, 'calibration_product_id');
    }
}
