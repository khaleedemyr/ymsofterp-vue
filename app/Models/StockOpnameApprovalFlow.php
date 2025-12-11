<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameApprovalFlow extends Model
{
    use HasFactory;

    protected $table = 'outlet_stock_opname_approval_flows';

    protected $fillable = [
        'stock_opname_id',
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

    // Relationships
    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // Scopes
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

    public function scopeByLevel($query, $level)
    {
        return $query->where('approval_level', $level);
    }

    public function scopeOrderedByLevel($query)
    {
        return $query->orderBy('approval_level', 'asc');
    }

    // Methods
    public function isPending()
    {
        return $this->status === 'PENDING';
    }

    public function isApproved()
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected()
    {
        return $this->status === 'REJECTED';
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

