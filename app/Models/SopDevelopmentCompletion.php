<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SopDevelopmentCompletion extends Model
{
    use HasFactory;

    protected $table = 'sop_development_completions';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'due_date',
        'file_path',
        'file_original_name',
        'status',
        'approval_notes',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'resubmission_count',
    ];

    protected $casts = [
        'due_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = [
        'status_text',
        'status_color',
        'is_overdue',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(SopDevelopmentCompletionApprovalFlow::class, 'sop_development_completion_id')
            ->orderBy('approval_level');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending' => 'Menunggu Approval',
            'approved' => 'Selesai',
            'rejected' => 'Ditolak',
            default => 'Unknown',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        if (! $this->due_date || in_array($this->status, ['approved'], true)) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function isEditableByOwner(): bool
    {
        return in_array($this->status, ['draft'], true);
    }

    public function canSubmitForApproval(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function canBeDeletedByOwner(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'rejected'], true);
    }
}
