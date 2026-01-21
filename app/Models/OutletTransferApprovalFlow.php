<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletTransferApprovalFlow extends Model
{
    use HasFactory;

    protected $table = 'outlet_transfer_approval_flows';

    protected $fillable = [
        'outlet_transfer_id',
        'approver_id',
        'approval_level',
        'status',
        'approved_at',
        'rejected_at',
        'comments',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function transfer()
    {
        return $this->belongsTo(OutletTransfer::class, 'outlet_transfer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeOrderedByLevel($query)
    {
        return $query->orderBy('approval_level', 'asc');
    }

    public function approve($comments = null)
    {
        $this->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'comments' => $comments,
        ]);
    }

    public function reject($comments = null)
    {
        $this->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'comments' => $comments,
        ]);
    }
}

