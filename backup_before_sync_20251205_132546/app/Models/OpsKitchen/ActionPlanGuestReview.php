<?php

namespace App\Models\OpsKitchen;

use Illuminate\Database\Eloquent\Model;

class ActionPlanGuestReview extends Model
{
    protected $table = 'action_plan_guest_reviews'; // Pastikan nama tabel sesuai
    protected $fillable = [
        'outlet', 'tanggal', 'dept', 'pic', 'problem', 'analisa', 'preventive', 'status', 'documentation'
    ];

    public function images()
    {
        return $this->hasMany(ActionPlanGuestReviewImage::class, 'action_plan_guest_review_id');
    }
}
