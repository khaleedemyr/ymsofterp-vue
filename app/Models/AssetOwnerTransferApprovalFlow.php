<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetOwnerTransferApprovalFlow extends Model
{
    protected $table = 'asset_owner_transfer_approval_flows';
    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function transfer()
    {
        return $this->belongsTo(AssetOwnerTransfer::class, 'asset_owner_transfer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function approve(?string $comments = null): void
    {
        $this->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'comments' => $comments,
        ]);
    }

    public function reject(?string $comments = null): void
    {
        $this->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'comments' => $comments,
        ]);
    }
}
