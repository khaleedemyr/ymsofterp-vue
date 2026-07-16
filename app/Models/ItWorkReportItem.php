<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItWorkReportItem extends Model
{
    protected $table = 'it_work_report_items';

    protected $fillable = [
        'it_work_report_id',
        'device_type',
        'device_label',
        'identifier',
        'laptop_user_name',
        'scopes',
        'notes',
        'result',
        'sort_order',
    ];

    protected $casts = [
        'scopes' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ItWorkReport::class, 'it_work_report_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ItWorkReportEvidence::class, 'it_work_report_item_id')->orderBy('id');
    }
}
