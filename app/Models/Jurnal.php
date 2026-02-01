<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jurnal extends Model
{
    protected $table = 'jurnal';

    protected $fillable = [
        'no_jurnal',
        'tanggal',
        'keterangan',
        'coa_debit_id',
        'coa_kredit_id',
        'jumlah_debit',
        'jumlah_kredit',
        'outlet_id',
        'warehouse_id',
        'reference_type',
        'reference_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
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

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    public static function generateNoJurnal()
    {
        $year = date('Y');
        $month = date('m');
        
        $lastJurnal = self::where('no_jurnal', 'like', "JRN-$year$month%")
            ->orderBy('no_jurnal', 'desc')
            ->first();
        
        if ($lastJurnal) {
            $lastSequence = intval(substr($lastJurnal->no_jurnal, -4));
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }
        
        return "JRN-$year$month" . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }
}

