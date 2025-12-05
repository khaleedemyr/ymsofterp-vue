<?php

namespace App\Models\OpsKitchen;

use Illuminate\Database\Eloquent\Model;

class ActionPlanGuestReviewImage extends Model
{
    protected $table = 'action_plan_guest_review_images';
    protected $fillable = ['action_plan_guest_review_id', 'path'];
} 