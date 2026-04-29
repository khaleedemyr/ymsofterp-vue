<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleReviewManualReview extends Model
{
    protected $table = 'google_review_manual_reviews';

    protected $fillable = [
        'id_outlet',
        'nama_outlet',
        'author',
        'rating',
        'review_date',
        'text',
        'profile_photo',
        'is_active',
        'created_by',
        'updated_by',
    ];
}

