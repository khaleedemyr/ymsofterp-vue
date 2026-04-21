<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestCommentForm extends Model
{
    protected $table = 'guest_comment_forms';

    protected $fillable = [
        'image_path',
        'status',
        'ocr_raw_text',
        'ocr_payload',
        'rating_service',
        'rating_food',
        'rating_beverage',
        'rating_cleanliness',
        'rating_staff',
        'rating_value',
        'comment_text',
        'guest_name',
        'guest_address',
        'guest_phone',
        'guest_dob',
        'visit_date',
        'praised_staff_name',
        'praised_staff_outlet',
        'marketing_source',
        'issue_severity',
        'issue_topics',
        'issue_summary_id',
        'issue_classified_at',
        'id_outlet',
        'created_by',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'ocr_payload' => 'array',
            'issue_topics' => 'array',
            'guest_dob' => 'date',
            'issue_classified_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
