<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderOpsApprovalFlow extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_ops_approval_flows';

    protected $fillable = [
        'purchase_order_ops_id',
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
    public function purchaseOrderOps()
    {
        return $this->belongsTo(PurchaseOrderOps::class, 'purchase_order_ops_id');
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

    // Accessors
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'PENDING' => 'warning',
            'APPROVED' => 'success',
            'REJECTED' => 'danger',
        ];

        return $badges[$this->status] ?? 'secondary';
    }

    public function getStatusTextAttribute()
    {
        $texts = [
            'PENDING' => 'Menunggu Approval',
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
        ];

        return $texts[$this->status] ?? 'Unknown';
    }
}
