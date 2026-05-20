<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OmniTeam extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'omni_team_user', 'team_id', 'user_id')
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(OmniConversation::class, 'omni_conversation_teams', 'team_id', 'conversation_id')
            ->withTimestamps();
    }
}
