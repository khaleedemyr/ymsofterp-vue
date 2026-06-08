<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmniConversation extends Model
{
    protected $fillable = [
        'channel',
        'external_contact_id',
        'contact_name',
        'phone_number_id',
        'waba_id',
        'member_apps_member_id',
        'omni_contact_id',
        'assigned_user_id',
        'lead_stage',
        'memo',
        'contact_first_name',
        'contact_last_name',
        'contact_email',
        'contact_company',
        'contact_job_title',
        'last_message_at',
        'last_customer_message_at',
        'last_message_preview',
        'unread_count',
        'status',
        'automation_paused',
        'active_flow_run_id',
        'complaint_severity',
        'complaint_snippet',
        'complaint_message_id',
        'complaint_detected_at',
        'feedback_case_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'complaint_detected_at' => 'datetime',
        'unread_count' => 'integer',
        'automation_paused' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(OmniMessage::class, 'conversation_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_apps_member_id');
    }

    public function omniContact(): BelongsTo
    {
        return $this->belongsTo(OmniContact::class, 'omni_contact_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'omni_conversation_assignees', 'conversation_id', 'user_id')
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(OmniTeam::class, 'omni_conversation_teams', 'conversation_id', 'team_id')
            ->withTimestamps();
    }

    public function activeFlowRun(): BelongsTo
    {
        return $this->belongsTo(OmniFlowRun::class, 'active_flow_run_id');
    }
}
