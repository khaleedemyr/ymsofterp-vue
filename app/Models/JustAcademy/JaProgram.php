<?php

namespace App\Models\JustAcademy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaProgram extends Model
{
    protected $table = 'ja_programs';

    protected $fillable = [
        'category_id',
        'code',
        'title',
        'description',
        'duration_hours',
        'status',
        'created_by',
    ];

    protected $casts = [
        'duration_hours' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(JaCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JaProgramItem::class, 'program_id')->orderBy('sort_order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(JaSchedule::class, 'program_id');
    }
}
