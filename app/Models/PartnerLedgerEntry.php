<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLedgerEntry extends Model
{
    protected $table = 'partner_ledger_entries';

    protected $fillable = [
        'sub_ledger_id',
        'entry_type',
        'amount',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'jurnal_id',
        'no_jurnal',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function subLedger(): BelongsTo
    {
        return $this->belongsTo(PartnerSubLedger::class, 'sub_ledger_id');
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'jurnal_id');
    }
}
