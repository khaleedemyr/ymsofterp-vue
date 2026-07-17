<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualCsComplaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'id_outlet',
        'author_name',
        'customer_contact',
        'customer_email',
        'input_channel',
        'event_at',
        'severity',
        'topics',
        'summary',
        'complaint_text',
        'notes',
        'sync_status',
        'feedback_case_id',
        'synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'synced_at' => 'datetime',
        'topics' => 'array',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
