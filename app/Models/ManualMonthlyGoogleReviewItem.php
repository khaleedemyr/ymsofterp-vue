<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualMonthlyGoogleReviewItem extends Model
{
    protected $table = 'manual_monthly_google_review_items';

    protected $fillable = [
        'manual_monthly_google_review_id',
        'outlet_id',
        'rating',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(ManualMonthlyGoogleReview::class, 'manual_monthly_google_review_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
