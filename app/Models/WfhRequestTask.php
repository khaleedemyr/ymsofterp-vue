<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfhRequestTask extends Model
{
    protected $fillable = [
        'wfh_request_id',
        'sort_order',
        'description',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(WfhRequest::class, 'wfh_request_id');
    }
}
