<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLeadReoApproval extends Model
{
    protected $fillable = [
        'reo_id',
        'approver_id',
        'approval_level',
        'status',
        'signature_path',
        'notes',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'approval_level' => 'integer',
    ];

    protected $appends = ['signature_url'];

    public function getSignatureUrlAttribute(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return asset('storage/'.$this->signature_path);
    }

    public function reo(): BelongsTo
    {
        return $this->belongsTo(SalesLeadReo::class, 'reo_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
