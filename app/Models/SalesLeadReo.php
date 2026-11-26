<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesLeadReo extends Model
{
    public const STATUSES = [
        'DRAFT' => 'Draft',
        'SUBMITTED' => 'Submitted',
        'APPROVED' => 'Approved',
        'REJECTED' => 'Rejected',
    ];

    protected $fillable = [
        'sales_lead_id',
        'number',
        'company_name',
        'address',
        'contact_person',
        'mobile',
        'email',
        'date_of_issue',
        'venue_setup',
        'sales_person_user_id',
        'sales_person_name',
        'sign_board',
        'revenue_items',
        'catering_fee_percent',
        'subtotal_rate',
        'subtotal_nett',
        'catering_fee_amount',
        'total_restaurant_revenue',
        'total_transaction',
        'total_amount',
        'pax',
        'date_of_event',
        'time_of_event',
        'menu_items',
        'kitchen_maintenance',
        'marcom',
        'billing_instruction',
        'prepared_by_name',
        'prepared_by_title',
        'acknowledge_by_1_name',
        'acknowledge_by_1_title',
        'acknowledge_by_2_name',
        'acknowledge_by_2_title',
        'approved_by_name',
        'approved_by_title',
        'status',
        'created_by',
        'updated_by',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'revenue_items' => 'array',
        'time_of_event' => 'array',
        'catering_fee_percent' => 'decimal:2',
        'subtotal_rate' => 'decimal:2',
        'subtotal_nett' => 'decimal:2',
        'catering_fee_amount' => 'decimal:2',
        'total_restaurant_revenue' => 'decimal:2',
        'total_transaction' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'pax' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected function dateOfIssue(): Attribute
    {
        return $this->dateOnlyAttribute();
    }

    protected function dateOfEvent(): Attribute
    {
        return $this->dateOnlyAttribute();
    }

    private function dateOnlyAttribute(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }

                return Carbon::parse($value)->toDateString();
            },
            set: function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }
                if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m)) {
                    return $m[0];
                }

                return Carbon::parse($value)->toDateString();
            },
        );
    }

    public function salesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SalesLeadReoApproval::class, 'reo_id')->orderBy('approval_level');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
