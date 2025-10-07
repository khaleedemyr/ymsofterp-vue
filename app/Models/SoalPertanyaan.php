<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoalPertanyaan extends Model
{
    use HasFactory;

    protected $table = 'soal_pertanyaan';

    protected $fillable = [
        'master_soal_id',
        'urutan',
        'tipe_soal',
        'pertanyaan',
        'pertanyaan_gambar',
        'waktu_detik',
        'jawaban_benar',
        'pilihan_a',
        'pilihan_a_gambar',
        'pilihan_b',
        'pilihan_b_gambar',
        'pilihan_c',
        'pilihan_c_gambar',
        'pilihan_d',
        'pilihan_d_gambar',
        'skor',
        'status'
    ];

    protected $casts = [
        'waktu_detik' => 'integer',
        'skor' => 'decimal:2',
        'status' => 'string',
        'pertanyaan_gambar' => 'array'
    ];

    // Relationships
    public function masterSoal()
    {
        return $this->belongsTo(MasterSoal::class, 'master_soal_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByTipe($query, $tipe)
    {
        return $query->where('tipe_soal', $tipe);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan');
    }

    // Accessors
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
