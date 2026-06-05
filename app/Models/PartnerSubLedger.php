<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerSubLedger extends Model
{
    protected $table = 'partner_sub_ledgers';

    protected $fillable = [
        'ledger_type',
        'partner_type',
        'partner_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(PartnerLedgerEntry::class, 'sub_ledger_id');
    }
}
