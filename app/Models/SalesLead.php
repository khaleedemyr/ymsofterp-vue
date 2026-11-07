<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'revenue' => 'decimal:2',
        'real_revenue' => 'decimal:2',
        'pax' => 'integer',
        'real_pax' => 'integer',
    ];

    /**
     * Selalu kirim/simpan sebagai Y-m-d (tanpa timezone),
     * supaya tidak geser -1 hari saat di-JSON ke frontend (UTC vs Asia/Jakarta).
     */
    protected function eventDate(): Attribute
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
