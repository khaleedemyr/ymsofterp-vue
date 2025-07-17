<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoTutorialGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(VideoTutorial::class, 'group_id');
    }

    public function activeVideos(): HasMany
    {
        return $this->hasMany(VideoTutorial::class, 'group_id')->where('status', 'A');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'N');
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status === 'A' ? 'Active' : 'Inactive';
    }

    public function getVideosCountAttribute(): int
    {
        return $this->videos()->count();
    }

    public function getActiveVideosCountAttribute(): int
    {
        return $this->activeVideos()->count();
    }
} 