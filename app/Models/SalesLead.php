<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLead extends Model
{
    public const SEGMENT_1 = [
        'Corporate',
        'Government',
        'BUMN',
        'Travel Agent',
        'Individual',
        'Community / NGO',
    ];

    public const SEGMENT_2 = [
        'Banking & Financial Services',
        'FMCG',
        'Media - Agency - Creative Industry',
        'Industrial/Manufacturing',
        'Education',
        'Healthcare',
        'Otomotif',
        'Family',
    ];

    public const TYPE_OF_EVENTS = [
        'Meeting',
        'Business Lunch/Dinner',
        'Company Gathering',
        'Birthday',
        'Anniversary',
        'Arisan',
        'Engagement',
        'Home Service',
        'Family Gathering',
    ];

    public const STATUSES = [
        'new_leads' => 'New Leads',
        'connected' => 'Connected',
        'quotation_send' => 'Quotation Send',
        'confirmed' => 'Confirmed',
        'no_respond' => 'No Respond',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'number',
        'company_name',
        'segment_1',
        'segment_2',
        'type_of_event',
        'event_date',
        'pax',
        'real_pax',
        'outlet_id',
        'status',
        'revenue',
        'real_revenue',
        'bill_photo',
        'sales_user_id',
        'remarks',
        'closing_remarks',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['bill_photo_url'];

    protected $casts = [
        'event_date' => 'date',
        'revenue' => 'decimal:2',
        'real_revenue' => 'decimal:2',
        'pax' => 'integer',
        'real_pax' => 'integer',
    ];

    public function getBillPhotoUrlAttribute(): ?string
    {
        if (! $this->bill_photo) {
            return null;
        }

        return asset('storage/'.$this->bill_photo);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
