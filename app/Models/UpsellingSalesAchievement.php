<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UpsellingSalesAchievement extends Model
{
    protected $fillable = [
        'outlet_id',
        'month',
        'year',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(UpsellingSalesAchievementItem::class, 'achievement_id')->orderBy('sort_order');
    }
}
