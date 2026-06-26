<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FbProductCalibration extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'outlet_id',
        'outlet_name',
        'scheduled_date',
        'conductor_id',
        'conductor_name',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conductor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(FbProductCalibrationProduct::class, 'calibration_id')->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FbProductCalibrationParticipant::class, 'calibration_id')->orderBy('sort_order');
    }

    public function results(): HasMany
    {
        return $this->hasMany(FbProductCalibrationResult::class, 'calibration_id');
    }
}
