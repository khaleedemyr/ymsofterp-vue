<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialContentPost extends Model
{
    protected $fillable = [
        'platform',
        'account_id',
        'account_label',
        'external_id',
        'caption',
        'permalink',
        'thumbnail_url',
        'media_type',
        'posted_at',
        'last_synced_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function metrics(): HasOne
    {
        return $this->hasOne(SocialContentMetric::class, 'social_content_post_id');
    }

    public function engagementTotal(): int
    {
        $m = $this->metrics;
        if (! $m) {
            return 0;
        }

        return (int) ($m->likes ?? 0)
            + (int) ($m->comments ?? 0)
            + (int) ($m->shares ?? 0)
            + (int) ($m->saved ?? 0);
    }
}
