<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Point extends Model
{
    /**
     * Menentukan connection database yang digunakan
     * 
     * @var string
     */
    protected $connection = 'mysql_second';

    /**
     * Nama tabel di database kedua
     * 
     * @var string
     */
    protected $table = 'point';

    /**
     * Primary key
     * 
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Mass assignment protection
     * 
     * @var array
     */
    protected $fillable = [
        'no_bill',
        'no_bill_2',
        'costumer_id',
        'cabang_id',
        'point',
        'jml_trans',
        'type',
        'created_at',
        'updated_at'
    ];

    /**
     * Cast attributes
     * 
     * @var array
     */
    protected $casts = [
        'point' => 'integer',
        'jml_trans' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship dengan Customer
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'costumer_id', 'id');
    }

    /**
     * Relationship dengan Cabang
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'id');
    }

    /**
     * Scope untuk top up point
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTopUp($query)
    {
        return $query->where('type', '1');
    }

    /**
     * Scope untuk redeem point
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRedeem($query)
    {
        return $query->where('type', '2');
    }

    /**
     * Scope untuk filter berdasarkan tanggal
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope untuk filter berdasarkan cabang
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cabangId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCabang($query, $cabangId)
    {
        return $query->where('cabang_id', $cabangId);
    }

    /**
     * Scope untuk exclude cabang "Reset Point"
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeResetPoint($query)
    {
        return $query->whereHas('cabang', function ($q) {
            $q->where('name', '!=', 'Reset Point');
        });
    }

    /**
     * Accessor untuk type yang readable
     * 
     * @return string
     */
    public function getTypeTextAttribute()
    {
        return $this->type === '1' ? 'Top Up' : 'Redeem';
    }

    /**
     * Accessor untuk bill number yang benar
     * 
     * @return string
     */
    public function getBillNumberAttribute()
    {
        return $this->type === '1' ? $this->no_bill : $this->no_bill_2;
    }

    /**
     * Accessor untuk point dengan format
     * 
     * @return string
     */
    public function getPointFormattedAttribute()
    {
        return number_format($this->point, 0, ',', '.');
    }

    /**
     * Accessor untuk jumlah transaksi dengan format
     * 
     * @return string
     */
    public function getJmlTransFormattedAttribute()
    {
        return 'Rp ' . number_format($this->jml_trans, 0, ',', '.');
    }

    /**
     * Accessor untuk tanggal yang readable
     * 
     * @return string
     */
    public function getCreatedAtTextAttribute()
    {
        return $this->created_at ? $this->created_at->format('d/m/Y H:i') : '-';
    }

    /**
     * Accessor untuk status transaksi
     * 
     * @return string
     */
    public function getStatusAttribute()
    {
        return $this->type === '1' ? 'success' : 'warning';
    }

    /**
     * Accessor untuk icon transaksi
     * 
     * @return string
     */
    public function getIconAttribute()
    {
        return $this->type === '1' ? 'fa-solid fa-plus-circle' : 'fa-solid fa-minus-circle';
    }

    /**
     * Accessor untuk warna transaksi
     * 
     * @return string
     */
    public function getColorAttribute()
    {
        return $this->type === '1' ? 'text-green-600' : 'text-orange-600';
    }
} 