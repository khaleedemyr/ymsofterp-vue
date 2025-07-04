<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingVisitChecklistItem extends Model
{
    protected $table = 'marketing_visit_checklist_items';
    protected $fillable = [
        'checklist_id',
        'no',
        'category',
        'checklist_point',
        'checked',
        'actual_condition',
        'action',
        'remarks',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(MarketingVisitChecklist::class, 'checklist_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MarketingVisitChecklistPhoto::class, 'item_id');
    }
} 