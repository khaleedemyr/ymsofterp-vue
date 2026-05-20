<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmniContact extends Model
{
    protected $fillable = [
        'phone_normalized',
        'display_name',
        'member_apps_member_id',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_apps_member_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(OmniConversation::class, 'omni_contact_id');
    }
}
