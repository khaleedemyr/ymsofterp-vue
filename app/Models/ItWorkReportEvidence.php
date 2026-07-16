<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ItWorkReportEvidence extends Model
{
    public const KIND_WA_SCREENSHOT = 'wa_screenshot';

    public const KIND_WORK = 'work';

    public const KIND_OTHER = 'other';

    protected $table = 'it_work_report_evidences';

    protected $fillable = [
        'it_work_report_id',
        'it_work_report_item_id',
        'kind',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'caption',
        'latitude',
        'longitude',
        'address',
        'maps_url',
        'captured_at',
        'uploaded_by',
    ];

    protected $appends = ['url', 'is_image', 'is_video'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'captured_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ItWorkReport::class, 'it_work_report_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItWorkReportItem::class, 'it_work_report_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsImageAttribute(): bool
    {
        $mime = (string) ($this->mime_type ?? '');
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) ($this->original_name ?? ''));
    }

    public function getIsVideoAttribute(): bool
    {
        $mime = (string) ($this->mime_type ?? '');
        if (str_starts_with($mime, 'video/')) {
            return true;
        }

        return (bool) preg_match('/\.(mp4|mov|webm|avi|mpeg|3gp|m4v)$/i', (string) ($this->original_name ?? ''));
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
