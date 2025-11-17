<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPointRedemptionDetail extends Model
{
    protected $table = 'member_apps_point_redemption_details';
    
    protected $fillable = [
        'redemption_id',
        'point_earning_id',
        'point_amount'
    ];

    protected $casts = [
        'point_amount' => 'integer',
    ];

    public function redemption()
    {
        return $this->belongsTo(MemberAppsPointRedemption::class, 'redemption_id');
    }

    public function earning()
    {
        return $this->belongsTo(MemberAppsPointEarning::class, 'point_earning_id');
    }
}

