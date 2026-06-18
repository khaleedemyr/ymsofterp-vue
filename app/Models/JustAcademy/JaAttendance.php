<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaAttendance extends Model
{
    protected $table = 'ja_attendances';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'check_in_at',
        'check_out_at',
        'method',
        'marked_by',
        'notes',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(JaSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
