<?php

namespace App\Models\JustAcademy;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaSchedule extends Model
{
    protected $table = 'ja_schedules';

    protected $fillable = [
        'program_id',
        'title',
        'start_at',
        'end_at',
        'location',
        'outlet_id',
        'region_id',
        'capacity',
        'status',
        'qr_token',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'capacity' => 'integer',
        'outlet_id' => 'integer',
        'region_id' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(JaProgram::class, 'program_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(JaScheduleParticipant::class, 'schedule_id');
    }

    public function trainers(): HasMany
    {
        return $this->hasMany(JaScheduleTrainer::class, 'schedule_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(JaAttendance::class, 'schedule_id');
    }

    public function inviteLogs(): HasMany
    {
        return $this->hasMany(JaScheduleInviteLog::class, 'schedule_id');
    }
}
