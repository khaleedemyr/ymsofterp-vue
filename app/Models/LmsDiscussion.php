<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsDiscussion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_discussions';

    protected $fillable = [
        'course_id',
        'user_id',
        'title',
        'content',
        'is_pinned',
        'is_locked',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies()
    {
        return $this->hasMany(LmsDiscussionReply::class, 'discussion_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors
    public function getRepliesCountAttribute()
    {
        return $this->replies()->count();
    }

    public function getLastReplyAttribute()
    {
        return $this->replies()->latest()->first();
    }

    public function getLastActivityAttribute()
    {
        $lastReply = $this->lastReply;
        if ($lastReply) {
            return $lastReply->created_at;
        }
        return $this->created_at;
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active' && !$this->is_locked;
    }

    // Methods
    public function canBeRepliedByUser($userId)
    {
        return $this->isActive && !$this->is_locked;
    }

    public function canBeEditedByUser($userId)
    {
        return $this->user_id === $userId || auth()->user()->hasRole('admin');
    }

    public function canBeDeletedByUser($userId)
    {
        return $this->user_id === $userId || auth()->user()->hasRole('admin');
    }

    public function pin()
    {
        $this->is_pinned = true;
        $this->save();
    }

    public function unpin()
    {
        $this->is_pinned = false;
        $this->save();
    }

    public function lock()
    {
        $this->is_locked = true;
        $this->save();
    }

    public function unlock()
    {
        $this->is_locked = false;
        $this->save();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($discussion) {
            if (!$discussion->created_by) {
                $discussion->created_by = auth()->id();
            }
            if (!$discussion->updated_by) {
                $discussion->updated_by = auth()->id();
            }
        });

        static::updating(function ($discussion) {
            $discussion->updated_by = auth()->id();
        });
    }
} 