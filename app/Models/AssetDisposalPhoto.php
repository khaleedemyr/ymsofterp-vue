<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetDisposalPhoto extends Model
{
    protected $table = 'asset_disposal_photos';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function disposal()
    {
        return $this->belongsTo(AssetDisposal::class, 'disposal_id');
    }
}
