<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends Model
{
    protected $table = 'asset_disposals';
    
    protected $fillable = [
        'asset_id',
        'disposal_date',
        'disposal_method',
        'disposal_value',
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
        'disposal_date' => 'date',
        'disposal_value' => 'decimal:2',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'asset_id' => 'integer',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
    ];

    /**
     * Get the asset being disposed
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Get the user who requested the disposal
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the disposal
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if disposal is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }
}

