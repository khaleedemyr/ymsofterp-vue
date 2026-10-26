<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlan extends Model
{
    public const PLATFORMS = ['Instagram', 'TikTok', 'Threads', 'LinkedIn'];

    public const FUNNELS = ['Purchase', 'Awareness', 'Consideration', 'Retention'];

    public const PILLARS = [
        'Promotion',
        'Product Highlight',
        'Education',
        'Entertainment',
        'Trend Jacking',
        'Greeting',
        'Product Story',
        'Experience',
    ];

    public const FORMATS = [
        'Carousel - Design',
        'Carousel - Video',
        'Still Image',
        'Reels',
        'Motion',
        'Story',
    ];

    public const JOB_STATUSES = [
        'on_crafting' => 'On Crafting',
        'waiting_list' => 'Waiting List',
        'rev_1' => 'Rev 1',
        'rev_2' => 'Rev 2',
    ];

    public const WORKFLOW_STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'number',
        'platform',
        'date_post',
        'day_name',
        'funnel',
        'pillar',
        'format',
        'objective',
        'brief',
        'reference',
        'caption',
        'job_status',
        'final_output_links',
        'actual_post_date',
        'actual_post_links',
        'remarks',
        'workflow_status',
        'created_by',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'date_post' => 'date',
        'actual_post_date' => 'date',
        'final_output_links' => 'array',
        'actual_post_links' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(ContentPlanApprovalFlow::class)->orderBy('approval_level');
    }

    public function jobStatusLabel(): string
    {
        return self::JOB_STATUSES[$this->job_status] ?? $this->job_status;
    }

    public function workflowStatusLabel(): string
    {
        return self::WORKFLOW_STATUSES[$this->workflow_status] ?? $this->workflow_status;
    }
}
