<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BpjsKategori extends Model
{
    protected $table = 'tbl_bpjs_kategori';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'pct_kes_perusahaan' => 'decimal:4',
        'pct_kes_karyawan' => 'decimal:4',
        'pct_jht_perusahaan' => 'decimal:4',
        'pct_jp_perusahaan' => 'decimal:4',
        'pct_jkk_perusahaan' => 'decimal:4',
        'pct_jkm_perusahaan' => 'decimal:4',
        'pct_jht_karyawan' => 'decimal:4',
        'pct_jp_karyawan' => 'decimal:4',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
}
