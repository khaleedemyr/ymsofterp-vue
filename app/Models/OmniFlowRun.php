<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmniFlowRun extends Model
{
    protected $fillable = [
        'flow_id',
        'conversation_id',
        'trigger_message_id',
        'status',
        'current_step_index',
        'context',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'current_step_index' => 'integer',
        'context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(OmniFlow::class, 'flow_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OmniConversation::class, 'conversation_id');
    }

    public function triggerMessage(): BelongsTo
    {
        return $this->belongsTo(OmniMessage::class, 'trigger_message_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OmniFlowRunLog::class, 'flow_run_id');
    }
}
