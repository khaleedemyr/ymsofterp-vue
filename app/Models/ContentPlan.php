<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlan extends Model
{
    public const PLATFORMS = [
        'Instagram',
        'TVC',
        'Threads',
        'Website',
        'LinkedIn',
        'Tiktok',
        'Member Apps',
        'Printing Media Promo',
    ];

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
        'Carousel Design',
        'Carousel Video',
        'Still Image',
        'Reels',
        'Motion',
        'Story',
        'Video TVC',
        'Banner Digital',
        'Print Media',
        'Menu',
    ];

    /** Legacy values kept so existing records remain editable. */
    public const LEGACY_PLATFORMS = ['TikTok'];

    public const LEGACY_FORMATS = [
        'Carousel - Design',
        'Carousel - Video',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
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
        'brand_id',
        'pic_user_id',
        'platform',
        'deadline_content',
        'date_post',
        'day_name',
        'funnel',
        'pillar',
        'format',
        'objective',
        'brief',
        'reference',
        'reference_images',
        'caption',
        'priority',
        'pic_submit_date',
        'task',
        'job_status',
        'final_output_links',
        'actual_post_date',
        'actual_post_links',
        'remarks',
        'pic_remarks',
        'workflow_status',
        'created_by',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'deadline_content' => 'date',
        'date_post' => 'date',
        'pic_submit_date' => 'date',
        'actual_post_date' => 'date',
        'reference_images' => 'array',
        'final_output_links' => 'array',
        'actual_post_links' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

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

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ($this->priority ?: '-');
    }

    public static function allowedPlatforms(): array
    {
        return array_values(array_unique(array_merge(self::PLATFORMS, self::LEGACY_PLATFORMS)));
    }

    public static function allowedFormats(): array
    {
        return array_values(array_unique(array_merge(self::FORMATS, self::LEGACY_FORMATS)));
    }
}
