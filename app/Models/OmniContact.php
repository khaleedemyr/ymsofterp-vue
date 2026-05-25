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
        'avatar_url',
        'member_apps_member_id',
        'marital_status',
        'preferred_outlet_id',
        'preferred_area',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_apps_member_id');
    }

    public function preferredOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'preferred_outlet_id', 'id_outlet');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(OmniConversation::class, 'omni_contact_id');
    }
}
