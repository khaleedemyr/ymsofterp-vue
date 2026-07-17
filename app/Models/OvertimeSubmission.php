<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'submission_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'submission_date' => 'date:Y-m-d',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OvertimeSubmissionItem::class, 'submission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
