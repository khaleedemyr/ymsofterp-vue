<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingVisitChecklist extends Model
{
    protected $table = 'marketing_visit_checklists';
    protected $fillable = [
        'outlet_id',
        'visit_date',
        'created_by',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketingVisitChecklistItem::class, 'checklist_id');
    }
} 