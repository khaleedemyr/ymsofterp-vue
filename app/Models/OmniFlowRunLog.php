<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmniFlowRunLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'flow_run_id',
        'step_index',
        'step_type',
        'status',
        'message',
        'created_at',
    ];

    protected $casts = [
        'step_index' => 'integer',
        'created_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(OmniFlowRun::class, 'flow_run_id');
    }
}
