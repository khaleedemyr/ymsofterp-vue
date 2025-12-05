<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsDiscussionReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_discussion_replies';

    protected $fillable = [
        'discussion_id',
        'user_id',
        'content',
        'is_solution',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_solution' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function discussion()
    {
        return $this->belongsTo(LmsDiscussion::class, 'discussion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function scopeSolutions($query)
    {
        return $query->where('is_solution', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors
    public function getIsSolutionTextAttribute()
    {
        return $this->is_solution ? 'Solusi' : 'Balasan';
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    // Methods
    public function canBeEditedByUser($userId)
    {
        return $this->user_id === $userId || auth()->user()->hasRole('admin');
    }

    public function canBeDeletedByUser($userId)
    {
        return $this->user_id === $userId || auth()->user()->hasRole('admin');
    }

    public function markAsSolution()
    {
        // Remove solution mark from other replies in the same discussion
        $this->discussion->replies()
            ->where('id', '!=', $this->id)
            ->update(['is_solution' => false]);

        $this->is_solution = true;
        $this->save();
    }

    public function removeSolutionMark()
    {
        $this->is_solution = false;
        $this->save();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reply) {
            if (!$reply->created_by) {
                $reply->created_by = auth()->id();
            }
            if (!$reply->updated_by) {
                $reply->updated_by = auth()->id();
            }
        });

        static::updating(function ($reply) {
            $reply->updated_by = auth()->id();
        });
    }
} 