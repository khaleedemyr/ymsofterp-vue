<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsFeedback extends Model
{
    protected $table = 'member_apps_feedbacks';
    
    protected $fillable = [
        'parent_id',
        'member_id',
        'outlet_id',
        'subject',
        'message',
        'rating',
        'status',
        'admin_reply',
        'replied_by',
        'replied_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'replied_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    public function parent()
    {
        return $this->belongsTo(MemberAppsFeedback::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(MemberAppsFeedback::class, 'parent_id')->orderBy('created_at', 'asc');
    }
}

