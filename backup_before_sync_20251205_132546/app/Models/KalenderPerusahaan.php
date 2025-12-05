<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class KalenderPerusahaan extends Model
{
    protected $table = 'tbl_kalender_perusahaan';
    
    protected $fillable = [
        'tgl_libur',
        'keterangan'
    ];
    
    protected $casts = [
        'tgl_libur' => 'date'
    ];
    
    // Scope untuk mendapatkan libur berdasarkan tahun dan bulan
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('tgl_libur', $year)
                    ->whereMonth('tgl_libur', $month);
    }
    
    // Scope untuk mendapatkan libur yang akan datang
    public function scopeUpcoming($query)
    {
        return $query->where('tgl_libur', '>=', Carbon::today());
    }
    
    // Method untuk mengecek apakah tanggal adalah hari libur
    public static function isHoliday($date)
    {
        return self::where('tgl_libur', $date)->exists();
    }
    
    // Method untuk mendapatkan keterangan libur
    public static function getHolidayDescription($date)
    {
        $holiday = self::where('tgl_libur', $date)->first();
        return $holiday ? $holiday->keterangan : null;
    }
}
