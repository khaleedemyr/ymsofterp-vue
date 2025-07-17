<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class VideoTutorial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'title',
        'description',
        'video_path',
        'video_name',
        'video_type',
        'video_size',
        'thumbnail_path',
        'duration',
        'status',
        'created_by',
    ];

    protected $casts = [
        'video_size' => 'integer',
        'duration' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'video_url',
        'thumbnail_url',
        'video_size_formatted',
        'duration_formatted',
        'status_text',
        'creator_name',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(VideoTutorialGroup::class, 'group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function getCreatorNameAttribute(): string
    {
        return $this->creator?->nama_lengkap ?? '-';
    }

    public function getVideoUrlAttribute(): string
    {
        if (!$this->video_path) {
            return '';
        }
        
        // Generate URL directly without checking file existence
        return '/storage/' . $this->video_path;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }
        
        // Generate URL directly without checking file existence
        return '/storage/' . $this->thumbnail_path;
    }

    public function getVideoSizeFormattedAttribute(): string
    {
        $bytes = $this->video_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration) {
            return 'Unknown';
        }
        
        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
} 