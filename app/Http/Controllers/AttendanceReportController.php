<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Exports\AttendanceReportExport;
use App\Exports\EmployeeSummaryExport;

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

            // Get approved absent requests for the date range
            $approvedAbsents = $this->getApprovedAbsentRequests($start, $end);

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
                    'u.nama_lengkap'
                )
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            // Filter outlet hanya untuk dropdown karyawan, bukan untuk report
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
            
            // Step 1: Kelompokkan scan berdasarkan user dan tanggal
            foreach ($rawData as $scan) {
                $date = date('Y-m-d', strtotime($scan->scan_date));
                $key = $scan->user_id . '_' . $date;
                
                if (!isset($processedData[$key])) {
                    $processedData[$key] = [
                        'tanggal' => $date,
                        'user_id' => $scan->user_id,
                        'nama_lengkap' => $scan->nama_lengkap,
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
                    // Cari scan keluar TERAKHIR di hari yang sama
                    $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                    if ($sameDayOuts->isNotEmpty()) {
                        $jamKeluar = $sameDayOuts->last()['scan_date'];
                        $isCrossDay = false;
                    } else {
                        // Cari scan keluar TERAKHIR di hari berikutnya
                        $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                        $nextDayKey = $data['user_id'] . '_' . $nextDay;
                        
                        if (isset($processedData[$nextDayKey])) {
                            $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                            $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                            
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamKeluar = $nextDayOuts->last()['scan_date'];
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
                $key = $row['tanggal'];
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

            // Generate data untuk setiap tanggal
            $finalData = [];
            foreach ($period as $tanggal) {
                if (isset($indexedData[$tanggal])) {
                    foreach ($indexedData[$tanggal] as $row) {
                        $finalData[] = (object) $row;
                    }
                }
            }

            $dataRows = collect($finalData);

            // Ambil nama karyawan dari hasil query (atau dari search)
            $namaKaryawan = $dataRows->first() ? $dataRows->first()->nama_lengkap : '';
            $userId = $dataRows->first() ? $dataRows->first()->user_id : null;
            
            // Jika tidak ada data dari attendance records, coba ambil dari search atau approved absents
            if (!$userId || !$namaKaryawan) {
                if (!empty($search)) {
                    // Ambil user dari search
                    $user = DB::table('users')
                        ->where('nama_lengkap', 'like', "%$search%")
                        ->when(!empty($outletId), function($query) use ($outletId) {
                            return $query->where('id_outlet', $outletId);
                        })
                        ->when(!empty($divisionId), function($query) use ($divisionId) {
                            return $query->where('division_id', $divisionId);
                        })
                        ->select('id', 'nama_lengkap')
                        ->first();
                        
                    if ($user) {
                        $userId = $user->id;
                        $namaKaryawan = $user->nama_lengkap;
                    }
                } else {
                    // Ambil user dari approved absents
                    $approvedAbsentUser = DB::table('absent_requests')
                        ->join('users', 'absent_requests.user_id', '=', 'users.id')
                        ->where('absent_requests.status', 'approved')
                        ->where(function($query) use ($start, $end) {
                            $query->whereBetween('absent_requests.date_from', [$start, $end])
                                  ->orWhereBetween('absent_requests.date_to', [$start, $end])
                                  ->orWhere(function($q) use ($start, $end) {
                                      $q->where('absent_requests.date_from', '<=', $start)
                                        ->where('absent_requests.date_to', '>=', $end);
                                  });
                        })
                        ->when(!empty($outletId), function($query) use ($outletId) {
                            return $query->where('users.id_outlet', $outletId);
                        })
                        ->when(!empty($divisionId), function($query) use ($divisionId) {
                            return $query->where('users.division_id', $divisionId);
                        })
                        ->select('users.id', 'users.nama_lengkap')
                        ->first();
                        
                    if ($approvedAbsentUser) {
                        $userId = $approvedAbsentUser->id;
                        $namaKaryawan = $approvedAbsentUser->nama_lengkap;
                    }
                }
            }

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
                        
                        // Check if user has approved absent for this date
                        $approvedAbsent = null;
                        $is_approved_absent = false;
                        $approved_absent_name = null;
                        if (isset($approvedAbsents[$rowUserId][$tanggal])) {
                            $approvedAbsent = $approvedAbsents[$rowUserId][$tanggal];
                            $is_approved_absent = true;
                            $approved_absent_name = $approvedAbsent['leave_type_name'];
                        }
                        
                        $rows->push((object)[
                            'tanggal' => $tanggal,
                            'user_id' => $rowUserId,
                            'nama_lengkap' => $rowNama,
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
                            'approved_absent' => $approvedAbsent,
                            'is_approved_absent' => $is_approved_absent,
                            'approved_absent_name' => $approved_absent_name,
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
                    
                    // Check if user has approved absent for this date
                    $approvedAbsent = null;
                    $is_approved_absent = false;
                    $approved_absent_name = null;
                    if (isset($approvedAbsents[$rowUserId][$tanggal])) {
                        $approvedAbsent = $approvedAbsents[$rowUserId][$tanggal];
                        $is_approved_absent = true;
                        $approved_absent_name = $approvedAbsent['leave_type_name'];
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
                        'approved_absent' => $approvedAbsent,
                        'is_approved_absent' => $is_approved_absent,
                        'approved_absent_name' => $approved_absent_name,
                    ]);
                }
            }
            
            // Note: Approved absent data is already handled in the main loop above
            // The else block (lines 348-404) already adds rows for dates without attendance data
            // and includes approved_absent information, so no need to add it again here
        } else {
            \Log::info('No filters provided, returning empty data');
        }

        // Sort rows by tanggal and user_id to ensure proper chronological order
        $rows = $rows->sortBy([
            ['tanggal', 'asc'],
            ['user_id', 'asc']
        ])->values();

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
            
        // Proses data untuk menangani cross-day - kelompokkan berdasarkan outlet
        $processedData = [];
        foreach ($rows as $row) {
            $date = date('Y-m-d', strtotime($row->scan_date));
            $key = $row->id_outlet . '_' . $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'id_outlet' => $row->id_outlet,
                    'nama_outlet' => $row->nama_outlet,
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
                    // Cari scan keluar TERAKHIR di hari yang sama
                    $sameDayOuts = $outScans->where('scan_date', '>', $jamIn);
                    if ($sameDayOuts->isNotEmpty()) {
                        $jamOut = $sameDayOuts->last()['scan_date'];
                    } else {
                        // Cari scan keluar TERAKHIR di hari berikutnya
                        $nextDayKey = $data['id_outlet'] . '_' . $nextDay;
                        if (isset($processedData[$nextDayKey])) {
                            $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                            $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                            
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamOut = $nextDayOuts->last()['scan_date'];
                                $totalOut = 1; // Cross-day scan keluar
                            }
                        }
                    }
                }
                
                $result[] = [
                    'id_outlet' => $data['id_outlet'],
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
            
            // Step 1: Kelompokkan scan berdasarkan user dan tanggal
            foreach ($rawData as $scan) {
                $date = date('Y-m-d', strtotime($scan->scan_date));
                $key = $scan->user_id . '_' . $date;
                
                if (!isset($processedData[$key])) {
                    $processedData[$key] = [
                        'tanggal' => $date,
                        'user_id' => $scan->user_id,
                        'nama_lengkap' => $scan->nama_lengkap,
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
                    // Cari scan keluar TERAKHIR di hari yang sama
                    $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                    if ($sameDayOuts->isNotEmpty()) {
                        $jamKeluar = $sameDayOuts->last()['scan_date'];
                        $isCrossDay = false;
                    } else {
                        // Cari scan keluar TERAKHIR di hari berikutnya
                        $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                        $nextDayKey = $data['user_id'] . '_' . $nextDay;
                        
                        if (isset($processedData[$nextDayKey])) {
                            $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                            $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                            
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamKeluar = $nextDayOuts->last()['scan_date'];
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
                            $detail = ($jam_masuk ?? '-') . ' - ' . ($jam_keluar ?? '-') . 
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
                'u.nama_lengkap'
            )
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
        // Filter outlet hanya untuk dropdown karyawan, bukan untuk report
        if (!empty($divisionId)) {
            $sub->where('u.division_id', $divisionId);
        }
        $rawData = $sub->orderBy('a.scan_date')->get();

        // Proses data pairing IN/OUT dan cross-day seperti di index()
        $processedData = [];
        foreach ($rawData as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $scan->user_id . '_' . $date;
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'user_id' => $scan->user_id,
                    'nama_lengkap' => $scan->nama_lengkap,
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
                $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                    if ($sameDayOuts->isNotEmpty()) {
                        $jamKeluar = $sameDayOuts->last()['scan_date'];
                    $isCrossDay = false;
                } else {
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $data['user_id'] . '_' . $nextDay;
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamKeluar = $nextDayOuts->last()['scan_date'];
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

    /**
     * Calculate total days in period (26 bulan sebelumnya - 25 bulan berjalan)
     */
    private function calculateTotalDaysInPeriod($startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        return $interval->days + 1; // +1 to include both start and end dates
    }

    /**
     * Calculate off days (days without shift) for a user in a period
     */
    private function calculateOffDays($userId, $outletId, $startDate, $endDate)
    {
        // Get all dates in the period
        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }
        
        // Get all shift data for this user in this period
        $shifts = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.outlet_id', $outletId)
            ->whereIn('us.tanggal', $period)
            ->select('us.tanggal', 's.shift_name', 'us.shift_id')
            ->get()
            ->keyBy('tanggal');
        
        // Count days without shift (or with 'off' shift)
        $offDays = 0;
        foreach ($period as $date) {
            $shift = $shifts->get($date);
            if (!$shift || is_null($shift->shift_id) || strtolower($shift->shift_name ?? '') === 'off') {
                $offDays++;
            }
        }
        
        return $offDays;
    }

    /**
     * Calculate PH days (Public Holiday compensations) for a user in a period
     */
    private function calculatePHDays($userId, $startDate, $endDate)
    {
        // Get holiday attendance compensations for this user in the period
        $compensations = DB::table('holiday_attendance_compensations')
            ->where('user_id', $userId)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'used']) // Only count approved or used compensations
            ->get();
        
        // Count total PH days (both extra_off and bonus compensations)
        $phDays = 0;
        foreach ($compensations as $compensation) {
            if ($compensation->compensation_type === 'extra_off') {
                // Extra off days are counted as 1 day each
                $phDays += $compensation->compensation_amount;
            } else if ($compensation->compensation_type === 'bonus') {
                // Bonus compensations are also counted as PH days
                $phDays += 1; // Each bonus compensation counts as 1 PH day
            }
        }
        
        return $phDays;
    }

    /**
     * Calculate PH data (days and bonus amount) for a user in a period
     */
    private function calculatePHData($userId, $startDate, $endDate)
    {
        // Get holiday attendance compensations for this user in the period
        $compensations = DB::table('holiday_attendance_compensations')
            ->where('user_id', $userId)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'used']) // Only count approved or used compensations
            ->get();
        
        $phDays = 0;
        $phBonus = 0;
        
        foreach ($compensations as $compensation) {
            if ($compensation->compensation_type === 'extra_off') {
                // Extra off days are counted as 1 day each
                $phDays += $compensation->compensation_amount;
            } else if ($compensation->compensation_type === 'bonus') {
                // Bonus compensations are also counted as PH days
                $phDays += 1; // Each bonus compensation counts as 1 PH day
                // Add bonus amount
                $phBonus += $compensation->compensation_amount;
            }
        }
        
        return [
            'days' => $phDays,
            'bonus' => $phBonus
        ];
    }

    /**
     * Calculate leave data (cuti, extra off, sakit) for a user in a period
     */
    private function calculateLeaveData($userId, $startDate, $endDate)
    {
        // Get approved absent requests for this user in the period
        $approvedAbsents = DB::table('absent_requests')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.user_id', $userId)
            ->where('absent_requests.status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            })
            ->select([
                'absent_requests.date_from',
                'absent_requests.date_to',
                'leave_types.name as leave_type_name'
            ])
            ->get();
        
        $cutiDays = 0;
        $extraOffDays = 0;
        $sakitDays = 0;
        
        foreach ($approvedAbsents as $absent) {
            // Calculate days between date_from and date_to
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            $daysCount = $fromDate->diff($toDate)->days + 1;
            
            // Categorize based on leave type name
            $leaveTypeName = strtolower($absent->leave_type_name);
            
            if (strpos($leaveTypeName, 'cuti') !== false || strpos($leaveTypeName, 'annual') !== false) {
                $cutiDays += $daysCount;
            } else if (strpos($leaveTypeName, 'extra off') !== false || strpos($leaveTypeName, 'extraoff') !== false) {
                $extraOffDays += $daysCount;
            } else if (strpos($leaveTypeName, 'sakit') !== false || strpos($leaveTypeName, 'sick') !== false) {
                $sakitDays += $daysCount;
            }
        }
        
        return [
            'cuti_days' => $cutiDays,
            'extra_off_days' => $extraOffDays,
            'sakit_days' => $sakitDays
        ];
    }

    /**
     * Calculate alpa days (days with shift but no attendance and no absent request)
     */
    private function calculateAlpaDays($userId, $outletId, $startDate, $endDate)
    {
        // Get all days in the period
        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }
        
        // Get user's shifts for the period
        $shifts = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.outlet_id', $outletId)
            ->whereIn('us.tanggal', $period)
            ->whereNotNull('us.shift_id') // Must have a shift (not off)
            ->where('s.shift_name', '!=', 'off') // Exclude 'off' shifts
            ->select('us.tanggal', 's.shift_name')
            ->get()
            ->keyBy('tanggal');
        
        // Get user's attendance data using the same logic as AttendanceReportController
        $rawData = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->select('a.scan_date', 'a.inoutmode')
            ->orderBy('a.scan_date')
            ->get();
        
        // Process attendance data like in AttendanceReportController
        $processedData = [];
        foreach ($rawData as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'scans' => []
                ];
            }
            
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }
        
        // Process each day to determine if there's valid attendance
        $attendanceDates = [];
        foreach ($processedData as $key => $data) {
            $scans = collect($data['scans'])->sortBy('scan_date');
            $inScans = $scans->where('inoutmode', 1);
            $outScans = $scans->where('inoutmode', 2);
            
            // Check if there's valid attendance (first in and last out)
            $jamMasuk = $inScans->first()['scan_date'] ?? null;
            $jamKeluar = null;
            $isCrossDay = false;
            
            if ($jamMasuk) {
                // Cari scan keluar di hari yang sama
                $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                
                if ($sameDayOut) {
                    // Ada scan keluar di hari yang sama
                    $jamKeluar = $sameDayOut['scan_date'];
                    $isCrossDay = false;
                } else {
                    // Cari scan keluar di hari berikutnya (cross-day)
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $nextDay;
                    
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                        
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamKeluar = $nextDayOuts->last()['scan_date'];
                            $isCrossDay = true;
                        }
                    }
                }
            }
            
            // If there's both check-in and check-out, consider it as attended
            if ($jamMasuk && $jamKeluar) {
                $attendanceDates[] = $data['tanggal'];
                
                // For cross-day, also mark the next day as attended (since OUT happened there)
                if ($isCrossDay) {
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $attendanceDates[] = $nextDay;
                }
            }
        }
        
        // Get user's approved absent requests for the period
        $absentDates = DB::table('absent_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('date_from', [$startDate, $endDate])
                      ->orWhereBetween('date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('date_from', '<=', $startDate)
                            ->where('date_to', '>=', $endDate);
                      });
            })
            ->get();
        
        // Create array of all absent dates
        $absentDateArray = [];
        foreach ($absentDates as $absent) {
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            while ($fromDate <= $toDate) {
                $absentDateArray[] = $fromDate->format('Y-m-d');
                $fromDate->modify('+1 day');
            }
        }
        
        $alpaDays = 0;
        $today = date('Y-m-d');
        
        // Check each day in the period
        foreach ($period as $date) {
            // Only count alpa for dates that have already passed (including today)
            if ($date > $today) {
                continue; // Skip future dates
            }
            
            $hasShift = $shifts->has($date);
            $hasAttendance = in_array($date, $attendanceDates);
            $hasAbsent = in_array($date, $absentDateArray);
            
            // Alpa: has shift but no attendance and no absent request
            if ($hasShift && !$hasAttendance && !$hasAbsent) {
                $alpaDays++;
            }
        }
        
        return $alpaDays;
    }

    // Endpoint untuk summary per karyawan
    public function employeeSummary(Request $request)
    {
        // Set timeout untuk mencegah loading terlalu lama
        set_time_limit(300); // 5 menit
        
        // Tambahkan progress tracking
        $startTime = microtime(true);
        $maxExecutionTime = 280; // 4.5 menit untuk safety margin
        
        \Log::info('=== EMPLOYEE SUMMARY START ===');
        \Log::info('Request received at: ' . now());
        \Log::info('Max execution time set to: ' . $maxExecutionTime . ' seconds');
        
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        // Log filter values for debugging
        \Log::info('Employee Summary Filter Values', [
            'outlet_id' => $outletId,
            'division_id' => $divisionId,
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);

        $rows = collect();
        $employeeSummary = collect(); // Initialize employeeSummary to prevent undefined variable error
        
        if (!empty($outletId) || !empty($divisionId) || !empty($bulan) || !empty($tahun)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

            \Log::info('Date range calculated', [
                'start' => $start,
                'end' => $end
            ]);

            // Query data absensi dengan optimasi
            \Log::info('Starting database query at: ' . now());
            
            try {
                // Check execution time before starting heavy operations
                $elapsedTime = microtime(true) - $startTime;
                if ($elapsedTime > $maxExecutionTime) {
                    throw new \Exception('Execution time limit exceeded before processing started');
                }
                
                // Optimasi: Gunakan chunk untuk data yang besar
                $chunkSize = 5000;
                $rawData = collect();
                
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
                        'u.division_id'
                    )
                    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);

                // Apply filters - Filter outlet hanya untuk dropdown karyawan, bukan untuk report
                if (!empty($divisionId)) {
                    $sub->where('u.division_id', $divisionId);
                    \Log::info('Applied division filter: ' . $divisionId);
                }

                // Gunakan chunk untuk mencegah memory overflow
                \Log::info('Executing query with chunking...');
                $sub->orderBy('a.scan_date')->chunk($chunkSize, function($chunk) use (&$rawData) {
                    $rawData = $rawData->merge($chunk);
                    \Log::info('Chunk processed, current total: ' . $rawData->count());
                });

                \Log::info('Query completed at: ' . now());
                \Log::info('Raw data count: ' . $rawData->count());
                \Log::info('Memory usage after query: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');

                if ($rawData->count() > 10000) {
                    \Log::warning('Large dataset detected: ' . $rawData->count() . ' records. This may cause performance issues.');
                }

                // Proses data manual untuk menangani cross-day
                \Log::info('Starting data processing step 1: Grouping scans...');
                $processedData = [];
                
                // Step 1: Kelompokkan scan berdasarkan user dan tanggal
                $processedCount = 0;
                $totalScans = $rawData->count();
                
                foreach ($rawData as $index => $scan) {
                    if ($index % 1000 == 0) {
                        \Log::info('Processing scan ' . $index . ' of ' . $totalScans);
                        // Force garbage collection untuk mencegah memory leak
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                    
                    $date = date('Y-m-d', strtotime($scan->scan_date));
                    $key = $scan->user_id . '_' . $date;
                    
                    if (!isset($processedData[$key])) {
                        $processedData[$key] = [
                            'tanggal' => $date,
                            'user_id' => $scan->user_id,
                            'nama_lengkap' => $scan->nama_lengkap,
                            'division_id' => $scan->division_id,
                            'scans' => []
                        ];
                    }
                    
                    $processedData[$key]['scans'][] = [
                        'scan_date' => $scan->scan_date,
                        'inoutmode' => $scan->inoutmode
                    ];
                }
                
                \Log::info('Step 1 completed. Processed data groups: ' . count($processedData));
                \Log::info('Memory usage after step 1: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                
                // Clear raw data untuk menghemat memory
                $rawData = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                // Step 2: Proses setiap kelompok untuk menentukan jam masuk/keluar
                \Log::info('Starting data processing step 2: Processing time calculations...');
                $finalData = [];
                $totalGroups = count($processedData);
                $groupIndex = 0;
                
                foreach ($processedData as $key => $data) {
                    $groupIndex++;
                    if ($groupIndex % 100 == 0) {
                        \Log::info('Processing group ' . $groupIndex . ' of ' . $totalGroups);
                        // Force garbage collection
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                    
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
                        $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                        
                    if ($sameDayOuts->isNotEmpty()) {
                        $jamKeluar = $sameDayOuts->last()['scan_date'];
                            $isCrossDay = false;
                        } else {
                            // Cari scan keluar di hari berikutnya
                            $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                            $nextDayKey = $data['user_id'] . '_' . $nextDay;
                            
                            if (isset($processedData[$nextDayKey])) {
                                $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                                $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                                
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamKeluar = $nextDayOuts->last()['scan_date'];
                                    $isCrossDay = true;
                                    $totalKeluar = 1;
                                }
                            }
                        }
                    }
                    
                    $finalData[] = [
                        'tanggal' => $data['tanggal'],
                        'user_id' => $data['user_id'],
                        'nama_lengkap' => $data['nama_lengkap'],
                        'division_id' => $data['division_id'],
                        'jam_masuk' => $jamMasuk,
                        'jam_keluar' => $jamKeluar,
                        'total_masuk' => $totalMasuk,
                        'total_keluar' => $totalKeluar,
                        'is_cross_day' => $isCrossDay
                    ];
                }
                
                \Log::info('Step 2 completed. Final data count: ' . count($finalData));
                \Log::info('Memory usage after step 2: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');

                $dataRows = collect($finalData);
                
                // Clear processed data untuk menghemat memory
                $processedData = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                // Ambil data shift untuk perhitungan lembur
                \Log::info('Fetching shift data...');
                $shiftData = DB::table('shifts')->first();
                \Log::info('Shift data fetched: ' . ($shiftData ? 'Yes' : 'No'));

                // Hitung lembur untuk setiap baris
                \Log::info('Calculating overtime for each row...');
                $overtimeCount = 0;
                foreach ($dataRows as $index => $row) {
                    if ($index % 100 == 0) {
                        \Log::info('Calculating overtime for row ' . $index . ' of ' . $dataRows->count());
                        // Force garbage collection
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                    
                    if ($row['jam_masuk'] && $row['jam_keluar'] && $shiftData) {
                        $shiftStart = $shiftData->time_start ?? '08:00:00';
                        $shiftEnd = $shiftData->time_end ?? '17:00:00';
                        
                        $shiftEndDateTime = date('Y-m-d', strtotime($row['jam_masuk'])) . ' ' . $shiftEnd;
                        $scanOutDateTime = $row['jam_keluar'];
                        
                        if ($row['is_cross_day']) {
                            $shiftEndDateTime = date('Y-m-d', strtotime($row['jam_masuk'] . ' +1 day')) . ' ' . $shiftEnd;
                        }
                        
                        $shiftEndTime = strtotime($shiftEndDateTime);
                        $scanOutTime = strtotime($scanOutDateTime);
                        
                        if ($scanOutTime > $shiftEndTime) {
                            $lemburHours = round(($scanOutTime - $shiftEndTime) / 3600, 2);
                            $row['lembur'] = $lemburHours;
                            $overtimeCount++;
                        } else {
                            $row['lembur'] = 0;
                        }
                    } else {
                        $row['lembur'] = 0;
                    }
                }
                
                \Log::info('Overtime calculation completed. Rows with overtime: ' . $overtimeCount);

                // Ambil semua tanggal libur dalam periode
                \Log::info('Fetching holiday data...');
                $holidays = DB::table('tbl_kalender_perusahaan')
                    ->whereBetween('tgl_libur', [$start, $end])
                    ->pluck('keterangan', 'tgl_libur');
                \Log::info('Holiday data fetched. Count: ' . $holidays->count());

                // Build rows for each tanggal in period
                \Log::info('Building final rows with shift and holiday data...');
                $rows = collect();
                $rowsWithShift = 0;
                
                // OPTIMASI: Batch query untuk shift data untuk mencegah N+1 query problem
                \Log::info('Fetching all shift data in batch...');
                $allShiftData = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                    ->whereIn('us.outlet_id', $dataRows->pluck('id_outlet')->unique()->values())
                    ->select('us.user_id', 'us.tanggal', 'us.outlet_id', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->tanggal . '_' . $item->outlet_id;
                    });
                
                \Log::info('Batch shift data fetched. Total shift records: ' . $allShiftData->count());
                
                // OPTIMASI: Progress tracking yang lebih detail dengan time estimation
                $startRowBuildingTime = microtime(true);
                $estimatedTimePerRow = 0.1; // 0.1 detik per row (estimasi)
                $totalEstimatedTime = $dataRows->count() * $estimatedTimePerRow;
                
                \Log::info('Estimated time for building rows: ' . round($totalEstimatedTime, 1) . ' seconds');
                \Log::info('Processing ' . $dataRows->count() . ' rows with batch optimization...');
                
                foreach ($dataRows as $index => $row) {
                    // Check execution time every 50 iterations (lebih sering untuk mencegah timeout)
                    if ($index % 50 == 0) {
                        $elapsedTime = microtime(true) - $startTime;
                        $remainingTime = $maxExecutionTime - $elapsedTime;
                        
                        if ($elapsedTime > $maxExecutionTime) {
                            throw new \Exception('Execution time limit exceeded during row building');
                        }
                        
                        // Calculate progress dan estimated remaining time
                        $progress = round(($index / $dataRows->count()) * 100, 1);
                        $elapsedRowTime = microtime(true) - $startRowBuildingTime;
                        $avgTimePerRow = $elapsedRowTime / max(1, $index);
                        $remainingRows = $dataRows->count() - $index;
                        $estimatedRemainingTime = $remainingRows * $avgTimePerRow;
                        
                        \Log::info('Building row ' . $index . ' of ' . $dataRows->count() . ' (' . $progress . '%) - ETA: ' . round($estimatedRemainingTime, 1) . 's');
                        
                        // Warning jika estimated remaining time melebihi remaining execution time
                        if ($estimatedRemainingTime > $remainingTime) {
                            \Log::warning('WARNING: Estimated remaining time (' . round($estimatedRemainingTime, 1) . 's) exceeds remaining execution time (' . round($remainingTime, 1) . 's)');
                        }
                        
                        // Force garbage collection
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                    
                    $jam_masuk = $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : null;
                    $jam_keluar = $row['jam_keluar'] ? date('H:i:s', strtotime($row['jam_keluar'])) : null;
                    $telat = 0;
                    $lembur = 0;
                    
                    // OPTIMASI: Gunakan cached shift data instead of individual query
                    $shiftKey = $row['user_id'] . '_' . $row['tanggal'] . '_' . $row['id_outlet'];
                    $shift = $allShiftData->get($shiftKey, collect())->first();

                    if ($shift) {
                        $rowsWithShift++;
                        
                        // Hitung telat dan lembur berdasarkan shift
                        if ($shift->time_start && $jam_masuk) {
                            $shiftStartTime = strtotime($shift->time_start);
                            $masukTime = strtotime($jam_masuk);
                            $diff = $masukTime - $shiftStartTime;
                            $telat = $diff > 0 ? round($diff/60) : 0;
                        }
                        if ($shift->time_end && $jam_keluar) {
                            $shiftEndDateTime = date('Y-m-d', strtotime($row['tanggal'])) . ' ' . $shift->time_end;
                            $scanOutDateTime = $row['jam_keluar'];
                            $shiftEndTime = strtotime($shiftEndDateTime);
                            $keluarTime = strtotime($scanOutDateTime);
                            $diff = $keluarTime - $shiftEndTime;
                            $lembur = $diff > 0 ? floor($diff/3600) : 0;
                        }
                    }

                    $rows->push((object)[
                        'tanggal' => $row['tanggal'],
                        'user_id' => $row['user_id'],
                        'nama_lengkap' => $row['nama_lengkap'],
                        'division_id' => $row['division_id'],
                        'outlet_id' => $row['id_outlet'],
                        'nama_outlet' => $row['nama_outlet'],
                        'jam_masuk' => $jam_masuk,
                        'jam_keluar' => $jam_keluar,
                        'total_masuk' => $row['total_masuk'],
                        'total_keluar' => $row['total_keluar'],
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_cross_day' => $row['is_cross_day'],
                    ]);
                }
                
                $totalRowBuildingTime = microtime(true) - $startRowBuildingTime;
                \Log::info('Final rows built. Total rows: ' . $rows->count());
                \Log::info('Rows with shift data: ' . $rowsWithShift);
                \Log::info('Total time for building rows: ' . round($totalRowBuildingTime, 2) . ' seconds');
                \Log::info('Average time per row: ' . round($totalRowBuildingTime / $dataRows->count(), 4) . ' seconds');
                \Log::info('Memory usage after building rows: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                
                // Clear data rows dan shift data untuk menghemat memory
                $dataRows = null;
                $allShiftData = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                // Group by employee and calculate summary
                \Log::info('Processing employee summary, rows count: ' . $rows->count());
                
                // Tambah progress logging
                $totalEmployees = $rows->groupBy('user_id')->count();
                \Log::info('Total unique employees to process: ' . $totalEmployees);
                
                // Get all user data (NIK and jabatan) at once to avoid N+1 queries
                $userIds = $rows->pluck('user_id')->unique()->toArray();
                \Log::info('Fetching user data for ' . count($userIds) . ' users');
                
                $allUserData = DB::table('users as u')
                    ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->select('u.id', 'u.nik', 'j.nama_jabatan as jabatan')
                    ->whereIn('u.id', $userIds)
                    ->get()
                    ->keyBy('id');
                
                \Log::info('User data fetched:', [
                    'count' => $allUserData->count(),
                    'sample' => $allUserData->take(3)->toArray()
                ]);
                
                // Tambahkan progress tracking untuk mencegah infinite loop
                $processedEmployees = 0;
                $maxEmployeeProcessingTime = 30; // 30 detik per employee
                $employeeSummary = collect();
                
                // Gunakan foreach biasa untuk mencegah infinite loop pada map
                $employeeGroups = $rows->groupBy('user_id');
                $employeeIndex = 0;
                
                foreach ($employeeGroups as $userId => $employeeRows) {
                    $employeeIndex++;
                    $employeeStartTime = microtime(true);
                    
                    // Check execution time during employee processing
                    $elapsedTime = microtime(true) - $startTime;
                    if ($elapsedTime > $maxExecutionTime) {
                        throw new \Exception('Execution time limit exceeded during employee summary processing');
                    }
                    
                    \Log::info('Processing employee ' . $employeeIndex . ' of ' . $totalEmployees . ': ' . $employeeRows->first()->nama_lengkap);
                    
                    try {
                        $firstRow = $employeeRows->first();
                        
                        // Get NIK and jabatan from pre-fetched data
                        $userData = $allUserData->get($firstRow->user_id);
                        
                        \Log::info('User data for user_id ' . $firstRow->user_id . ':', [
                            'user_data' => $userData,
                            'nik' => $userData->nik ?? 'null',
                            'jabatan' => $userData->jabatan ?? 'null'
                        ]);
                        
                        // Calculate off days (days without shift)
                        $offDays = $this->calculateOffDays($firstRow->user_id, $firstRow->outlet_id, $start, $end);
                        
                        // Calculate PH days (Public Holiday compensations)
                        $phData = $this->calculatePHData($firstRow->user_id, $start, $end);
                        
                        // Calculate leave data (cuti, extra off, sakit)
                        $leaveData = $this->calculateLeaveData($firstRow->user_id, $start, $end);
                        
                        // Calculate alpa days (days with shift but no attendance and no absent request)
                        $alpaDays = $this->calculateAlpaDays($firstRow->user_id, $firstRow->outlet_id, $start, $end);
                        
                        $result = [
                            'user_id' => $firstRow->user_id,
                            'nik' => $userData->nik ?? '-',
                            'nama_lengkap' => $firstRow->nama_lengkap,
                            'jabatan' => $userData->jabatan ?? '-',
                            'division_id' => $firstRow->division_id,
                            'outlet_id' => $firstRow->outlet_id,
                            'nama_outlet' => $firstRow->nama_outlet,
                            'hari_kerja' => $employeeRows->count(), // Jumlah hari yang bekerja
                            'off_days' => $offDays, // Jumlah hari tanpa shift
                            'ph_days' => $phData['days'], // Jumlah hari libur nasional dengan kompensasi
                            'ph_bonus' => $phData['bonus'], // Total bonus PH yang diterima
                            'cuti_days' => $leaveData['cuti_days'], // Jumlah hari cuti
                            'extra_off_days' => $leaveData['extra_off_days'], // Jumlah hari extra off
                            'sakit_days' => $leaveData['sakit_days'], // Jumlah hari sakit
                            'alpa_days' => $alpaDays, // Jumlah hari alpa
                            'ot_full_days' => $employeeRows->sum('lembur'), // Total lembur (OT Full)
                            'total_telat' => $employeeRows->sum('telat'),
                            'total_lembur' => $employeeRows->sum('lembur'),
                            'total_days' => $this->calculateTotalDaysInPeriod($start, $end), // Total hari dalam periode
                        ];
                        
                        $employeeSummary->push($result);
                        $processedEmployees++;
                        $employeeProcessingTime = microtime(true) - $employeeStartTime;
                        
                        if ($employeeProcessingTime > $maxEmployeeProcessingTime) {
                            \Log::warning('Employee ' . $firstRow->nama_lengkap . ' took too long to process: ' . round($employeeProcessingTime, 2) . ' seconds');
                        }
                        
                        \Log::info('Employee ' . $firstRow->nama_lengkap . ' processed in ' . round($employeeProcessingTime, 2) . ' seconds');
                        
                        // Progress update setiap 10 employee
                        if ($employeeIndex % 10 == 0) {
                            \Log::info('Progress: ' . $employeeIndex . ' of ' . $totalEmployees . ' employees processed (' . round(($employeeIndex / $totalEmployees) * 100, 1) . '%)');
                        }
                        
                    } catch (\Exception $e) {
                        \Log::error('Error processing employee ' . ($employeeRows->first()->nama_lengkap ?? 'Unknown') . ': ' . $e->getMessage());
                        // Return default values untuk employee yang error
                        $errorResult = [
                            'user_id' => $employeeRows->first()->user_id ?? 0,
                            'nik' => '-',
                            'nama_lengkap' => $employeeRows->first()->nama_lengkap ?? 'Error Processing',
                            'jabatan' => '-',
                            'division_id' => $employeeRows->first()->division_id ?? 0,
                            'outlet_id' => $employeeRows->first()->outlet_id ?? 0,
                            'nama_outlet' => $employeeRows->first()->nama_outlet ?? 'Error',
                            'hari_kerja' => 0,
                            'off_days' => isset($start) && isset($end) ? $this->calculateOffDays($employeeRows->first()->user_id ?? 0, $employeeRows->first()->outlet_id ?? 0, $start, $end) : 0,
                            'ph_days' => isset($start) && isset($end) ? $this->calculatePHData($employeeRows->first()->user_id ?? 0, $start, $end)['days'] : 0,
                            'ph_bonus' => isset($start) && isset($end) ? $this->calculatePHData($employeeRows->first()->user_id ?? 0, $start, $end)['bonus'] : 0,
                            'cuti_days' => isset($start) && isset($end) ? $this->calculateLeaveData($employeeRows->first()->user_id ?? 0, $start, $end)['cuti_days'] : 0,
                            'extra_off_days' => isset($start) && isset($end) ? $this->calculateLeaveData($employeeRows->first()->user_id ?? 0, $start, $end)['extra_off_days'] : 0,
                            'sakit_days' => isset($start) && isset($end) ? $this->calculateLeaveData($employeeRows->first()->user_id ?? 0, $start, $end)['sakit_days'] : 0,
                            'alpa_days' => isset($start) && isset($end) ? $this->calculateAlpaDays($employeeRows->first()->user_id ?? 0, $employeeRows->first()->outlet_id ?? 0, $start, $end) : 0,
                            'ot_full_days' => 0,
                            'total_telat' => 0,
                            'total_lembur' => 0,
                            'total_days' => isset($start) && isset($end) ? $this->calculateTotalDaysInPeriod($start, $end) : 0,
                        ];
                        
                        $employeeSummary->push($errorResult);
                        $processedEmployees++;
                    }
                }
                
                // Sort employee summary by nama_lengkap
                $employeeSummary = $employeeSummary->sortBy('nama_lengkap')->values();
                
                \Log::info('Employee summary completed, employee count: ' . $employeeSummary->count());
                \Log::info('Successfully processed employees: ' . $processedEmployees);
                \Log::info('Memory usage after employee summary: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
                
                // Clear rows untuk menghemat memory
                $rows = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
            } catch (\Exception $e) {
                \Log::error('Error in employee summary processing: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                throw $e;
            }
        }

        // Dropdown filter
        \Log::info('Fetching dropdown data...');
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();
        \Log::info('Dropdown data fetched. Outlets: ' . $outlets->count() . ', Divisions: ' . $divisions->count());

        \Log::info('=== EMPLOYEE SUMMARY COMPLETED ===');
        \Log::info('Final memory usage: ' . memory_get_usage(true) / 1024 / 1024 . ' MB');
        \Log::info('Total execution time: ' . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . ' seconds');

        // Calculate summary statistics
        $summary = [
            'total_employees' => $employeeSummary ? $employeeSummary->count() : 0,
            'total_lembur' => $employeeSummary ? $employeeSummary->sum('total_lembur') : 0,
            'total_telat' => $employeeSummary ? $employeeSummary->sum('total_telat') : 0,
            'avg_lembur_per_employee' => $employeeSummary && $employeeSummary->count() > 0 ? 
                round($employeeSummary->sum('total_lembur') / $employeeSummary->count(), 2) : 0,
            'avg_telat_per_employee' => $employeeSummary && $employeeSummary->count() > 0 ? 
                round($employeeSummary->sum('total_telat') / $employeeSummary->count(), 2) : 0,
        ];

        return Inertia::render('AttendanceReport/EmployeeSummary', [
            'rows' => $employeeSummary ?? collect(),
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'bulan' => $bulan,
                'tahun' => $tahun,
            ],
            'period' => isset($start) ? ['start' => $start, 'end' => $end] : null,
            'summary' => $summary,
            'user' => auth()->user(),
        ]);
    }

    // Export Excel untuk Employee Summary
    public function exportEmployeeSummary(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        // Set timeout untuk mencegah loading terlalu lama
        set_time_limit(300); // 5 menit
        
        \Log::info('=== EXPORT EMPLOYEE SUMMARY START ===');
        \Log::info('Export request received at: ' . now());
        
        if (!empty($outletId) || !empty($divisionId) || !empty($bulan) || !empty($tahun)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

            \Log::info('Date range calculated', [
                'start' => $start,
                'end' => $end
            ]);

            try {
                // Query data absensi dengan optimasi
                \Log::info('Starting database query for export...');
                
                $chunkSize = 5000;
                $rawData = collect();
                
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
                        'u.division_id'
                    )
                    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);

                // Apply filters - Filter outlet hanya untuk dropdown karyawan, bukan untuk report
                if (!empty($divisionId)) {
                    $sub->where('u.division_id', $divisionId);
                }

                $sub->orderBy('a.scan_date')->chunk($chunkSize, function($chunk) use (&$rawData) {
                    $rawData = $rawData->merge($chunk);
                });

                \Log::info('Query completed. Raw data count: ' . $rawData->count());

                // Proses data seperti di employeeSummary
                $processedData = [];
                foreach ($rawData as $scan) {
                    $date = date('Y-m-d', strtotime($scan->scan_date));
                    $key = $scan->user_id . '_' . $date;
                    
                    if (!isset($processedData[$key])) {
                        $processedData[$key] = [
                            'tanggal' => $date,
                            'user_id' => $scan->user_id,
                            'nama_lengkap' => $scan->nama_lengkap,
                            'division_id' => $scan->division_id,
                            'scans' => []
                        ];
                    }
                    
                    $processedData[$key]['scans'][] = [
                        'scan_date' => $scan->scan_date,
                        'inoutmode' => $scan->inoutmode
                    ];
                }

                $finalData = [];
                foreach ($processedData as $key => $data) {
                    $scans = collect($data['scans'])->sortBy('scan_date');
                    $inScans = $scans->where('inoutmode', 1);
                    $outScans = $scans->where('inoutmode', 2);
                    
                    $jamMasuk = $inScans->first()['scan_date'] ?? null;
                    $jamKeluar = null;
                    $isCrossDay = false;
                    
                    if ($jamMasuk) {
                        $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                        
                    if ($sameDayOuts->isNotEmpty()) {
                        $jamKeluar = $sameDayOuts->last()['scan_date'];
                            $isCrossDay = false;
                        } else {
                            $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                            $nextDayKey = $data['user_id'] . '_' . $nextDay;
                            
                            if (isset($processedData[$nextDayKey])) {
                                $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                                $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                                
                            if ($nextDayOuts->isNotEmpty()) {
                                $jamKeluar = $nextDayOuts->last()['scan_date'];
                                    $isCrossDay = true;
                                }
                            }
                        }
                    }
                    
                    $finalData[] = [
                        'tanggal' => $data['tanggal'],
                        'user_id' => $data['user_id'],
                        'nama_lengkap' => $data['nama_lengkap'],
                        'division_id' => $data['division_id'],
                        'jam_masuk' => $jamMasuk,
                        'jam_keluar' => $jamKeluar,
                        'is_cross_day' => $isCrossDay
                    ];
                }

                $dataRows = collect($finalData);

                // Ambil data shift untuk perhitungan lembur
                $shiftData = DB::table('shifts')->first();

                // Hitung lembur untuk setiap baris
                foreach ($dataRows as $row) {
                    if ($row['jam_masuk'] && $row['jam_keluar'] && $shiftData) {
                        $shiftStart = $shiftData->time_start ?? '08:00:00';
                        $shiftEnd = $shiftData->time_end ?? '17:00:00';
                        
                        $shiftEndDateTime = date('Y-m-d', strtotime($row['jam_masuk'])) . ' ' . $shiftEnd;
                        $scanOutDateTime = $row['jam_keluar'];
                        
                        if ($row['is_cross_day']) {
                            $shiftEndDateTime = date('Y-m-d', strtotime($row['jam_masuk'] . ' +1 day')) . ' ' . $shiftEnd;
                        }
                        
                        $shiftEndTime = strtotime($shiftEndDateTime);
                        $scanOutTime = strtotime($scanOutDateTime);
                        
                        if ($scanOutTime > $shiftEndTime) {
                            $lemburHours = round(($scanOutTime - $shiftEndTime) / 3600, 2);
                            $row['lembur'] = $lemburHours;
                        } else {
                            $row['lembur'] = 0;
                        }
                    } else {
                        $row['lembur'] = 0;
                    }
                }

                // Build rows dengan shift data
                $rows = collect();
                $allShiftData = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                    ->whereIn('us.outlet_id', $dataRows->pluck('id_outlet')->unique()->values())
                    ->select('us.user_id', 'us.tanggal', 'us.outlet_id', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->tanggal . '_' . $item->outlet_id;
                    });

                foreach ($dataRows as $row) {
                    // Fix: Pastikan data jam_masuk dan jam_keluar tidak null
                    $jam_masuk = !empty($row['jam_masuk']) ? date('H:i:s', strtotime($row['jam_masuk'])) : null;
                    $jam_keluar = !empty($row['jam_keluar']) ? date('H:i:s', strtotime($row['jam_keluar'])) : null;
                    $telat = 0;
                    $lembur = 0;
                    
                    $shiftKey = $row['user_id'] . '_' . $row['tanggal'] . '_' . $row['id_outlet'];
                    $shift = $allShiftData->get($shiftKey, collect())->first();
                    
                    // Debug: Log jika data kosong
                    if (empty($jam_masuk) && empty($jam_keluar)) {
                        \Log::warning('Empty attendance data for user:', [
                            'user_id' => $row['user_id'],
                            'tanggal' => $row['tanggal'],
                            'id_outlet' => $row['id_outlet'],
                            'raw_jam_masuk' => $row['jam_masuk'] ?? 'null',
                            'raw_jam_keluar' => $row['jam_keluar'] ?? 'null'
                        ]);
                    }

                    if ($shift) {
                        if ($shift->time_start && $jam_masuk) {
                            $shiftStartTime = strtotime($shift->time_start);
                            $masukTime = strtotime($jam_masuk);
                            $diff = $masukTime - $shiftStartTime;
                            $telat = $diff > 0 ? round($diff/60) : 0;
                        }
                        if ($shift->time_end && $jam_keluar) {
                            $shiftEndDateTime = date('Y-m-d', strtotime($row['tanggal'])) . ' ' . $shift->time_end;
                            $scanOutDateTime = $row['jam_keluar'];
                            $shiftEndTime = strtotime($shiftEndDateTime);
                            $keluarTime = strtotime($scanOutDateTime);
                            $diff = $keluarTime - $shiftEndTime;
                            $lembur = $diff > 0 ? floor($diff/3600) : 0;
                        }
                    }

                    // Debug logging untuk troubleshooting
                    \Log::info('Processing row for main table:', [
                        'user_id' => $row['user_id'],
                        'tanggal' => $row['tanggal'],
                        'id_outlet' => $row['id_outlet'],
                        'nama_outlet' => $row['nama_outlet'],
                        'jam_masuk' => $jam_masuk,
                        'jam_keluar' => $jam_keluar,
                        'has_shift' => $shift ? true : false
                    ]);

                    // Fix: Pastikan data outlet dan jam selalu ada, meskipun kosong
                    $rows->push((object)[
                        'tanggal' => $row['tanggal'],
                        'user_id' => $row['user_id'],
                        'nama_lengkap' => $row['nama_lengkap'],
                        'division_id' => $row['division_id'],
                        'outlet_id' => $row['id_outlet'],
                        'nama_outlet' => $row['nama_outlet'] ?? 'Unknown Outlet',
                        'jam_masuk' => $jam_masuk,
                        'jam_keluar' => $jam_keluar,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_cross_day' => $row['is_cross_day'] ?? false,
                    ]);
                }

                // Group by employee dan hitung summary
                $employeeSummary = collect();
                $employeeGroups = $rows->groupBy('user_id');
                
                // Get all user data (NIK and jabatan) at once to avoid N+1 queries
                $userIds = $rows->pluck('user_id')->unique()->toArray();
                $allUserData = DB::table('users as u')
                    ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->select('u.id', 'u.nik', 'j.nama_jabatan as jabatan')
                    ->whereIn('u.id', $userIds)
                    ->get()
                    ->keyBy('id');

                foreach ($employeeGroups as $userId => $employeeRows) {
                    $firstRow = $employeeRows->first();
                    
                    // Get NIK and jabatan from pre-fetched data
                    $userData = $allUserData->get($firstRow->user_id);
                    
                    // Calculate off days (days without shift)
                    $offDays = $this->calculateOffDays($firstRow->user_id, $firstRow->outlet_id, $start, $end);
                    
                    // Calculate PH days (Public Holiday compensations)
                    $phData = $this->calculatePHData($firstRow->user_id, $start, $end);
                    
                    // Calculate leave data (cuti, extra off, sakit)
                    $leaveData = $this->calculateLeaveData($firstRow->user_id, $start, $end);
                    
                    // Calculate alpa days (days with shift but no attendance and no absent request)
                    $alpaDays = $this->calculateAlpaDays($firstRow->user_id, $firstRow->outlet_id, $start, $end);
                    
                    $result = (object)[
                        'user_id' => $firstRow->user_id,
                        'nik' => $userData->nik ?? '-',
                        'nama_lengkap' => $firstRow->nama_lengkap,
                        'jabatan' => $userData->jabatan ?? '-',
                        'division_id' => $firstRow->division_id,
                        'outlet_id' => $firstRow->outlet_id,
                        'nama_outlet' => $firstRow->nama_outlet,
                        'hari_kerja' => $employeeRows->count(), // Jumlah hari yang bekerja
                        'off_days' => $offDays, // Jumlah hari tanpa shift
                        'ph_days' => $phData['days'], // Jumlah hari libur nasional dengan kompensasi
                        'ph_bonus' => $phData['bonus'], // Total bonus PH yang diterima
                        'cuti_days' => $leaveData['cuti_days'], // Jumlah hari cuti
                        'extra_off_days' => $leaveData['extra_off_days'], // Jumlah hari extra off
                        'sakit_days' => $leaveData['sakit_days'], // Jumlah hari sakit
                        'alpa_days' => $alpaDays, // Jumlah hari alpa
                        'ot_full_days' => $employeeRows->sum('lembur'), // Total lembur (OT Full)
                        'total_telat' => $employeeRows->sum('telat'),
                        'total_lembur' => $employeeRows->sum('lembur'),
                        'total_days' => $this->calculateTotalDaysInPeriod($start, $end), // Total hari dalam periode
                    ];
                    
                    $employeeSummary->push($result);
                }

                // Sort employee summary by nama_lengkap
                $employeeSummary = $employeeSummary->sortBy('nama_lengkap')->values();

                \Log::info('Employee summary prepared for export. Count: ' . $employeeSummary->count());

                // Ambil nama outlet dan divisi untuk nama file
                $outletName = null;
                $divisionName = null;
                
                if (!empty($outletId)) {
                    $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
                    $outletName = $outlet ? $outlet->nama_outlet : null;
                }
                
                if (!empty($divisionId)) {
                    $division = DB::table('tbl_data_divisi')->where('id', $divisionId)->first();
                    $divisionName = $division ? $division->nama_divisi : null;
                }

                // Generate nama file
                $fileName = 'employee_summary_';
                if ($outletName) {
                    $fileName .= str_replace(' ', '_', $outletName) . '_';
                }
                if ($divisionName) {
                    $fileName .= str_replace(' ', '_', $divisionName) . '_';
                }
                $fileName .= $start . '_sampai_' . $end . '.xlsx';

                \Log::info('Exporting to file: ' . $fileName);

                $export = new EmployeeSummaryExport($employeeSummary);
                $export->fileName = $fileName;
                
                \Log::info('=== EXPORT EMPLOYEE SUMMARY COMPLETED ===');
                
                return $export;

            } catch (\Exception $e) {
                \Log::error('Error in employee summary export: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                throw $e;
            }
        }

        // Jika tidak ada filter, return empty export
        $export = new EmployeeSummaryExport(collect());
        $export->fileName = 'employee_summary_no_filter.xlsx';
        return $export;
    }
    
    /**
     * Get approved absent requests for all users in the date range
     */
    private function getApprovedAbsentRequests($startDate, $endDate)
    {
        $approvedAbsents = DB::table('absent_requests')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            })
            ->select([
                'absent_requests.user_id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'leave_types.name as leave_type_name'
            ])
            ->get();
            
        // Group by user_id and date for easy lookup
        $groupedAbsents = [];
        foreach ($approvedAbsents as $absent) {
            $userId = $absent->user_id;
            if (!isset($groupedAbsents[$userId])) {
                $groupedAbsents[$userId] = [];
            }
            
            // Add all dates in the range
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            
            while ($fromDate <= $toDate) {
                $dateStr = $fromDate->format('Y-m-d');
                $groupedAbsents[$userId][$dateStr] = [
                    'leave_type_name' => $absent->leave_type_name,
                    'reason' => $absent->reason
                ];
                $fromDate->modify('+1 day');
            }
        }
        
        return $groupedAbsents;
    }
} 