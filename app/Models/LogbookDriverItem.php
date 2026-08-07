<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LogbookDriverItem extends Model
{
    protected $table = 'logbook_driver_items';

    protected $fillable = [
        'logbook_driver_id',
        'log_time',
        'description',
        'photo_path',
        'sort_order',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function logbookDriver(): BelongsTo
    {
        return $this->belongsTo(LogbookDriver::class, 'logbook_driver_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }
}
