<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Divisi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tbl_data_divisi';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama_divisi',
        'nominal_lembur',
        'nominal_uang_makan',
        'nominal_ph',
        'status'
    ];

    protected $casts = [
        'nominal_lembur' => 'integer',
        'nominal_uang_makan' => 'integer',
        'nominal_ph' => 'integer',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'N');
    }

    public function getStatusTextAttribute()
    {
        return $this->status === 'A' ? 'Active' : 'Inactive';
    }

    public function getFormattedNominalLemburAttribute()
    {
        return 'Rp ' . number_format($this->nominal_lembur, 0, ',', '.');
    }

    public function getFormattedNominalUangMakanAttribute()
    {
        return 'Rp ' . number_format($this->nominal_uang_makan, 0, ',', '.');
    }

    public function getFormattedNominalPhAttribute()
    {
        return 'Rp ' . number_format($this->nominal_ph, 0, ',', '.');
    }

    /**
     * Get all tickets for this division
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'divisi_id');
    }

    public function purchaseRequisitions()
    {
        return $this->hasMany(PurchaseRequisition::class, 'division_id');
    }
} 