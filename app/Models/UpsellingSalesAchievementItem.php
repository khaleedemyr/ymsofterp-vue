<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsellingSalesAchievementItem extends Model
{
    protected $fillable = [
        'achievement_id',
        'item_id',
        'item_name',
        'category_label',
        'average_check',
        'cover',
        'fb_revenue',
        'sort_order',
    ];

    protected $casts = [
        'average_check' => 'decimal:2',
        'cover' => 'integer',
        'fb_revenue' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(UpsellingSalesAchievement::class, 'achievement_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
