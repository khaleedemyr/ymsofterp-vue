<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItWorkReport extends Model
{
    public const SOURCE_PROACTIVE = 'proactive';

    public const SOURCE_TICKET = 'ticket';

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const DEVICE_TYPES = [
        'pc' => 'PC',
        'laptop' => 'Laptop',
        'printer' => 'Printer',
        'scanner' => 'Scanner',
        'switch_ap' => 'Switch / AP',
        'nvr_cctv' => 'NVR / CCTV',
        'other' => 'Lainnya',
    ];

    public const SCOPES = [
        'cleaning_hardware' => 'Cleaning hardware',
        'os_software_check' => 'Cek OS dan software',
        'network' => 'Jaringan',
        'cctv' => 'CCTV',
        'peripheral' => 'Peripheral (printer/scanner)',
        'security_update' => 'Update / patch keamanan',
        'other' => 'Lainnya',
    ];

    public const RESULTS = [
        'ok' => 'OK',
        'issue_found' => 'Issue found',
        'needs_followup' => 'Needs follow-up',
    ];

    protected $table = 'it_work_reports';

    protected $fillable = [
        'number',
        'work_date',
        'start_time',
        'end_time',
        'outlet_id',
        'outlet_name',
        'executor_id',
        'source_type',
        'ticket_id',
        'wa_contact_name',
        'wa_phone',
        'wa_reported_at',
        'wa_summary',
        'title',
        'notes',
        'status',
        'created_by',
        'updated_by',
        'submitted_at',
    ];

    protected $casts = [
        // Y-m-d agar JSON tidak jadi ISO UTC (bisa geser ±1 hari di UI)
        'work_date' => 'date:Y-m-d',
        'wa_reported_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ItWorkReportItem::class, 'it_work_report_id')->orderBy('sort_order')->orderBy('id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ItWorkReportEvidence::class, 'it_work_report_id')->orderBy('id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
