<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends Model
{
    protected $table = 'asset_transfers';
    
    protected $fillable = [
        'asset_id',
        'from_outlet_id',
        'to_outlet_id',
        'transfer_date',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'asset_id' => 'integer',
        'from_outlet_id' => 'integer',
        'to_outlet_id' => 'integer',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
    ];

    /**
     * Get the asset being transferred
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Get the outlet from which the asset is being transferred
     */
    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(DataOutlet::class, 'from_outlet_id', 'id_outlet');
    }

    /**
     * Get the outlet to which the asset is being transferred
     */
    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(DataOutlet::class, 'to_outlet_id', 'id_outlet');
    }

    /**
     * Get the user who requested the transfer
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the transfer
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

