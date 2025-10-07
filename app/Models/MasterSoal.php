<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterSoal extends Model
{
    use HasFactory;

    protected $table = 'master_soal';

    protected $fillable = [
        'judul',
        'deskripsi',
        'skor_total',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'skor_total' => 'decimal:2',
        'status' => 'string'
    ];

    // Relationships

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function pertanyaans()
    {
        return $this->hasMany(SoalPertanyaan::class, 'master_soal_id')->orderBy('urutan');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeByTipe($query, $tipe)
    {
        return $query->where('tipe_soal', $tipe);
    }


    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('judul', 'like', "%{$search}%")
              ->orWhere('pertanyaan', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return $this->status === 'active' ? 'success' : 'secondary';
    }

    public function getStatusTextAttribute()
    {
        return $this->status === 'active' ? 'Aktif' : 'Tidak Aktif';
    }

    public function getTipeSoalTextAttribute()
    {
        $types = [
            'essay' => 'Essay',
            'pilihan_ganda' => 'Pilihan Ganda',
            'yes_no' => 'Ya/Tidak'
        ];

        return $types[$this->tipe_soal] ?? 'Unknown';
    }

    public function getWaktuMenitAttribute()
    {
        return round($this->waktu_detik / 60, 1);
    }

    public function getFormattedSkorAttribute()
    {
        return number_format($this->skor, 2);
    }

    // Methods
    public function getPilihanArray()
    {
        if ($this->tipe_soal === 'pilihan_ganda') {
            return [
                'A' => $this->pilihan_a,
                'B' => $this->pilihan_b,
                'C' => $this->pilihan_c,
                'D' => $this->pilihan_d
            ];
        }

        if ($this->tipe_soal === 'yes_no') {
            return [
                'yes' => 'Ya',
                'no' => 'Tidak'
            ];
        }

        return [];
    }

    public function isJawabanBenar($jawaban)
    {
        if ($this->tipe_soal === 'essay') {
            return null; // Essay tidak bisa dicek otomatis
        }

        return strtolower($jawaban) === strtolower($this->jawaban_benar);
    }

    public function getJawabanBenarText()
    {
        if ($this->tipe_soal === 'essay') {
            return 'Essay - Manual Check';
        }

        if ($this->tipe_soal === 'yes_no') {
            return $this->jawaban_benar === 'yes' ? 'Ya' : 'Tidak';
        }

        if ($this->tipe_soal === 'pilihan_ganda') {
            $pilihan = $this->getPilihanArray();
            return $pilihan[$this->jawaban_benar] ?? $this->jawaban_benar;
        }

        return $this->jawaban_benar;
    }
}
