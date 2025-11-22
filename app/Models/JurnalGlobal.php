<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalGlobal extends Model
{
    protected $table = 'jurnal_global';

    protected $fillable = [
        'no_jurnal',
        'tanggal',
        'keterangan',
        'coa_debit_id',
        'coa_kredit_id',
        'jumlah_debit',
        'jumlah_kredit',
        'outlet_id',
        'source_module',
        'source_id',
        'reference_type',
        'reference_id',
        'status',
        'posted_at',
        'posted_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'posted_at' => 'datetime',
        'jumlah_debit' => 'decimal:2',
        'jumlah_kredit' => 'decimal:2',
    ];

    public function coaDebit(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_debit_id');
    }

    public function coaKredit(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_kredit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }
}

