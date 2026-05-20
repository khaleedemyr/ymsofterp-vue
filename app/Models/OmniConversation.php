<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'last_message_at',
        'last_message_preview',
        'unread_count',
        'status',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(OmniMessage::class, 'conversation_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_apps_member_id');
    }
}
