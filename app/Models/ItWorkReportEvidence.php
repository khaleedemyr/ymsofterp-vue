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
        'kind',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'caption',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ItWorkReport::class, 'it_work_report_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
