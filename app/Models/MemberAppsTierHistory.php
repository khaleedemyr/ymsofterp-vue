<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsTierHistory extends Model
{
    protected $table = 'member_apps_tier_history';
    
    protected $fillable = [
        'member_id',
        'old_tier',
        'new_tier',
        'total_spending',
        'spending_period_start',
        'spending_period_end',
        'change_reason',
        'changed_at'
    ];

    protected $casts = [
        'total_spending' => 'decimal:2',
        'spending_period_start' => 'date',
        'spending_period_end' => 'date',
        'changed_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }
}

