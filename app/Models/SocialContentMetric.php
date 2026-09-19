<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialContentMetric extends Model
{
    protected $fillable = [
        'social_content_post_id',
        'likes',
        'comments',
        'shares',
        'saved',
        'impressions',
        'reach',
        'clicks',
        'synced_at',
    ];

    protected $casts = [
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'saved' => 'integer',
        'impressions' => 'integer',
        'reach' => 'integer',
        'clicks' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialContentPost::class, 'social_content_post_id');
    }
}
