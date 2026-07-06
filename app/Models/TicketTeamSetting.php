<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketTeamSetting extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(
            Region::class,
            'ticket_team_setting_regions',
            'ticket_team_setting_id',
            'region_id'
        )->withTimestamps();
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(
            Outlet::class,
            'ticket_team_setting_outlets',
            'ticket_team_setting_id',
            'outlet_id',
            'id',
            'id_outlet'
        )->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'ticket_team_setting_users',
            'ticket_team_setting_id',
            'user_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
}
