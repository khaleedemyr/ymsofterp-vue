<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsOccupation extends Model
{
    protected $table = 'member_apps_occupations';
    
    protected $fillable = [
        'name',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function members()
    {
        return $this->hasMany(MemberAppsMember::class, 'pekerjaan_id');
    }
}

