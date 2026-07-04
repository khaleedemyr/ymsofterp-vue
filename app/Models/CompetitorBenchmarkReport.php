<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompetitorBenchmarkReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'report_month',
        'outlet_id',
        'outlet_name',
        'pics',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'report_month' => 'date:Y-m-d',
        'pics' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CompetitorBenchmarkReportItem::class, 'report_id')->orderBy('sort_order');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(CompetitorBenchmarkReportApprovalFlow::class, 'report_id')->orderBy('approval_level');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
