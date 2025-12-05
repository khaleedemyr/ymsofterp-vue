<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingVisitChecklistPhoto extends Model
{
    protected $table = 'marketing_visit_checklist_photos';
    protected $fillable = [
        'item_id',
        'photo_path',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketingVisitChecklistItem::class, 'item_id');
    }
} 