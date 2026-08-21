<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NpdServiceCalibrationProduct extends Model
{
    protected $fillable = [
        'calibration_id',
        'item_id',
        'item_name',
        'category_name',
        'sub_category_name',
        'sort_order',
    ];

    public function calibration(): BelongsTo
    {
        return $this->belongsTo(NpdServiceCalibration::class, 'calibration_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(NpdServiceCalibrationResult::class, 'calibration_product_id');
    }
}
