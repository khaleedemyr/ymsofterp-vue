<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $table = 'approval_requests';

    protected $fillable = [
        'user_id',
        'approver_id',
        'hrd_approver_id',
        'leave_type_id',
        'date_from',
        'date_to',
        'reason',
        'document_path',
        'status',
        'hrd_status',
        'approved_at',
        'rejected_at',
        'hrd_approved_at',
        'hrd_rejected_at',
        'approval_notes',
        'hrd_approval_notes'
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'hrd_approved_at' => 'datetime',
        'hrd_rejected_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function hrdApprover()
    {
        return $this->belongsTo(User::class, 'hrd_approver_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForApprover($query, $approverId)
    {
        return $query->where('approver_id', $approverId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePendingHrd($query)
    {
        return $query->where('status', 'approved')->where('hrd_status', 'pending');
    }

    public function scopeForHrdApprover($query, $hrdApproverId)
    {
        return $query->where('hrd_approver_id', $hrdApproverId);
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        if ($this->status === 'pending') {
            return 'Menunggu Approval Atasan';
        } elseif ($this->status === 'approved' && $this->hrd_status === 'pending') {
            return 'Menunggu Approval HRD';
        } elseif ($this->status === 'approved' && $this->hrd_status === 'approved') {
            return 'Disetujui';
        } elseif ($this->status === 'rejected' || $this->hrd_status === 'rejected') {
            return 'Ditolak';
        }
        return 'Unknown';
    }

    public function getStatusColorAttribute()
    {
        if ($this->status === 'pending') {
            return 'yellow';
        } elseif ($this->status === 'approved' && $this->hrd_status === 'pending') {
            return 'blue';
        } elseif ($this->status === 'approved' && $this->hrd_status === 'approved') {
            return 'green';
        } elseif ($this->status === 'rejected' || $this->hrd_status === 'rejected') {
            return 'red';
        }
        return 'gray';
    }

    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->date_from);
        $end = \Carbon\Carbon::parse($this->date_to);
        return $start->diffInDays($end) + 1; // +1 to include both start and end dates
    }

    public function getDurationTextAttribute()
    {
        $duration = $this->duration;
        return $duration . ' hari';
    }

    // Methods
    public function approve($notes = null)
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);

        return $this;
    }

    public function reject($notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'approval_notes' => $notes
        ]);

        return $this;
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected' || $this->hrd_status === 'rejected';
    }

    public function isPendingHrd()
    {
        return $this->status === 'approved' && $this->hrd_status === 'pending';
    }

    public function isFullyApproved()
    {
        return $this->status === 'approved' && $this->hrd_status === 'approved';
    }

    public function approveHrd($notes = null)
    {
        $this->update([
            'hrd_status' => 'approved',
            'hrd_approved_at' => now(),
            'hrd_approval_notes' => $notes
        ]);

        return $this;
    }

    public function rejectHrd($notes = null)
    {
        $this->update([
            'hrd_status' => 'rejected',
            'hrd_rejected_at' => now(),
            'hrd_approval_notes' => $notes
        ]);

        return $this;
    }

    // Accessors for approval information
    public function getApprovedByAttribute()
    {
        if ($this->status === 'approved' && $this->approver) {
            return $this->approver->nama_lengkap;
        }
        return null;
    }

    public function getApprovedAtAttribute()
    {
        return $this->attributes['approved_at'];
    }

    public function getHrdApprovedByAttribute()
    {
        if ($this->hrd_status === 'approved' && $this->hrdApprover) {
            return $this->hrdApprover->nama_lengkap;
        }
        return null;
    }

    public function getHrdApprovedAtAttribute()
    {
        return $this->attributes['hrd_approved_at'];
    }

    public function getRejectedByAttribute()
    {
        if ($this->status === 'rejected' && $this->approver) {
            return $this->approver->nama_lengkap;
        }
        if ($this->hrd_status === 'rejected' && $this->hrdApprover) {
            return $this->hrdApprover->nama_lengkap;
        }
        return null;
    }

    public function getRejectedAtAttribute()
    {
        if ($this->status === 'rejected') {
            return $this->attributes['rejected_at'];
        }
        if ($this->hrd_status === 'rejected') {
            return $this->attributes['hrd_rejected_at'];
        }
        return null;
    }
}
