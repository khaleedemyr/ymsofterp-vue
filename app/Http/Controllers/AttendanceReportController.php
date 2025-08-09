<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Exports\AttendanceReportExport;

class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $search = $request->input('search');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        // Log filter values for debugging
        \Log::info('Attendance Report Filter Values', [
            'outlet_id' => $outletId,
            'division_id' => $divisionId,
            'search' => $search,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'has_filters' => !empty($outletId) || !empty($divisionId) || !empty($search) || !empty($bulan) || !empty($tahun)
        ]);

        $rows = collect();
        $summary = [ 'total_telat' => 0, 'total_lembur' => 0 ];
        if (!empty($outletId) || !empty($divisionId) || !empty($search) || !empty($bulan) || !empty($tahun)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

            // Query data absensi - Ambil semua scan dan proses manual
            $sub = DB::table('att_log as a')
                ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                ->join('user_pins as up', function($q) {
                    $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                })
                ->join('users as u', 'up.user_id', '=', 'u.id')
                ->select(
                    'a.scan_date',
                    'a.inoutmode',
                    'u.id as user_id',
                    'u.nama_lengkap',
                    'o.id_outlet',
                    'o.nama_outlet'
                )
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            if (!empty($outletId)) {
                $sub->where('o.id_outlet', $outletId);
            }
            if (!empty($divisionId)) {
                $sub->where('u.division_id', $divisionId);
            }
            if (!empty($search)) {
                $sub->where('u.nama_lengkap', 'like', "%$search%");
            }
            $rawData = $sub->orderBy('a.scan_date')->get();

            \Log::info('Raw data count: ' . $rawData->count());
            \Log::info('Raw data sample:', $rawData->take(5)->toArray());

            // Proses data manual untuk menangani cross-day dengan benar
            $processedData = [];
            
            // Step 1: Kelompokkan scan berdasarkan user, outlet, dan tanggal
            foreach ($rawData as $scan) {
                $date = date('Y-m-d', strtotime($scan->scan_date));
                $key = $scan->user_id . '_' . $scan->id_outlet . '_' . $date;
                
                if (!isset($processedData[$key])) {
                    $processedData[$key] = [
                        'tanggal' => $date,
                        'user_id' => $scan->user_id,
                        'nama_lengkap' => $scan->nama_lengkap,
                        'id_outlet' => $scan->id_outlet,
                        'nama_outlet' => $scan->nama_outlet,
                        'scans' => []
                    ];
                }
                
                $processedData[$key]['scans'][] = [
                    'scan_date' => $scan->scan_date,
                    'inoutmode' => $scan->inoutmode
                ];
            }
            
            // Step 2: Proses setiap kelompok untuk menentukan jam masuk/keluar
            $finalData = [];
            foreach ($processedData as $key => $data) {
                $scans = collect($data['scans'])->sortBy('scan_date');
                $inScans = $scans->where('inoutmode', 1);
                $outScans = $scans->where('inoutmode', 2);
                
                // Ambil scan masuk pertama
                $jamMasuk = $inScans->first()['scan_date'] ?? null;
                
                // Cari scan keluar yang sesuai
                $jamKeluar = null;
                $isCrossDay = false;
                $totalMasuk = $inScans->count();
                $totalKeluar = $outScans->count();
                
                if ($jamMasuk) {
                    // Cari scan keluar di hari yang sama
                    $sameDayOut = $outScans->where('scan_date', '>', $jamMasuk)->first();
                    
                    if ($sameDayOut) {
                        // Ada scan keluar di hari yang sama
                        $jamKeluar = $sameDayOut['scan_date'];
                        $isCrossDay = false;
                    } else {
                        // Cari scan keluar di hari berikutnya
                        $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                        $nextDayKey = $data['user_id'] . '_' . $data['id_outlet'] . '_' . $nextDay;
                        
                        if (isset($processedData[$nextDayKey])) {
                            $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                            $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                            
                            if ($nextDayOut) {
                                $jamKeluar = $nextDayOut['scan_date'];
                                $isCrossDay = true;
                                $totalKeluar = 1; // Cross-day scan keluar
                                
                                // Hapus scan keluar ini dari hari berikutnya
                                $processedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                            }
                        }
                    }
                }
                
                $finalData[] = [
                    'tanggal' => $data['tanggal'],
                    'user_id' => $data['user_id'],
                    'nama_lengkap' => $data['nama_lengkap'],
                    'id_outlet' => $data['id_outlet'],
                    'nama_outlet' => $data['nama_outlet'],
                    'jam_masuk' => $jamMasuk,
                    'jam_keluar' => $jamKeluar,
                    'total_masuk' => $totalMasuk,
                    'total_keluar' => $totalKeluar,
                    'is_cross_day' => $isCrossDay
                ];
            }
            
            $dataRows = collect($finalData);

            // Index dataRows by tanggal dan outlet
            $indexedData = [];
            foreach ($dataRows as $row) {
                $key = $row['tanggal'] . '_' . $row['id_outlet'];
                if (!isset($indexedData[$key])) {
                    $indexedData[$key] = [];
                }
                $indexedData[$key][] = $row;
            }

            // Ambil semua tanggal dalam periode
            $period = [];
            $dt = new \DateTime($start);
            $dtEnd = new \DateTime($end);
            while ($dt <= $dtEnd) {
                $period[] = $dt->format('Y-m-d');
                $dt->modify('+1 day');
            }

            // Ambil semua outlet
            $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

            // Generate data untuk setiap tanggal dan outlet
            $finalData = [];
            foreach ($period as $tanggal) {
                foreach ($outlets as $outlet) {
                    $key = $tanggal . '_' . $outlet->id_outlet;
                    if (isset($indexedData[$key])) {
                        foreach ($indexedData[$key] as $row) {
                            $finalData[] = (object) $row;
                        }
                    }
                }
            }

            $dataRows = collect($finalData);

            // Ambil nama karyawan dari hasil query (atau dari search)
            $namaKaryawan = $dataRows->first() ? $dataRows->first()->nama_lengkap : '';
            $userId = $dataRows->first() ? $dataRows->first()->user_id : null;

            // Ambil data shift untuk perhitungan lembur
            $shiftData = DB::table('shifts')
                ->first();

            // Hitung lembur untuk setiap baris
            foreach ($dataRows as $row) {
                if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                    // Ambil jam shift dari shift data
                    $shiftStart = $shiftData->time_start ?? '08:00:00'; // Default jika tidak ada
                    $shiftEnd = $shiftData->time_end ?? '17:00:00'; // Default jika tidak ada
                    
                    // Buat datetime untuk shift end
                    $shiftEndDateTime = date('Y-m-d', strtotime($row->jam_masuk)) . ' ' . $shiftEnd;
                    $scanOutDateTime = $row->jam_keluar;
                    
                    // Jika cross-day, shift end harus di hari berikutnya
                    if ($row->is_cross_day) {
                        $shiftEndDateTime = date('Y-m-d', strtotime($row->jam_masuk . ' +1 day')) . ' ' . $shiftEnd;
                    }
                    
                    // Hitung selisih waktu
                    $shiftEndTime = strtotime($shiftEndDateTime);
                    $scanOutTime = strtotime($scanOutDateTime);
                    
                    if ($scanOutTime > $shiftEndTime) {
                        $lemburHours = round(($scanOutTime - $shiftEndTime) / 3600, 2);
                        $row->lembur = $lemburHours;
                    } else {
                        $row->lembur = 0;
                    }
                } else {
                    $row->lembur = 0;
                }
                
                // Set is_off jika tidak ada scan
                $row->is_off = !$row->jam_masuk && !$row->jam_keluar;
            }

            // Ambil semua tanggal libur dalam periode
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->whereBetween('tgl_libur', [$start, $end])
                ->pluck('keterangan', 'tgl_libur'); // key: tgl_libur, value: keterangan

            // Build rows for each tanggal in period
            $rows = collect();
            foreach ($period as $tanggal) {
                // Ambil semua data untuk tanggal ini
                $dayData = $dataRows->where('tanggal', $tanggal);
                
                if ($dayData->count() > 0) {
                    // Ada data absensi untuk tanggal ini
                    foreach ($dayData as $row) {
                        $jam_masuk = $row->jam_masuk ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                        $jam_keluar = $row->jam_keluar ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                        $rowUserId = $row->user_id;
                        $rowNama = $row->nama_lengkap;
                        $telat = 0;
                        $lembur = 0;
                        $is_off = false;
                        $shift_name = null;
                        $is_holiday = false;
                        $holiday_name = null;
                        
                        if ($rowUserId && $rowNama) {
                            $shift = DB::table('user_shifts as us')
                                ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                                ->where('us.user_id', $rowUserId)
                                ->where('us.tanggal', $tanggal)
                                ->where('us.outlet_id', $row->id_outlet)
                                ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                                ->first();
                            if ($shift) {
                                $shift_name = $shift->shift_name;
                                if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                                    $is_off = true;
                                }
                            }
                            if (!$is_off) {
                                if ($shift && $shift->time_start && $jam_masuk) {
                                    $start = strtotime($shift->time_start);
                                    $masuk = strtotime($jam_masuk);
                                    $diff = $masuk - $start;
                                    $telat = $diff > 0 ? round($diff/60) : 0;
                                }
                                if ($shift && $shift->time_end && $jam_keluar) {
                                    // Perbaikan perhitungan lembur untuk cross-day
                                    // Buat datetime lengkap untuk shift end
                                    $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                                    
                                    // Gunakan scan keluar yang sudah dalam format datetime lengkap
                                    $scanOutDateTime = $row->jam_keluar;
                                    
                                    // Hitung selisih waktu
                                    $end = strtotime($shiftEndDateTime);
                                    $keluar = strtotime($scanOutDateTime);
                                    $diff = $keluar - $end;
                                    $lembur = $diff > 0 ? floor($diff/3600) : 0;
                                    
                                    // Log untuk debugging
                                    \Log::info('Overtime calculation', [
                                        'user' => $rowNama,
                                        'tanggal' => $tanggal,
                                        'shift_end' => $shiftEndDateTime,
                                        'scan_out' => $scanOutDateTime,
                                        'diff_seconds' => $diff,
                                        'lembur_hours' => $lembur,
                                        'is_cross_day' => $row->is_cross_day ?? false
                                    ]);
                                }
                            } else {
                                $jam_masuk = null;
                                $jam_keluar = null;
                                $telat = 0;
                                $lembur = 0;
                            }
                        }
                        // Cek hari libur
                        if ($holidays->has($tanggal)) {
                            $is_holiday = true;
                            $holiday_name = $holidays[$tanggal];
                        }
                        $summary['total_telat'] += $telat;
                        $summary['total_lembur'] += $lembur;
                        $rows->push((object)[
                            'tanggal' => $tanggal,
                            'user_id' => $rowUserId,
                            'nama_lengkap' => $rowNama,
                            'outlet_id' => $row->id_outlet,
                            'nama_outlet' => $row->nama_outlet,
                            'jam_masuk' => $jam_masuk,
                            'jam_keluar' => $jam_keluar,
                            'total_masuk' => $row->total_masuk,
                            'total_keluar' => $row->total_keluar,
                            'telat' => $telat,
                            'lembur' => $lembur,
                            'is_off' => $is_off,
                            'shift_name' => $shift_name,
                            'is_holiday' => $is_holiday,
                            'holiday_name' => $holiday_name,
                            'is_cross_day' => $row->is_cross_day ?? false,
                        ]);
                    }
                } else {
                    // Tidak ada data absensi untuk tanggal ini
                    $rowUserId = $userId;
                    $rowNama = $namaKaryawan;
                    $telat = 0;
                    $lembur = 0;
                    $is_off = false;
                    $shift_name = null;
                    $is_holiday = false;
                    $holiday_name = null;
                    
                    if ($rowUserId && $rowNama) {
                        $shift = DB::table('user_shifts as us')
                            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                            ->where('us.user_id', $rowUserId)
                            ->where('us.tanggal', $tanggal)
                            ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                            ->first();
                        if ($shift) {
                            $shift_name = $shift->shift_name;
                            if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                                $is_off = true;
                            }
                        }
                    }
                    // Cek hari libur
                    if ($holidays->has($tanggal)) {
                        $is_holiday = true;
                        $holiday_name = $holidays[$tanggal];
                    }
                    $rows->push((object)[
                        'tanggal' => $tanggal,
                        'user_id' => $rowUserId,
                        'nama_lengkap' => $rowNama,
                        'outlet_id' => null,
                        'nama_outlet' => null,
                        'jam_masuk' => null,
                        'jam_keluar' => null,
                        'total_masuk' => 0,
                        'total_keluar' => 0,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_off' => $is_off,
                        'shift_name' => $shift_name,
                        'is_holiday' => $is_holiday,
                        'holiday_name' => $holiday_name,
                        'is_cross_day' => false,
                    ]);
                }
            }
        } else {
            \Log::info('No filters provided, returning empty data');
        }

        // Dropdown filter
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();

        return Inertia::render('AttendanceReport/Index', [
            'data' => $rows,
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'search' => $search,
                'bulan' => $bulan,
                'tahun' => $tahun,
            ],
            'summary' => $summary,
        ]);
    }

    // Endpoint detail absensi per user per tanggal
    public function detail(Request $request)
    {
        $userId = $request->input('user_id');
        $tanggal = $request->input('tanggal');
        if (!$userId || !$tanggal) return response()->json([]);
        
        // Ambil data absensi untuk tanggal yang diminta dan hari berikutnya (untuk cross-day)
        $nextDay = date('Y-m-d', strtotime($tanggal . ' +1 day'));
        
        $rows = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->whereIn(DB::raw('DATE(a.scan_date)'), [$tanggal, $nextDay])
            ->select(
                'o.id_outlet', 
                'o.nama_outlet',
                'a.scan_date',
                'a.inoutmode'
            )
            ->orderBy('a.scan_date')
            ->get();
            
        // Proses data untuk menangani cross-day
        $processedData = [];
        foreach ($rows as $row) {
            $date = date('Y-m-d', strtotime($row->scan_date));
            $key = $row->id_outlet . '_' . $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'id_outlet' => $row->id_outlet,
                    'nama_outlet' => $row->nama_outlet,
                    'tanggal' => $date,
                    'scans' => []
                ];
            }
            
            $processedData[$key]['scans'][] = [
                'scan_date' => $row->scan_date,
                'inoutmode' => $row->inoutmode
            ];
        }
        
        // Proses setiap outlet untuk tanggal yang diminta
        $result = [];
        foreach ($processedData as $key => $data) {
            if ($data['tanggal'] == $tanggal) {
                $scans = collect($data['scans'])->sortBy('scan_date');
                $inScans = $scans->where('inoutmode', 1);
                $outScans = $scans->where('inoutmode', 2);
                
                // Ambil scan masuk pertama
                $jamIn = $inScans->first()['scan_date'] ?? null;
                $jamOut = null;
                $totalIn = $inScans->count();
                $totalOut = $outScans->count();
                
                if ($jamIn) {
                    // Cari scan keluar di hari yang sama
                    $sameDayOut = $outScans->where('scan_date', '>', $jamIn)->first();
                    
                    if ($sameDayOut) {
                        $jamOut = $sameDayOut['scan_date'];
                    } else {
                        // Cari scan keluar di hari berikutnya
                        $nextDayKey = $data['id_outlet'] . '_' . $nextDay;
                        if (isset($processedData[$nextDayKey])) {
                            $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                            $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                            
                            if ($nextDayOut) {
                                $jamOut = $nextDayOut['scan_date'];
                                $totalOut = 1; // Cross-day scan keluar
                            }
                        }
                    }
                }
                
                $result[] = [
                    'nama_outlet' => $data['nama_outlet'],
                    'jam_in' => $jamIn ? date('H:i:s', strtotime($jamIn)) : null,
                    'jam_out' => $jamOut ? date('H:i:s', strtotime($jamOut)) : null,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                ];
            }
        }
        
        \Log::info('Attendance detail result', ['result' => $result]);
        return response()->json($result);
    }

    // Endpoint untuk info shift karyawan per tanggal
    public function shiftInfo(Request $request)
    {
        $userId = $request->input('user_id');
        $tanggal = $request->input('tanggal');
        if (!$userId || !$tanggal) return response()->json([]);
        $shift = \DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.tanggal', $tanggal)
            ->select('s.shift_name', 's.time_start', 's.time_end')
            ->first();
        if (!$shift) return response()->json([]);
        return response()->json($shift);
    }

    // API endpoint untuk dropdown karyawan berdasarkan filter outlet dan divisi
    public function getEmployees(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $search = $request->input('search');

        $query = DB::table('users as u')
            ->select('u.id', 'u.nama_lengkap as name')
            ->where('u.status', 'A'); // Hanya karyawan aktif

        // Filter berdasarkan outlet
        if (!empty($outletId)) {
            $query->where('u.id_outlet', $outletId);
        }

        // Filter berdasarkan divisi
        if (!empty($divisionId)) {
            $query->where('u.division_id', $divisionId);
        }

        // Filter berdasarkan search
        if (!empty($search)) {
            $query->where('u.nama_lengkap', 'like', "%$search%");
        }

        $employees = $query->orderBy('u.nama_lengkap')->get();

        return response()->json($employees);
    }

    public function exportExcel(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $search = $request->input('search');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        $rows = collect();
        if (!empty($outletId) || !empty($divisionId) || !empty($search) || !empty($bulan) || !empty($tahun)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

            $period = [];
            $dt = new \DateTime($start);
            $dtEnd = new \DateTime($end);
            while ($dt <= $dtEnd) {
                $period[] = $dt->format('Y-m-d');
                $dt->modify('+1 day');
            }

            $sub = DB::table('att_log as a')
                ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                ->join('user_pins as up', function($q) {
                    $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                })
                ->join('users as u', 'up.user_id', '=', 'u.id')
                ->select(
                    'a.scan_date',
                    'a.inoutmode',
                    'u.id as user_id',
                    'u.nama_lengkap',
                    'o.id_outlet',
                    'o.nama_outlet'
                )
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            if (!empty($outletId)) {
                $sub->where('o.id_outlet', $outletId);
            }
            if (!empty($divisionId)) {
                $sub->where('u.division_id', $divisionId);
            }
            if (!empty($search)) {
                $sub->where('u.nama_lengkap', 'like', "%$search%");
            }
            $rawData = $sub->orderBy('a.scan_date')->get();

            // Proses data manual untuk menangani cross-day dengan benar
            $processedData = [];
            
            // Step 1: Kelompokkan scan berdasarkan user, outlet, dan tanggal
            foreach ($rawData as $scan) {
                $date = date('Y-m-d', strtotime($scan->scan_date));
                $key = $scan->user_id . '_' . $scan->id_outlet . '_' . $date;
                
                if (!isset($processedData[$key])) {
                    $processedData[$key] = [
                        'tanggal' => $date,
                        'user_id' => $scan->user_id,
                        'nama_lengkap' => $scan->nama_lengkap,
                        'id_outlet' => $scan->id_outlet,
                        'nama_outlet' => $scan->nama_outlet,
                        'scans' => []
                    ];
                }
                
                $processedData[$key]['scans'][] = [
                    'scan_date' => $scan->scan_date,
                    'inoutmode' => $scan->inoutmode
                ];
            }
            
            // Step 2: Proses setiap kelompok untuk menentukan jam masuk/keluar
            $finalData = [];
            foreach ($processedData as $key => $data) {
                $scans = collect($data['scans'])->sortBy('scan_date');
                $inScans = $scans->where('inoutmode', 1);
                $outScans = $scans->where('inoutmode', 2);
                
                // Ambil scan masuk pertama
                $jamMasuk = $inScans->first()['scan_date'] ?? null;
                
                // Cari scan keluar yang sesuai
                $jamKeluar = null;
                $isCrossDay = false;
                
                if ($jamMasuk) {
                    // Cari scan keluar di hari yang sama
                    $sameDayOut = $outScans->where('scan_date', '>', $jamMasuk)->first();
                    
                    if ($sameDayOut) {
                        // Ada scan keluar di hari yang sama
                        $jamKeluar = $sameDayOut['scan_date'];
                        $isCrossDay = false;
                    } else {
                        // Cari scan keluar di hari berikutnya
                        $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                        $nextDayKey = $data['user_id'] . '_' . $data['id_outlet'] . '_' . $nextDay;
                        
                        if (isset($processedData[$nextDayKey])) {
                            $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                            $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                            
                            if ($nextDayOut) {
                                $jamKeluar = $nextDayOut['scan_date'];
                                $isCrossDay = true;
                                
                                // Hapus scan keluar ini dari hari berikutnya
                                $processedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                            }
                        }
                    }
                }
                
                // Hitung total scan
                $totalMasuk = $inScans->count();
                $totalKeluar = $outScans->count();
                
                $finalData[] = [
                    'tanggal' => $data['tanggal'],
                    'user_id' => $data['user_id'],
                    'nama_lengkap' => $data['nama_lengkap'],
                    'id_outlet' => $data['id_outlet'],
                    'nama_outlet' => $data['nama_outlet'],
                    'jam_masuk' => $jamMasuk,
                    'jam_keluar' => $jamKeluar,
                    'total_masuk' => $totalMasuk,
                    'total_keluar' => $totalKeluar,
                    'is_cross_day' => $isCrossDay
                ];
            }
            
            $dataRows = collect($finalData);

            $dataByTanggalOutlet = [];
            foreach ($dataRows as $row) {
                $key = $row['tanggal'] . '_' . $row['id_outlet'];
                $dataByTanggalOutlet[$key] = (object) $row;
            }

            // Ambil semua tanggal dalam periode
            $period = [];
            $dt = new \DateTime($start);
            $dtEnd = new \DateTime($end);
            while ($dt <= $dtEnd) {
                $period[] = $dt->format('Y-m-d');
                $dt->modify('+1 day');
            }

            // Ambil semua outlet
            $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

            // Generate data untuk setiap tanggal dan outlet
            $finalData = [];
            foreach ($period as $tanggal) {
                foreach ($outlets as $outlet) {
                    $key = $tanggal . '_' . $outlet->id_outlet;
                    if (isset($dataByTanggalOutlet[$key])) {
                        $finalData[] = $dataByTanggalOutlet[$key];
                    }
                }
            }

            $dataRows = collect($finalData);

            $namaKaryawan = null;
            $userId = null;
            if (count($dataRows) > 0) {
                $namaKaryawan = $dataRows[0]->nama_lengkap;
                $userId = $dataRows[0]->user_id;
            } elseif (!empty($search)) {
                // Cari user_id dari nama
                $user = DB::table('users')->where('nama_lengkap', $search)->first();
                if ($user) {
                    $namaKaryawan = $user->nama_lengkap;
                    $userId = $user->id;
                }
            }

            // Ambil data shift untuk perhitungan lembur
            $shiftData = DB::table('shifts')
                ->first();

            // Hitung lembur untuk setiap baris
            foreach ($dataRows as $row) {
                if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                    // Ambil jam shift dari shift data - gunakan kolom yang benar
                    $shiftStart = $shiftData->time_start ?? '08:00:00'; // Default jika tidak ada
                    $shiftEnd = $shiftData->time_end ?? '17:00:00'; // Default jika tidak ada
                    
                    // Buat datetime untuk shift end
                    $shiftEndDateTime = date('Y-m-d', strtotime($row->jam_masuk)) . ' ' . $shiftEnd;
                    $scanOutDateTime = $row->jam_keluar;
                    
                    // Jika cross-day, shift end harus di hari berikutnya
                    if ($row->is_cross_day) {
                        $shiftEndDateTime = date('Y-m-d', strtotime($row->jam_masuk . ' +1 day')) . ' ' . $shiftEnd;
                    }
                    
                    // Hitung selisih waktu
                    $shiftEndTime = strtotime($shiftEndDateTime);
                    $scanOutTime = strtotime($scanOutDateTime);
                    
                    if ($scanOutTime > $shiftEndTime) {
                        $lemburHours = round(($scanOutTime - $shiftEndTime) / 3600, 2);
                        $row->lembur = $lemburHours;
                    } else {
                        $row->lembur = 0;
                    }
                } else {
                    $row->lembur = 0;
                }
                
                // Set is_off jika tidak ada scan
                $row->is_off = !$row->jam_masuk && !$row->jam_keluar;
            }

            // Ambil semua tanggal libur dalam periode
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->whereBetween('tgl_libur', [$start, $end])
                ->pluck('keterangan', 'tgl_libur');

            foreach ($period as $tanggal) {
                // Ambil semua data untuk tanggal ini
                $dayData = $dataRows->where('tanggal', $tanggal);
                
                if ($dayData->count() > 0) {
                    // Ada data absensi untuk tanggal ini
                    foreach ($dayData as $row) {
                        $jam_masuk = $row->jam_masuk ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                        $jam_keluar = $row->jam_keluar ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                        $rowUserId = $row->user_id;
                        $rowNama = $row->nama_lengkap;
                        $telat = 0;
                        $lembur = 0;
                        $is_off = false;
                        $shift_name = null;
                        $shift_time_start = null;
                        $shift_time_end = null;
                        $is_holiday = false;
                        $holiday_name = null;
                        $detail = '';
                        
                        if ($rowUserId && $rowNama) {
                            // Ambil shift
                            $shift = DB::table('user_shifts as us')
                                ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                                ->where('us.user_id', $rowUserId)
                                ->where('us.tanggal', $tanggal)
                                ->where('us.outlet_id', $row->id_outlet)
                                ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                                ->first();
                            if ($shift) {
                                $shift_name = $shift->shift_name;
                                $shift_time_start = $shift->time_start;
                                $shift_time_end = $shift->time_end;
                                if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                                    $is_off = true;
                                }
                            }
                            if (!$is_off) {
                                if ($shift && $shift->time_start && $jam_masuk) {
                                    $start = strtotime($shift->time_start);
                                    $masuk = strtotime($jam_masuk);
                                    $diff = $masuk - $start;
                                    $telat = $diff > 0 ? round($diff/60) : 0;
                                }
                                if ($shift && $shift->time_end && $jam_keluar) {
                                    // Perbaikan perhitungan lembur untuk cross-day
                                    // Buat datetime lengkap untuk shift end
                                    $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                                    
                                    // Gunakan scan keluar yang sudah dalam format datetime lengkap
                                    $scanOutDateTime = $row->jam_keluar;
                                    
                                    // Hitung selisih waktu
                                    $end = strtotime($shiftEndDateTime);
                                    $keluar = strtotime($scanOutDateTime);
                                    $diff = $keluar - $end;
                                    $lembur = $diff > 0 ? floor($diff/3600) : 0;
                                    
                                    // Log untuk debugging
                                    \Log::info('Overtime calculation', [
                                        'user' => $rowNama,
                                        'tanggal' => $tanggal,
                                        'shift_end' => $shiftEndDateTime,
                                        'scan_out' => $scanOutDateTime,
                                        'diff_seconds' => $diff,
                                        'lembur_hours' => $lembur,
                                        'is_cross_day' => $row->is_cross_day ?? false
                                    ]);
                                }
                            } else {
                                $jam_masuk = null;
                                $jam_keluar = null;
                                $telat = 0;
                                $lembur = 0;
                            }
                            // Detail sudah ada dari query utama
                            $detail = $row->nama_outlet . ': ' . ($jam_masuk ?? '-') . ' - ' . ($jam_keluar ?? '-') . 
                                     ' (IN: ' . $row->total_masuk . ', OUT: ' . $row->total_keluar . ')';
                        }
                        if ($holidays->has($tanggal)) {
                            $is_holiday = true;
                            $holiday_name = $holidays[$tanggal];
                        }
                        $rows->push((object)[
                            'tanggal' => $tanggal,
                            'user_id' => $rowUserId,
                            'nama_lengkap' => $rowNama,
                            'outlet_id' => $row->id_outlet,
                            'nama_outlet' => $row->nama_outlet,
                            'jam_masuk' => $jam_masuk,
                            'jam_keluar' => $jam_keluar,
                            'total_masuk' => $row->total_masuk,
                            'total_keluar' => $row->total_keluar,
                            'telat' => $telat,
                            'lembur' => $lembur,
                            'is_off' => $is_off,
                            'shift_name' => $shift_name,
                            'shift_time_start' => $shift_time_start,
                            'shift_time_end' => $shift_time_end,
                            'is_holiday' => $is_holiday,
                            'holiday_name' => $holiday_name,
                            'detail' => $detail,
                            'is_cross_day' => $row->is_cross_day ?? false,
                        ]);
                    }
                } else {
                    // Tidak ada data absensi untuk tanggal ini
                    $rowUserId = $userId ?? null;
                    $rowNama = $namaKaryawan ?? '';
                    $telat = 0;
                    $lembur = 0;
                    $is_off = false;
                    $shift_name = null;
                    $is_holiday = false;
                    $holiday_name = null;
                    $detail = '';
                    
                    if ($rowUserId && $rowNama) {
                        // Ambil shift
                        $shift = DB::table('user_shifts as us')
                            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                            ->where('us.user_id', $rowUserId)
                            ->where('us.tanggal', $tanggal)
                            ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                            ->first();
                        if ($shift) {
                            $shift_name = $shift->shift_name;
                            $shift_time_start = $shift->time_start;
                            $shift_time_end = $shift->time_end;
                            if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                                $is_off = true;
                            }
                        }
                    }
                    if ($holidays->has($tanggal)) {
                        $is_holiday = true;
                        $holiday_name = $holidays[$tanggal];
                    }
                    $rows->push((object)[
                        'tanggal' => $tanggal,
                        'user_id' => $rowUserId,
                        'nama_lengkap' => $rowNama,
                        'outlet_id' => null,
                        'nama_outlet' => null,
                        'jam_masuk' => null,
                        'jam_keluar' => null,
                        'total_masuk' => 0,
                        'total_keluar' => 0,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_off' => $is_off,
                        'shift_name' => $shift_name,
                        'shift_time_start' => $shift_time_start,
                        'shift_time_end' => $shift_time_end,
                        'is_holiday' => $is_holiday,
                        'holiday_name' => $holiday_name,
                        'detail' => $detail,
                        'is_cross_day' => false,
                    ]);
                }
            }
        }
        $fileName = 'attendance_';
        $fileName .= $namaKaryawan ? str_replace(' ', '_', $namaKaryawan) : 'all';
        $fileName .= '_' . $start . '_sampai_' . $end . '.xlsx';
        $export = new AttendanceReportExport($rows);
        $export->fileName = $fileName;
        return $export;
    }

    // Ringkasan telat dan lembur per outlet dalam periode
    public function outletSummary(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $bulan = $request->input('bulan') ?: date('m');
        $tahun = $request->input('tahun') ?: date('Y');

        $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
        $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

        // Ambil raw scan data
        $sub = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->select(
                'a.scan_date',
                'a.inoutmode',
                'u.id as user_id',
                'u.nama_lengkap',
                'o.id_outlet',
                'o.nama_outlet'
            )
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
        if (!empty($outletId)) {
            $sub->where('o.id_outlet', $outletId);
        }
        if (!empty($divisionId)) {
            $sub->where('u.division_id', $divisionId);
        }
        $rawData = $sub->orderBy('a.scan_date')->get();

        // Proses data pairing IN/OUT dan cross-day seperti di index()
        $processedData = [];
        foreach ($rawData as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $scan->user_id . '_' . $scan->id_outlet . '_' . $date;
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'user_id' => $scan->user_id,
                    'nama_lengkap' => $scan->nama_lengkap,
                    'id_outlet' => $scan->id_outlet,
                    'nama_outlet' => $scan->nama_outlet,
                    'scans' => []
                ];
            }
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }

        // Step 2: tentukan jam_masuk/jam_keluar
        $finalData = [];
        foreach ($processedData as $key => $data) {
            $scans = collect($data['scans'])->sortBy('scan_date');
            $inScans = $scans->where('inoutmode', 1);
            $outScans = $scans->where('inoutmode', 2);
            $jamMasuk = $inScans->first()['scan_date'] ?? null;
            $jamKeluar = null;
            $isCrossDay = false;
            if ($jamMasuk) {
                $sameDayOut = $outScans->where('scan_date', '>', $jamMasuk)->first();
                if ($sameDayOut) {
                    $jamKeluar = $sameDayOut['scan_date'];
                    $isCrossDay = false;
                } else {
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $data['user_id'] . '_' . $data['id_outlet'] . '_' . $nextDay;
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                        if ($nextDayOut) {
                            $jamKeluar = $nextDayOut['scan_date'];
                            $isCrossDay = true;
                            // Hapus OUT di hari berikutnya seperti index()
                            $processedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                        }
                    }
                }
            }
            $finalData[] = [
                'tanggal' => $data['tanggal'],
                'user_id' => $data['user_id'],
                'nama_lengkap' => $data['nama_lengkap'],
                'id_outlet' => $data['id_outlet'],
                'nama_outlet' => $data['nama_outlet'],
                'jam_masuk' => $jamMasuk,
                'jam_keluar' => $jamKeluar,
                'total_masuk' => $inScans->count(),
                'total_keluar' => $outScans->count(),
                'is_cross_day' => $isCrossDay,
            ];
        }

        $dataRows = collect($finalData);

        // Index data by tanggal & outlet sama seperti index()
        $indexedData = [];
        foreach ($dataRows as $row) {
            $key = $row['tanggal'] . '_' . $row['id_outlet'];
            if (!isset($indexedData[$key])) $indexedData[$key] = [];
            $indexedData[$key][] = $row;
        }

        // Build period tanggal
        $period = [];
        $dt = new \DateTime($start);
        $dtEnd = new \DateTime($end);
        while ($dt <= $dtEnd) { $period[] = $dt->format('Y-m-d'); $dt->modify('+1 day'); }

        // Ambil seluruh outlet
        $allOutlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

        // Flatten sesuai tanggal x outlet
        $flatten = [];
        foreach ($period as $tgl) {
            foreach ($allOutlets as $o) {
                $key = $tgl . '_' . $o->id_outlet;
                if (isset($indexedData[$key])) {
                    foreach ($indexedData[$key] as $row) { $flatten[] = (object)$row; }
                }
            }
        }
        $dataRows = collect($flatten);

        // Ambil libur
        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$start, $end])
            ->pluck('keterangan', 'tgl_libur');

        // Hitung telat/lembur persis seperti index()
        $rows = collect();
        foreach ($period as $tanggal) {
            $dayData = $dataRows->where('tanggal', $tanggal);
            if ($dayData->count() > 0) {
                foreach ($dayData as $row) {
                    $jam_masuk = $row->jam_masuk ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                    $jam_keluar = $row->jam_keluar ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                    $telat = 0; $lembur = 0; $is_off = false; $shift_name = null;
                    $shift = DB::table('user_shifts as us')
                        ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                        ->where('us.user_id', $row->user_id)
                        ->where('us.tanggal', $tanggal)
                        ->where('us.outlet_id', $row->id_outlet)
                        ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                        ->first();
                    if ($shift) {
                        $shift_name = $shift->shift_name;
                        if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) { $is_off = true; }
                    }
                    if (!$is_off) {
                        if ($shift && $shift->time_start && $jam_masuk) {
                            $startTs = strtotime($shift->time_start);
                            $masukTs = strtotime($jam_masuk);
                            $diff = $masukTs - $startTs; $telat = $diff > 0 ? round($diff/60) : 0;
                        }
                        if ($shift && $shift->time_end && $jam_keluar) {
                            $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                            $scanOutDateTime = $row->jam_keluar;
                            $endTs = strtotime($shiftEndDateTime); $outTs = strtotime($scanOutDateTime);
                            $diff = $outTs - $endTs; $lembur = $diff > 0 ? floor($diff/3600) : 0;
                        }
                    } else { $jam_masuk = null; $jam_keluar = null; $telat = 0; $lembur = 0; }

                    $rows->push((object) [
                        'tanggal' => $tanggal,
                        'outlet_id' => $row->id_outlet,
                        'nama_outlet' => $row->nama_outlet,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_off' => $is_off,
                        'is_holiday' => $holidays->has($tanggal),
                    ]);
                }
            }
        }

        $byOutlet = $rows->groupBy('outlet_id')->map(function($g) {
            $first = $g->first();
            return [
                'outlet_id' => $first->outlet_id ?? null,
                'nama_outlet' => $first->nama_outlet ?? '-',
                'total_telat' => $g->where('is_off', false)->sum('telat'),
                'total_lembur' => $g->where('is_off', false)->sum('lembur'),
            ];
        })->values()->sortBy('nama_outlet')->values();

        // Dropdowns
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();

        return Inertia::render('AttendanceReport/OutletSummary', [
            'rows' => $byOutlet,
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'bulan' => $bulan,
                'tahun' => $tahun,
            ],
            'period' => [ 'start' => $start, 'end' => $end ],
        ]);
    }
} 