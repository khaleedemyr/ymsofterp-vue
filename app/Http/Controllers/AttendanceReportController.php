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
            // Apply filters
            if (!empty($outletId)) {
                $sub->where('u.id_outlet', $outletId);
            }
            if (!empty($divisionId)) {
                $sub->where('u.division_id', $divisionId);
            }
            if (!empty($search)) {
                $sub->where('u.nama_lengkap', '=', $search);
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
            
            // Step 2: Proses setiap kelompok dengan logika cross-day yang lebih cerdas
            $finalData = [];
            foreach ($processedData as $key => $data) {
                $result = $this->processSmartCrossDayAttendance($data, $processedData);
                $finalData[] = $result;
            }
            
            $dataRows = collect($finalData)->map(function($item) {
                return (object) $item;
            });

            // Index dataRows by tanggal dan outlet
            $indexedData = [];
            foreach ($dataRows as $row) {
                $key = $row->tanggal;
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

            // Hitung lembur untuk setiap baris menggunakan perhitungan sederhana
            foreach ($dataRows as $row) {
                if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                    // Ambil jam shift dari shift data
                    $shiftStart = $shiftData->time_start ?? '08:00:00'; // Default jika tidak ada
                    $shiftEnd = $shiftData->time_end ?? '17:00:00'; // Default jika tidak ada
                    
                    // Gunakan perhitungan lembur yang sederhana dan aman
                    $row->lembur = $this->calculateSimpleOvertime($row->jam_keluar, $shiftEnd);
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
                                    $telat = $this->calculateLateness($jam_masuk, $shift->time_start, $row->is_cross_day ?? false);
                                }
                                
                                // Untuk cross-day, tidak ada telat tambahan karena ini kelanjutan shift malam sebelumnya
                                if (!($row->is_cross_day ?? false)) {
                                    // Tambahkan telat jika checkout terakhir kurang dari jam pulang/end shift
                                    if ($shift && $shift->time_end && $jam_keluar) {
                                        $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                                        $scanOutDateTime = $row->jam_keluar;
                                        
                                        $end = strtotime($shiftEndDateTime);
                                        $keluar = strtotime($scanOutDateTime);
                                        $diff = $end - $keluar; // Selisih waktu shift end - scan out
                                        
                                        // Jika checkout lebih awal dari shift end, tambahkan ke telat
                                        if ($diff > 0) {
                                            $telat += round($diff/60); // Konversi detik ke menit
                                        }
                                    }
                                }
                                if ($shift && $shift->time_end && $jam_keluar) {
                                    // Gunakan smart overtime calculation
                                    $lembur = $this->calculateSimpleOvertime($jam_keluar, $shift->time_end);
                                    // Round down (bulatkan ke bawah)
                                    $lembur = floor($lembur);
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
                        
                        // Get overtime from Extra Off system for this date (tetap ambil meskipun is_off)
                        $extraOffOvertime = $this->getExtraOffOvertimeHoursForDate($rowUserId, $tanggal);
                        // Round down total lembur (bulatkan ke bawah)
                        $totalLembur = floor($lembur + $extraOffOvertime);
                        
                        $summary['total_telat'] += $telat;
                        $summary['total_lembur'] += $totalLembur;
                        
                        // Check if user has approved absent for this date
                        $approvedAbsent = null;
                        $is_approved_absent = false;
                        $approved_absent_name = null;
                        if (isset($approvedAbsents[$rowUserId][$tanggal])) {
                            $approvedAbsent = $approvedAbsents[$rowUserId][$tanggal];
                            $is_approved_absent = true;
                            $approved_absent_name = $approvedAbsent['leave_type_name'];
                        }
                        
                        // Deteksi attendance tanpa checkout
                        $has_no_checkout = false;
                        if (!$is_off && !$is_holiday && !$is_approved_absent && $jam_masuk && !$jam_keluar) {
                            $has_no_checkout = true;
                        }
                        
                        $rows->push((object)[
                            'tanggal' => $tanggal,
                            'user_id' => $rowUserId,
                            'nama_lengkap' => $rowNama,
                            'jabatan' => null, // Will be filled later from jabatanMap
                            'jam_masuk' => $jam_masuk,
                            'jam_keluar' => $jam_keluar,
                            'total_masuk' => $row->total_masuk,
                            'total_keluar' => $row->total_keluar,
                            'telat' => $telat,
                            'lembur' => $lembur,
                            'extra_off_overtime' => $extraOffOvertime,
                            'total_lembur' => $totalLembur,
                            'is_off' => $is_off,
                            'shift_name' => $shift_name,
                            'is_holiday' => $is_holiday,
                            'holiday_name' => $holiday_name,
                            'is_cross_day' => $row->is_cross_day ?? false,
                            'approved_absent' => $approvedAbsent,
                            'is_approved_absent' => $is_approved_absent,
                            'approved_absent_name' => $approved_absent_name,
                            'has_no_checkout' => $has_no_checkout,
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
                    
                    // Get overtime from Extra Off system for this date (tetap ambil meskipun is_off)
                    $extraOffOvertime = $this->getExtraOffOvertimeHoursForDate($rowUserId, $tanggal);
                    // Round down total lembur (bulatkan ke bawah)
                    $totalLembur = floor($lembur + $extraOffOvertime);
                    
                    $summary['total_lembur'] += $totalLembur;
                    
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
                        'jabatan' => null, // Will be filled later from jabatanMap
                        'outlet_id' => null,
                        'nama_outlet' => null,
                        'jam_masuk' => null,
                        'jam_keluar' => null,
                        'total_masuk' => 0,
                        'total_keluar' => 0,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'extra_off_overtime' => $extraOffOvertime,
                        'total_lembur' => $totalLembur,
                        'is_off' => $is_off,
                        'shift_name' => $shift_name,
                        'is_holiday' => $is_holiday,
                        'holiday_name' => $holiday_name,
                        'is_cross_day' => false,
                        'approved_absent' => $approvedAbsent,
                        'is_approved_absent' => $is_approved_absent,
                        'approved_absent_name' => $approved_absent_name,
                        'has_no_checkout' => false, // Tidak ada data attendance sama sekali
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

        // Get jabatan for all unique user_ids
        $userIds = $rows->pluck('user_id')->unique()->filter()->toArray();
        $jabatanMap = [];
        if (!empty($userIds)) {
            $jabatanData = DB::table('users as u')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->whereIn('u.id', $userIds)
                ->select('u.id', 'j.nama_jabatan as jabatan')
                ->get()
                ->keyBy('id');
            
            foreach ($jabatanData as $id => $data) {
                $jabatanMap[$id] = $data->jabatan ?? '-';
            }
        }

        // Add jabatan to each row
        $rows = $rows->map(function($row) use ($jabatanMap) {
            $row->jabatan = $jabatanMap[$row->user_id] ?? '-';
            return $row;
        });

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
                    // Cari scan keluar di hari yang sama
                    $sameDayOuts = $outScans->where('scan_date', '>', $jamIn);
                    
                    // Cari scan keluar di hari berikutnya (cross-day)
                    $nextDayKey = $data['id_outlet'] . '_' . $nextDay;
                    $nextDayOuts = collect();
                    
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                    }
                    
                    // Tentukan OUT scan yang paling masuk akal - SAMA DENGAN AttendanceController
                    if ($sameDayOuts->isNotEmpty() && $nextDayOuts->isNotEmpty()) {
                        // Ada both same-day dan cross-day OUT scan
                        $lastSameDayOut = $sameDayOuts->last()['scan_date'];
                        $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                        
                        // Cek durasi same-day OUT
                        $sameDayDuration = strtotime($lastSameDayOut) - strtotime($jamIn);
                        $outHour = (int)date('H', strtotime($firstNextDayOut));
                        
                        // Prioritas cross-day jika:
                        // 1. Same-day OUT terlalu pendek (< 5 jam) ATAU
                        // 2. Cross-day OUT di pagi sangat awal (00:00-06:00)
                        if ($sameDayDuration < 18000 || ($outHour >= 0 && $outHour <= 6)) {
                            $jamOut = $firstNextDayOut;
                            $totalOut = 1;
                        } else {
                            $jamOut = $lastSameDayOut;
                        }
                    } elseif ($sameDayOuts->isNotEmpty()) {
                        // Hanya ada same-day OUT scan
                        $jamOut = $sameDayOuts->last()['scan_date'];
                    } elseif ($nextDayOuts->isNotEmpty()) {
                        // Hanya ada cross-day OUT scan
                        $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                        $outHour = (int)date('H', strtotime($firstNextDayOut));
                        
                        // Untuk cross-day, hanya gunakan jika di pagi sangat awal (00:00-12:00)
                        if ($outHour >= 0 && $outHour <= 12) {
                            $jamOut = $firstNextDayOut;
                            $totalOut = 1;
                        }
                    }
                }
                
                // Deteksi attendance tanpa checkout
                $has_no_checkout = false;
                if ($jamIn && !$jamOut) {
                    $has_no_checkout = true;
                }
                
                
                $result[] = [
                    'id_outlet' => $data['id_outlet'],
                    'nama_outlet' => $data['nama_outlet'],
                    'jam_in' => $jamIn ? date('H:i:s', strtotime($jamIn)) : null,
                    'jam_out' => $jamOut ? date('H:i:s', strtotime($jamOut)) : null,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'has_no_checkout' => $has_no_checkout,
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
        $summary = [ 'total_telat' => 0, 'total_lembur' => 0 ];
        if (!empty($outletId) || !empty($divisionId) || !empty($search) || !empty($bulan) || !empty($tahun)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

            // Get approved absent requests for the date range
            $approvedAbsents = $this->getApprovedAbsentRequests($start, $end);

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
                    'u.nama_lengkap'
                )
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            if (!empty($outletId)) {
                $sub->where('u.id_outlet', $outletId);
            }
            if (!empty($divisionId)) {
                $sub->where('u.division_id', $divisionId);
            }
            if (!empty($search)) {
                $sub->where('u.nama_lengkap', '=', $search);
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
            
            // Step 2: Proses setiap kelompok dengan smart cross-day processing
            $finalData = [];
            foreach ($processedData as $key => $data) {
                $result = $this->processSmartCrossDayAttendance($data, $processedData);
                // Tambahkan outlet info untuk exportExcel
                $result['id_outlet'] = $outletId ?? 1;
                $finalData[] = $result;
            }
            
            $dataRows = collect($finalData)->map(function($item) {
                return (object) $item;
            });

            // Index dataRows by tanggal
            $indexedData = [];
            foreach ($dataRows as $row) {
                $key = $row->tanggal;
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

            // Hitung lembur untuk setiap baris menggunakan perhitungan sederhana
            foreach ($dataRows as $row) {
                if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                    // Ambil jam shift dari shift data
                    $shiftStart = $shiftData->time_start ?? '08:00:00'; // Default jika tidak ada
                    $shiftEnd = $shiftData->time_end ?? '17:00:00'; // Default jika tidak ada
                    
                    // Gunakan perhitungan lembur yang sederhana dan aman
                    $row->lembur = $this->calculateSimpleOvertime($row->jam_keluar, $shiftEnd);
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
                                    $telat = $this->calculateLateness($jam_masuk, $shift->time_start, $row->is_cross_day ?? false);
                                }
                                
                                // Untuk cross-day, tidak ada telat tambahan karena ini kelanjutan shift malam sebelumnya
                                if (!($row->is_cross_day ?? false)) {
                                    // Tambahkan telat jika checkout terakhir kurang dari jam pulang/end shift
                                    if ($shift && $shift->time_end && $jam_keluar) {
                                        $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                                        $scanOutDateTime = $row->jam_keluar;
                                        
                                        $end = strtotime($shiftEndDateTime);
                                        $keluar = strtotime($scanOutDateTime);
                                        $diff = $end - $keluar; // Selisih waktu shift end - scan out
                                        
                                        // Jika checkout lebih awal dari shift end, tambahkan ke telat
                                        if ($diff > 0) {
                                            $telat += round($diff/60); // Konversi detik ke menit
                                        }
                                    }
                                }
                                
                                if ($shift && $shift->time_end && $jam_keluar) {
                                    // Gunakan smart overtime calculation
                                    $lembur = $this->calculateSimpleOvertime($jam_keluar, $shift->time_end);
                                    
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
                        // Deteksi attendance tanpa checkout
                        $has_no_checkout = false;
                        if (!$is_off && !$is_holiday && $jam_masuk && !$jam_keluar) {
                            $has_no_checkout = true;
                        }
                        
                    $rows->push((object)[
                        'tanggal' => $tanggal,
                        'user_id' => $rowUserId,
                        'nama_lengkap' => $rowNama,
                        'outlet_id' => null,
                        'nama_outlet' => null,
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
                        'has_no_checkout' => $has_no_checkout,
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
                        
                        // Cek apakah ada approved absent untuk tanggal ini
                        $isApprovedAbsent = false;
                        if (isset($approvedAbsents[$rowUserId][$tanggal])) {
                            $isApprovedAbsent = true;
                            $approvedAbsentData = $approvedAbsents[$rowUserId][$tanggal];
                            $detail = 'Approved Absent: ' . ($approvedAbsentData['leave_type_name'] ?? 'Unknown');
                        }
                        
                        if (!$is_off && !$isApprovedAbsent) {
                            $telat = 0; // Tidak ada scan = tidak ada telat
                            $lembur = 0;
                        } else {
                            $telat = 0;
                            $lembur = 0;
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
                        'has_no_checkout' => false, // Tidak ada data attendance sama sekali
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

        // ✅ OPTIMIZATION: Only load data if filters are provided
        $hasFilters = !empty($outletId) || !empty($divisionId) || !empty($bulan) || !empty($tahun);
        
        if (!$hasFilters) {
            // Return empty data with dropdowns only
            $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
            $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();
            
            return Inertia::render('AttendanceReport/OutletSummary', [
                'rows' => [],
                'outlets' => $outlets,
                'divisions' => $divisions,
                'filter' => [
                    'outlet_id' => $outletId,
                    'division_id' => $divisionId,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                ],
                'period' => null,
            ]);
        }

        $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
        $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

        // Ambil raw scan data - FIXED: Grouping berdasarkan outlet tempat user bekerja
        $sub = DB::table('att_log as a')
            ->join('user_pins as up', 'a.pin', '=', 'up.pin')
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->join('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet') // ✅ FIX: Group by user's outlet
            ->select(
                'a.scan_date',
                'a.inoutmode',
                'u.id as user_id',
                'u.nama_lengkap',
                'u.id_outlet', // ✅ FIX: Add user's outlet for grouping
                'o.nama_outlet' // ✅ FIX: Add outlet name for display
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
                    'outlet_id' => $scan->id_outlet, // ✅ FIX: Use user's outlet
                    'nama_outlet' => $scan->nama_outlet, // ✅ FIX: Use user's outlet name
                    'scans' => []
                ];
            }
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }

        // Step 2: tentukan jam_masuk/jam_keluar dengan smart cross-day processing
        $finalData = [];
        foreach ($processedData as $key => $data) {
            $result = $this->processSmartCrossDayAttendance($data, $processedData);
            // Tambahkan outlet info untuk outletSummary
            $result['outlet_id'] = $data['outlet_id'];
            $result['nama_outlet'] = $data['nama_outlet'];
            $finalData[] = $result;
        }

        $dataRows = collect($finalData)->map(function($item) {
            return (object) $item;
        });

        // Index data by tanggal & outlet - FIXED: Use user's outlet for grouping
        $indexedData = [];
        foreach ($dataRows as $row) {
            $key = $row->tanggal . '_' . $row->outlet_id; // ✅ FIX: Use user's outlet
            if (!isset($indexedData[$key])) $indexedData[$key] = [];
            $indexedData[$key][] = $row;
        }

        // Build period tanggal
        $period = [];
        $dt = new \DateTime($start);
        $dtEnd = new \DateTime($end);
        while ($dt <= $dtEnd) { $period[] = $dt->format('Y-m-d'); $dt->modify('+1 day'); }

        // Ambil seluruh outlet untuk flattening
        $allOutlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

        // Flatten sesuai tanggal x outlet - FIXED: Use user's outlet
        $flatten = [];
        foreach ($period as $tgl) {
            foreach ($allOutlets as $o) {
                $key = $tgl . '_' . $o->id_outlet;
                if (isset($indexedData[$key])) {
                    foreach ($indexedData[$key] as $row) { 
                        $flatten[] = (object)$row; 
                    }
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
                            $telat = $this->calculateLateness($jam_masuk, $shift->time_start, $row->is_cross_day ?? false);
                        }
                        
                        // FIXED: Tambahkan telat jika checkout lebih awal dari shift end
                        if ($shift && $shift->time_end && $jam_keluar) {
                            // Hitung telat dari early checkout (dengan pengecekan cross-day)
                            $earlyCheckoutTelat = $this->calculateEarlyCheckoutLateness($jam_keluar, $shift->time_end, $row->is_cross_day ?? false);
                            $telat += $earlyCheckoutTelat;
                            
                            // Gunakan smart overtime calculation - FIXED to use new logic
                            $lembur = $this->calculateSimpleOvertime($jam_keluar, $shift->time_end);
                        }
                    } else { $jam_masuk = null; $jam_keluar = null; $telat = 0; $lembur = 0; }

                    // Deteksi attendance tanpa checkout
                    $has_no_checkout = false;
                    if (!$is_off && !$holidays->has($tanggal) && $jam_masuk && !$jam_keluar) {
                        $has_no_checkout = true;
                    }
                    
                    $rows->push((object) [
                        'tanggal' => $tanggal,
                        'outlet_id' => $row->outlet_id, // ✅ FIX: Include user's outlet
                        'nama_outlet' => $row->nama_outlet, // ✅ FIX: Include user's outlet name
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_off' => $is_off,
                        'is_holiday' => $holidays->has($tanggal),
                        'has_no_checkout' => $has_no_checkout,
                    ]);
                }
            }
        }

        // ✅ FIX: Group by user's outlet (not scan outlet)
        $byOutlet = $rows->groupBy('outlet_id')->map(function($g) {
            $first = $g->first();
            $nonOffDays = $g->where('is_off', false);
            $totalLembur = $nonOffDays->sum('lembur');
            $totalTelat = $nonOffDays->sum('telat');
            
            // Calculate unique employees (users) in this outlet
            $uniqueEmployees = $nonOffDays->pluck('user_id')->unique()->count();
            $averageLemburPerPerson = $uniqueEmployees > 0 ? round($totalLembur / $uniqueEmployees, 2) : 0;
            
            return [
                'outlet_id' => $first->outlet_id ?? null,
                'nama_outlet' => $first->nama_outlet ?? '-',
                'total_telat' => $totalTelat,
                'total_lembur' => $totalLembur,
                'average_lembur_per_person' => $averageLemburPerPerson,
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
     * Logika sama dengan my attendance: off = tidak ada shift DAN tidak ada attendance
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
            ->whereIn('us.tanggal', $period)
            ->select('us.tanggal', 's.shift_name', 'us.shift_id')
            ->get()
            ->keyBy('tanggal');
        
        // Get attendance data for this user in this period - SAMA dengan report attendance
        $attendance = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->whereIn(DB::raw('DATE(a.scan_date)'), $period)
            ->selectRaw('DATE(a.scan_date) as tanggal')
            ->distinct()
            ->pluck('tanggal')
            ->toArray();
        
        // Count off days: tidak ada shift DAN tidak ada attendance (sama dengan my attendance)
        $offDays = 0;
        $today = date('Y-m-d');
        $offDates = [];
        
        foreach ($period as $date) {
            $shift = $shifts->get($date);
            $hasAttendance = in_array($date, $attendance);
            $isPastOrToday = $date <= $today; // Hanya hitung hari yang sudah lewat
            
            // Off day criteria (berdasarkan report attendance):
            // 1. Tidak ada shift (TIDAK ada jadwal shift) ATAU
            // 2. Ada shift tapi tidak ada attendance (shift tapi tidak hadir)
            // 3. Hari sudah lewat atau hari ini (bukan masa depan)
            $hasShift = $shift && $shift->shift_id;
            $isOff = (!$hasShift || ($hasShift && !$hasAttendance)) && $isPastOrToday;
            
            if ($isOff) {
                $offDays++;
                $offDates[] = $date;
            }
        }
        
        // Debug logging untuk troubleshooting
        \Log::info('Off Days Calculation (Fixed)', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'today' => $today,
            'total_period_days' => count($period),
            'shifts_count' => $shifts->count(),
            'attendance_count' => count($attendance),
            'off_days' => $offDays,
            'off_dates' => $offDates,
            'period' => $period,
            'logic' => 'tidak ada shift DAN tidak ada attendance (sama dengan my attendance)'
        ]);
        
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
        // Get all leave types
        $leaveTypes = DB::table('leave_types')
            ->where('is_active', 1)
            ->select('id', 'name')
            ->get();
        
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
                'leave_types.id as leave_type_id',
                'leave_types.name as leave_type_name'
            ])
            ->get();
        
        // Initialize result with all leave types
        $result = [];
        foreach ($leaveTypes as $leaveType) {
            $result[strtolower(str_replace(' ', '_', $leaveType->name)) . '_days'] = 0;
        }
        
        // Group by leave type and calculate days
        $leaveDataByType = [];
        foreach ($approvedAbsents as $absent) {
            $leaveTypeId = $absent->leave_type_id;
            $leaveTypeName = $absent->leave_type_name;
            
            // Calculate days between date_from and date_to
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            $daysCount = $fromDate->diff($toDate)->days + 1;
            
            if (!isset($leaveDataByType[$leaveTypeId])) {
                $leaveDataByType[$leaveTypeId] = [
                    'name' => $leaveTypeName,
                    'days' => 0
                ];
            }
            $leaveDataByType[$leaveTypeId]['days'] += $daysCount;
        }
        
        // Map to result format
        foreach ($leaveDataByType as $leaveTypeId => $data) {
            $key = strtolower(str_replace(' ', '_', $data['name'])) . '_days';
            $result[$key] = $data['days'];
        }
        
        // Keep legacy fields for backward compatibility
        $result['extra_off_days'] = $result['extra_off_days'] ?? 0;
        
        return $result;
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
                
                if ($sameDayOuts->isNotEmpty()) {
                    // Ada scan keluar di hari yang sama
                    $jamKeluar = $sameDayOuts->last()['scan_date'];
                    $isCrossDay = false;
                } else {
                    // Cari scan keluar di hari berikutnya (cross-day)
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $nextDay;
                    
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                        
                        if ($nextDayOuts->isNotEmpty()) {
                            $jamKeluar = $nextDayOuts->first()['scan_date'];
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
        
        \Log::info('Max execution time set to: ' . $maxExecutionTime . ' seconds');
        
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }

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
                
                // ✅ FIX: Query data absensi - SAMA PERSIS dengan report attendance (logika absensi sama)
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

                // Apply filters - Filter outlet dan divisi untuk memfilter karyawan
                if (!empty($outletId)) {
                    $sub->where('u.id_outlet', $outletId);
                }
                
                if (!empty($divisionId)) {
                    $sub->where('u.division_id', $divisionId);
                }

                // Gunakan chunk untuk mencegah memory overflow
                $sub->orderBy('a.scan_date')->chunk($chunkSize, function($chunk) use (&$rawData) {
                    $rawData = $rawData->merge($chunk);
                });


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
                
                
                // Clear raw data untuk menghemat memory
                $rawData = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                // Step 2: Proses setiap kelompok dengan smart cross-day processing
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
                    
                    // Gunakan smart cross-day processing yang sama dengan report utama
                    $result = $this->processSmartCrossDayAttendance($data, $processedData);
                    // Tambahkan division_id untuk employee summary
                    $result['division_id'] = $data['division_id'];
                    $finalData[] = $result;
                }
                

                $dataRows = collect($finalData)->map(function($item) {
                    return (object) $item;
                });
                
                // Clear processed data untuk menghemat memory
                $processedData = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                // Ambil data shift untuk perhitungan lembur - gunakan batch query untuk efisiensi
                $allShiftData = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                    ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->tanggal;
                    });

                // Hitung lembur untuk setiap baris
                $overtimeCount = 0;
                foreach ($dataRows as $index => $row) {
                    if ($index % 100 == 0) {
                        // Force garbage collection
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                    
                    // Ambil shift data yang sesuai dengan user dan tanggal
                    $shiftKey = $row->user_id . '_' . $row->tanggal;
                    $shiftData = $allShiftData->get($shiftKey, collect())->first();
                    
                    if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                        // REFACTOR: Gunakan logika yang sama dengan Report Attendance
                        $shiftStart = $shiftData->time_start ?? '08:00:00';
                        $shiftEnd = $shiftData->time_end ?? '17:00:00';
                        
                        // ✅ FIX: Untuk cross-day, shift end tetap di hari yang sama (bukan hari berikutnya)
                        // Karena jam_keluar sudah berisi tanggal lengkap (termasuk hari berikutnya jika cross-day)
                        $shiftEndDateTime = date('Y-m-d', strtotime($row->tanggal)) . ' ' . $shiftEnd;
                        $scanOutDateTime = $row->jam_keluar;
                        
                        // Hitung selisih waktu
                        $shiftEndTime = strtotime($shiftEndDateTime);
                        $scanOutTime = strtotime($scanOutDateTime);
                        
                        
                        // Gunakan smart overtime calculation
                        $row->lembur = $this->calculateSimpleOvertime($row->jam_keluar, $shiftEnd);
                        if ($row->lembur > 0) {
                            $overtimeCount++;
                            
                        } else {
                            $row->lembur = 0;
                        }
                    } else {
                        $row->lembur = 0;
                    }
                }
                

                // Ambil semua tanggal libur dalam periode
                $holidays = DB::table('tbl_kalender_perusahaan')
                    ->whereBetween('tgl_libur', [$start, $end])
                    ->pluck('keterangan', 'tgl_libur');

                // Build rows for each tanggal in period
                $rows = collect();
                $rowsWithShift = 0;
                
                // OPTIMASI: Batch query untuk shift data untuk mencegah N+1 query problem
                $allShiftData = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                    // ✅ FIX: Remove outlet filter - sama seperti report attendance
                    ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->tanggal;
                    });
                
                
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
                    
                    $jam_masuk = $row->jam_masuk ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                    $jam_keluar = $row->jam_keluar ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                    $telat = 0;
                    $lembur = 0;
                    
                    // OPTIMASI: Gunakan cached shift data instead of individual query
                    $shiftKey = $row->user_id . '_' . $row->tanggal;
                    $shift = $allShiftData->get($shiftKey, collect())->first();

                    if ($shift) {
                        $rowsWithShift++;
                        
                        // Hitung telat dan lembur berdasarkan shift
                        if ($shift->time_start && $jam_masuk) {
                            $telat = $this->calculateLateness($jam_masuk, $shift->time_start, $row->is_cross_day ?? false);
                        }
                        
                        // FIXED: Tambahkan telat jika checkout lebih awal dari shift end
                        if ($shift->time_end && $jam_keluar) {
                            // Hitung telat dari early checkout (dengan pengecekan cross-day)
                            $earlyCheckoutTelat = $this->calculateEarlyCheckoutLateness($jam_keluar, $shift->time_end, $row->is_cross_day ?? false);
                            $telat += $earlyCheckoutTelat;
                            
                            // Gunakan perhitungan lembur yang konsisten
                            $lembur = $this->calculateSimpleOvertime($jam_keluar, $shift->time_end);
                        }
                    }


                    // Deteksi attendance tanpa checkout
                    $has_no_checkout = false;
                    if ($row->jam_masuk && !$row->jam_keluar) {
                        $has_no_checkout = true;
                    }
                    
                    $rows->push((object)[
                        'tanggal' => $row->tanggal,
                        'user_id' => $row->user_id,
                        'nama_lengkap' => $row->nama_lengkap,
                        'division_id' => $row->division_id,
                        'jam_masuk' => $row->jam_masuk, // Kirim datetime lengkap, bukan format time
                        'jam_keluar' => $row->jam_keluar, // Kirim datetime lengkap, bukan format time
                        'total_masuk' => $row->total_masuk,
                        'total_keluar' => $row->total_keluar,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_cross_day' => $row->is_cross_day,
                        'shift_start' => $shift->time_start ?? null,
                        'shift_end' => $shift->time_end ?? null,
                        'has_no_checkout' => $has_no_checkout,
                    ]);
                }
                
                $totalRowBuildingTime = microtime(true) - $startRowBuildingTime;
                \Log::info('Rows with shift data: ' . $rowsWithShift);
                \Log::info('Total time for building rows: ' . round($totalRowBuildingTime, 2) . ' seconds');
                \Log::info('Average time per row: ' . round($totalRowBuildingTime / $dataRows->count(), 4) . ' seconds');
                
                // Clear data rows dan shift data untuk menghemat memory
                $dataRows = null;
                $allShiftData = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                // Group by employee and calculate summary
                
                // Tambah progress logging
                $totalEmployees = $rows->groupBy('user_id')->count();
                
                // Get all user data (NIK and jabatan) at once to avoid N+1 queries
                $userIds = $rows->pluck('user_id')->unique()->toArray();
                
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
                        $offDays = $this->calculateOffDays($firstRow->user_id, null, $start, $end);
                        
                        // Calculate PH days (Public Holiday compensations)
                        $phData = $this->calculatePHData($firstRow->user_id, $start, $end);
                        
                        // Calculate leave data (cuti, extra off, sakit)
                        $leaveData = $this->calculateLeaveData($firstRow->user_id, $start, $end);
                        
                        // Calculate alpa days (days with shift but no attendance and no absent request)
                        $alpaDays = $this->calculateAlpaDays($firstRow->user_id, null, $start, $end);
                        
                        // SAFETY: Reset lembur values that are too large
                        $employeeRows->transform(function($row) {
                            if ($row->lembur > 12) {
                                $row->lembur = 0;
                            }
                            return $row;
                        });
                        
                        // FIXED: Calculate Extra Off overtime once to prevent accumulation
                        $extraOffOvertimeTotal = floor($this->getExtraOffOvertimeHours($firstRow->user_id, $start, $end));
                        

                        // Siapkan data detail absensi harian untuk expandable table
                        $dailyAttendance = $employeeRows->map(function($row) use ($firstRow) {
                            // Get overtime from Extra Off system for this specific date
                            $extraOffOvertimeForDate = $this->getExtraOffOvertimeHoursForDate($firstRow->user_id, $row->tanggal);
                            
                            // Round down lembur biasa (bulatkan ke bawah)
                            $lemburRounded = floor($row->lembur ?? 0);
                            // Round down total lembur (bulatkan ke bawah)
                            $totalLemburRounded = floor($lemburRounded + $extraOffOvertimeForDate);
                            
                            return [
                                'tanggal' => $row->tanggal,
                                'jam_masuk' => $row->jam_masuk,
                                'jam_keluar' => $row->jam_keluar,
                                'telat' => $row->telat,
                                'lembur' => $lemburRounded,
                                'extra_off_overtime' => $extraOffOvertimeForDate, // Overtime from Extra Off system for this date
                                'total_lembur' => $totalLemburRounded, // Combined overtime (rounded down)
                                'is_cross_day' => $row->is_cross_day ?? false,
                                'is_off' => $row->is_off ?? false,
                                'is_holiday' => $row->is_holiday ?? false,
                                'holiday_name' => $row->holiday_name ?? null,
                                'shift_start' => $row->shift_start ?? null,
                                'shift_end' => $row->shift_end ?? null,
                            ];
                        })->sortBy('tanggal')->values();

                        // Round down semua lembur (bulatkan ke bawah)
                        $totalLemburRegular = floor($employeeRows->sum('lembur'));
                        $totalLemburWithExtraOff = floor($totalLemburRegular + $extraOffOvertimeTotal);
                        
                        $result = [
                            'user_id' => $firstRow->user_id,
                            'nik' => $userData->nik ?? '-',
                            'nama_lengkap' => $firstRow->nama_lengkap,
                            'jabatan' => $userData->jabatan ?? '-',
                            'division_id' => $firstRow->division_id,
                            // ✅ FIX: Remove outlet info - sama seperti report attendance
                            'hari_kerja' => $employeeRows->count(), // Jumlah hari yang bekerja
                            'off_days' => $offDays, // Jumlah hari tanpa shift
                            'ph_days' => $phData['days'], // Jumlah hari libur nasional dengan kompensasi
                            'ph_bonus' => $phData['bonus'], // Total bonus PH yang diterima
                            'extra_off_days' => $leaveData['extra_off_days'] ?? 0, // Jumlah hari extra off
                            'alpa_days' => $alpaDays, // Jumlah hari alpa
                            'ot_full_days' => $totalLemburWithExtraOff, // Total lembur (OT Full) + Extra Off Overtime (rounded down)
                            'total_telat' => $employeeRows->sum('telat'),
                            'total_lembur' => $totalLemburWithExtraOff, // Total lembur termasuk extra off overtime (rounded down)
                            'total_days' => $this->calculateTotalDaysInPeriod($start, $end), // Total hari dalam periode
                            // Data detail untuk expandable table
                            'daily_attendance' => $dailyAttendance,
                        ];
                        
                        // Add dynamic leave data
                        foreach ($leaveData as $key => $value) {
                            if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                                $result[$key] = $value;
                            }
                        }
                        
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
                            // ✅ FIX: Remove outlet info - sama seperti report attendance
                            'hari_kerja' => 0,
                            'off_days' => isset($start) && isset($end) ? $this->calculateOffDays($employeeRows->first()->user_id ?? 0, null, $start, $end) : 0,
                            'ph_days' => isset($start) && isset($end) ? $this->calculatePHData($employeeRows->first()->user_id ?? 0, $start, $end)['days'] : 0,
                            'ph_bonus' => isset($start) && isset($end) ? $this->calculatePHData($employeeRows->first()->user_id ?? 0, $start, $end)['bonus'] : 0,
                            'extra_off_days' => isset($start) && isset($end) ? $this->calculateLeaveData($employeeRows->first()->user_id ?? 0, $start, $end)['extra_off_days'] : 0,
                            'alpa_days' => isset($start) && isset($end) ? $this->calculateAlpaDays($employeeRows->first()->user_id ?? 0, null, $start, $end) : 0,
                            'ot_full_days' => 0,
                            'total_telat' => 0,
                            'total_lembur' => 0,
                            'total_days' => isset($start) && isset($end) ? $this->calculateTotalDaysInPeriod($start, $end) : 0,
                        ];
                        
                        // Add dynamic leave data for error case
                        if (isset($start) && isset($end)) {
                            $errorLeaveData = $this->calculateLeaveData($employeeRows->first()->user_id ?? 0, $start, $end);
                            foreach ($errorLeaveData as $key => $value) {
                                if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                                    $errorResult[$key] = $value;
                                }
                            }
                        }
                        
                        $employeeSummary->push($errorResult);
                        $processedEmployees++;
                    }
                }
                
                // Sort employee summary by nama_lengkap
                $employeeSummary = $employeeSummary->sortBy('nama_lengkap')->values();
                
                \Log::info('Successfully processed employees: ' . $processedEmployees);
                
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
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();


        // Calculate summary statistics (round down total lembur)
        $totalLemburSum = $employeeSummary ? $employeeSummary->sum('total_lembur') : 0;
        $summary = [
            'total_employees' => $employeeSummary ? $employeeSummary->count() : 0,
            'total_lembur' => floor($totalLemburSum), // Round down (bulatkan ke bawah)
            'total_telat' => $employeeSummary ? $employeeSummary->sum('total_telat') : 0,
            'avg_lembur_per_employee' => $employeeSummary && $employeeSummary->count() > 0 ? 
                floor($totalLemburSum / $employeeSummary->count()) : 0, // Round down average
            'avg_telat_per_employee' => $employeeSummary && $employeeSummary->count() > 0 ? 
                round($employeeSummary->sum('total_telat') / $employeeSummary->count(), 2) : 0,
        ];

        // Get leave types for dynamic columns
        $leaveTypes = DB::table('leave_types')
            ->where('is_active', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('AttendanceReport/EmployeeSummary', [
            'rows' => $employeeSummary ?? collect(),
            'outlets' => $outlets,
            'divisions' => $divisions,
            'leaveTypes' => $leaveTypes,
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

        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for export', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }

        // Set timeout untuk mencegah loading terlalu lama
        set_time_limit(300); // 5 menit
        
        
        if (!empty($outletId) || !empty($divisionId) || !empty($bulan) || !empty($tahun)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));


            try {
                // Query data absensi dengan optimasi
                
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

                // Apply filters - SAMA PERSIS DENGAN METHOD employeeSummary
                if (!empty($outletId)) {
                    $sub->where('u.id_outlet', $outletId);
                }
                
                if (!empty($divisionId)) {
                    $sub->where('u.division_id', $divisionId);
                }

                $sub->orderBy('a.scan_date')->chunk($chunkSize, function($chunk) use (&$rawData) {
                    $rawData = $rawData->merge($chunk);
                });


                // Proses data dengan smart cross-day processing
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
                    $result = $this->processSmartCrossDayAttendance($data, $processedData);
                    // Tambahkan division_id untuk export employee summary
                    $result['division_id'] = $data['division_id'];
                    $finalData[] = $result;
                }

                $dataRows = collect($finalData)->map(function($item) {
                    return (object) $item;
                });

                // Ambil data shift untuk perhitungan lembur - gunakan batch query untuk efisiensi
                $allShiftData = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                    ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->tanggal;
                    });

                // Hitung lembur untuk setiap baris
                foreach ($dataRows as $row) {
                    // Ambil shift data yang sesuai dengan user dan tanggal
                    $shiftKey = $row->user_id . '_' . $row->tanggal;
                    $shiftData = $allShiftData->get($shiftKey, collect())->first();
                    
                    if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                        $shiftStart = $shiftData->time_start ?? '08:00:00';
                        $shiftEnd = $shiftData->time_end ?? '17:00:00';
                        
                        // ✅ FIX: Untuk cross-day, shift end tetap di hari yang sama (bukan hari berikutnya)
                        // Karena jam_keluar sudah berisi tanggal lengkap (termasuk hari berikutnya jika cross-day)
                        $shiftEndDateTime = date('Y-m-d', strtotime($row->tanggal)) . ' ' . $shiftEnd;
                        $scanOutDateTime = $row->jam_keluar;
                        
                        $shiftEndTime = strtotime($shiftEndDateTime);
                        $scanOutTime = strtotime($scanOutDateTime);
                        
                        // Gunakan smart overtime calculation
                        $row->lembur = $this->calculateSimpleOvertime($row->jam_keluar, $shiftEnd);
                    } else {
                        $row->lembur = 0;
                    }
                }

                // Build rows dengan shift data
                $rows = collect();
                $allShiftData = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                    // ✅ FIX: Remove outlet filter - sama seperti report attendance
                    ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->tanggal;
                    });

                foreach ($dataRows as $row) {
                    // Fix: Pastikan data jam_masuk dan jam_keluar tidak null
                    $jam_masuk = !empty($row->jam_masuk) ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                    $jam_keluar = !empty($row->jam_keluar) ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                    $telat = 0;
                    $lembur = 0;
                    
                    $shiftKey = $row->user_id . '_' . $row->tanggal;
                    $shift = $allShiftData->get($shiftKey, collect())->first();
                    
                    // Debug: Log jika data kosong
                    if (empty($jam_masuk) && empty($jam_keluar)) {
                        \Log::warning('Empty attendance data for user:', [
                            'user_id' => $row->user_id,
                            'tanggal' => $row->tanggal,
                            // ✅ FIX: Remove outlet info - sama seperti report attendance // ✅ FIX: Use outlet where user absen
                            'raw_jam_masuk' => $row->jam_masuk ?? 'null',
                            'raw_jam_keluar' => $row->jam_keluar ?? 'null'
                        ]);
                    }

                    if ($shift) {
                        if ($shift->time_start && $jam_masuk) {
                            $telat = $this->calculateLateness($jam_masuk, $shift->time_start, $row->is_cross_day ?? false);
                        }
                        
                        // FIXED: Tambahkan telat jika checkout lebih awal dari shift end
                        if ($shift->time_end && $jam_keluar) {
                            // Hitung telat dari early checkout (dengan pengecekan cross-day)
                            $earlyCheckoutTelat = $this->calculateEarlyCheckoutLateness($jam_keluar, $shift->time_end, $row->is_cross_day ?? false);
                            $telat += $earlyCheckoutTelat;
                            
                            // Gunakan perhitungan lembur yang konsisten
                            $lembur = $this->calculateSimpleOvertime($jam_keluar, $shift->time_end);
                        }
                    }


                    // Deteksi attendance tanpa checkout
                    $has_no_checkout = false;
                    if ($row->jam_masuk && !$row->jam_keluar) {
                        $has_no_checkout = true;
                    }
                    
                    // Fix: Pastikan data jam selalu ada, meskipun kosong - SAMA PERSIS DENGAN METHOD employeeSummary
                    $rows->push((object)[
                        'tanggal' => $row->tanggal,
                        'user_id' => $row->user_id,
                        'nama_lengkap' => $row->nama_lengkap,
                        'division_id' => $row->division_id,
                        'jam_masuk' => $row->jam_masuk, // Kirim datetime lengkap, bukan format time
                        'jam_keluar' => $row->jam_keluar, // Kirim datetime lengkap, bukan format time
                        'total_masuk' => $row->total_masuk ?? 0,
                        'total_keluar' => $row->total_keluar ?? 0,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_cross_day' => $row->is_cross_day ?? false,
                        'shift_start' => $shift->time_start ?? null,
                        'shift_end' => $shift->time_end ?? null,
                        'has_no_checkout' => $has_no_checkout,
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
                    $offDays = $this->calculateOffDays($firstRow->user_id, null, $start, $end);
                    
                    // Calculate PH days (Public Holiday compensations)
                    $phData = $this->calculatePHData($firstRow->user_id, $start, $end);
                    
                    // Calculate leave data (cuti, extra off, sakit)
                    $leaveData = $this->calculateLeaveData($firstRow->user_id, $start, $end);
                    
                    // Calculate alpa days (days with shift but no attendance and no absent request)
                    $alpaDays = $this->calculateAlpaDays($firstRow->user_id, null, $start, $end);
                    
                    // FIXED: Calculate Extra Off overtime once to prevent accumulation
                    $extraOffOvertimeTotal = floor($this->getExtraOffOvertimeHours($firstRow->user_id, $start, $end));
                    
                    // Siapkan data detail absensi harian untuk expandable table
                    $dailyAttendance = $employeeRows->map(function($row) use ($firstRow) {
                        // Get overtime from Extra Off system for this specific date
                        $extraOffOvertimeForDate = $this->getExtraOffOvertimeHoursForDate($firstRow->user_id, $row->tanggal);
                        
                        // Round down lembur biasa (bulatkan ke bawah)
                        $lemburRounded = floor($row->lembur ?? 0);
                        // Round down total lembur (bulatkan ke bawah)
                        $totalLemburRounded = floor($lemburRounded + $extraOffOvertimeForDate);
                        
                        return [
                            'tanggal' => $row->tanggal,
                            'jam_masuk' => $row->jam_masuk,
                            'jam_keluar' => $row->jam_keluar,
                            'telat' => $row->telat,
                            'lembur' => $lemburRounded,
                            'extra_off_overtime' => $extraOffOvertimeForDate, // Overtime from Extra Off system for this date
                            'total_lembur' => $totalLemburRounded, // Combined overtime (rounded down)
                            'is_cross_day' => $row->is_cross_day ?? false,
                            'is_off' => $row->is_off ?? false,
                            'is_holiday' => $row->is_holiday ?? false,
                            'holiday_name' => $row->holiday_name ?? null,
                            'shift_start' => $row->shift_start ?? null,
                            'shift_end' => $row->shift_end ?? null,
                        ];
                    })->sortBy('tanggal')->values();

                    // Round down semua lembur (bulatkan ke bawah)
                    $totalLemburRegular = floor($employeeRows->sum('lembur'));
                    $totalLemburWithExtraOff = floor($totalLemburRegular + $extraOffOvertimeTotal);
                    
                    $result = (object)[
                        'user_id' => $firstRow->user_id,
                        'nik' => $userData->nik ?? '-',
                        'nama_lengkap' => $firstRow->nama_lengkap,
                        'jabatan' => $userData->jabatan ?? '-',
                        'division_id' => $firstRow->division_id,
                        // ✅ FIX: Remove outlet info - sama seperti report attendance
                        'hari_kerja' => $employeeRows->count(), // Jumlah hari yang bekerja
                        'off_days' => $offDays, // Jumlah hari tanpa shift
                        'ph_days' => $phData['days'], // Jumlah hari libur nasional dengan kompensasi
                        'ph_bonus' => $phData['bonus'], // Total bonus PH yang diterima
                        'cuti_days' => $leaveData['annual_leave_days'] ?? 0, // Jumlah hari cuti (Annual Leave)
                        'extra_off_days' => $leaveData['extra_off_days'] ?? 0, // Jumlah hari extra off
                        'sakit_days' => $leaveData['sick_leave_days'] ?? 0, // Jumlah hari sakit (Sick Leave)
                        'alpa_days' => $alpaDays, // Jumlah hari alpa
                        'ot_full_days' => $totalLemburWithExtraOff, // Total lembur (OT Full) + Extra Off Overtime (rounded down)
                        'total_telat' => $employeeRows->sum('telat'),
                        'total_lembur' => $totalLemburWithExtraOff, // Total lembur termasuk extra off overtime (rounded down)
                        'total_days' => $this->calculateTotalDaysInPeriod($start, $end), // Total hari dalam periode
                        // Data detail untuk expandable table
                        'daily_attendance' => $dailyAttendance,
                    ];
                    
                    // Add dynamic leave data - SAMA PERSIS DENGAN METHOD employeeSummary
                    foreach ($leaveData as $key => $value) {
                        if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                            $result->$key = $value;
                        }
                    }
                    
                    $employeeSummary->push($result);
                }

                // Sort employee summary by nama_lengkap
                $employeeSummary = $employeeSummary->sortBy('nama_lengkap')->values();


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


                $export = new EmployeeSummaryExport($employeeSummary);
                $export->fileName = $fileName;
                
                
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
    private function getApprovedAbsentRequests($startDate, $endDate, $userId = null)
    {
        $query = DB::table('absent_requests')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            });
            
        // Filter by user if provided
        if ($userId) {
            $query->where('absent_requests.user_id', $userId);
        }
            
        $approvedAbsents = $query->select([
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

    // Method untuk employee summary attendance detail
    public function employeeSummaryAttendance(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $userId = $request->input('user_id');

        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for employee summary attendance', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }

        // Set timeout untuk mencegah loading terlalu lama
        set_time_limit(300); // 5 menit
        

        if (!empty($outletId) || !empty($divisionId) || !empty($bulan) || !empty($tahun) || !empty($userId)) {
            $bulan = $bulan ?: date('m');
            $tahun = $tahun ?: date('Y');
            $start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
            $end = date('Y-m-d', strtotime("$tahun-$bulan-25"));

            // Ambil data attendance untuk user tertentu
            $attendanceData = $this->getAttendanceDataForUser($userId, $start, $end);
            
            // Get dropdown data
            $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
            $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();

            return Inertia::render('AttendanceReport/EmployeeSummaryAttendance', [
                'attendanceData' => $attendanceData,
                'outlets' => $outlets,
                'divisions' => $divisions,
                'filter' => [
                    'outlet_id' => $outletId,
                    'division_id' => $divisionId,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'user_id' => $userId,
                ],
                'period' => ['start' => $start, 'end' => $end],
                'user' => auth()->user(),
            ]);
        }

        // Return empty data if no filters
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->select('id', 'nama_divisi as name')->orderBy('nama_divisi')->get();

        return Inertia::render('AttendanceReport/EmployeeSummaryAttendance', [
            'attendanceData' => [],
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'user_id' => $userId,
            ],
            'period' => null,
            'user' => auth()->user(),
        ]);
    }

    // Helper method untuk mengambil data attendance user tertentu
    private function getAttendanceDataForUser($userId, $start, $end)
    {
        // Implementation untuk mengambil data attendance detail user
        // Ini bisa diimplementasikan sesuai kebutuhan
        return [];
    }

    // Export Excel untuk Employee Summary Attendance
    public function exportEmployeeSummaryAttendance(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $userId = $request->input('user_id');

        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for export employee summary attendance', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }

        // Set timeout untuk mencegah loading terlalu lama
        set_time_limit(300); // 5 menit
        

        // Implementation untuk export Excel
        // Ini bisa diimplementasikan sesuai kebutuhan
        
        return response()->json(['message' => 'Export functionality not implemented yet']);
    }

    /**
     * Get overtime hours from Extra Off system for a specific user on a specific date
     * 
     * @param int $userId
     * @param string $date
     * @return float Overtime hours for that date
     */
    private function getExtraOffOvertimeHoursForDate($userId, $date)
    {
        try {
            // Get overtime transaction from Extra Off system for specific date
            $overtimeTransaction = DB::table('extra_off_transactions')
                ->where('user_id', $userId)
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('source_date', $date)
                ->first();

            if (!$overtimeTransaction) {
                return 0;
            }

            // Extract work hours from description
            // Format: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (jam 08:00 - 18:00, 10.45 jam)"
            // or: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (10.45 jam)"
            $workHours = 0;
            if (preg_match('/\(jam\s+[0-9:]+\s*-\s*[0-9:]+,\s*([0-9.]+)\s*jam\)/', $overtimeTransaction->description, $matches)) {
                // New format with time range
                $workHours = (float) $matches[1];
            } elseif (preg_match('/\(([0-9.]+)\s*jam\)/', $overtimeTransaction->description, $matches)) {
                // Old format without time range
                $workHours = (float) $matches[1];
            }

            // Round down (bulatkan ke bawah)
            return floor($workHours);

        } catch (\Exception $e) {
            \Log::error('Error calculating Extra Off overtime hours for date', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            
            return 0; // Return 0 if there's an error
        }
    }

    /**
     * Get overtime hours from Extra Off system for a specific user and date range
     * 
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @return float Total overtime hours
     */
    private function getExtraOffOvertimeHours($userId, $startDate, $endDate)
    {
        try {
            // Get overtime transactions from Extra Off system
            $overtimeTransactions = DB::table('extra_off_transactions')
                ->where('user_id', $userId)
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('status', 'approved') // Only count approved transactions
                ->whereBetween('source_date', [$startDate, $endDate])
                ->get();

            $totalOvertimeHours = 0;

            foreach ($overtimeTransactions as $transaction) {
                // Extract work hours from description
                // Format: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (jam 08:00 - 18:00, 10.45 jam)"
                // or: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (10.45 jam)"
                if (preg_match('/\(jam\s+[0-9:]+\s*-\s*[0-9:]+,\s*([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
                    // New format with time range
                    $workHours = (float) $matches[1];
                    // Round down (bulatkan ke bawah)
                    $workHours = floor($workHours);
                    $totalOvertimeHours += $workHours;
                } elseif (preg_match('/\(([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
                    // Old format without time range
                    $workHours = (float) $matches[1];
                    // Round down (bulatkan ke bawah)
                    $workHours = floor($workHours);
                    $totalOvertimeHours += $workHours;
                }
            }

            \Log::info('Extra Off Overtime Hours calculated', [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'transactions_count' => $overtimeTransactions->count(),
                'total_overtime_hours' => $totalOvertimeHours
            ]);

            return $totalOvertimeHours;

        } catch (\Exception $e) {
            \Log::error('Error calculating Extra Off overtime hours', [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return 0; // Return 0 if there's an error
        }
    }

    /**
     * Smart cross-day attendance processing untuk multi-outlet scenarios
     */
    private function processSmartCrossDayAttendance($data, $allProcessedData) {
        $scans = collect($data['scans'])->sortBy('scan_date');
        $inScans = $scans->where('inoutmode', 1);
        $outScans = $scans->where('inoutmode', 2);
        
        $totalMasuk = $inScans->count();
        $totalKeluar = $outScans->count();
        
        // Ambil scan masuk pertama
        $jamMasuk = $inScans->first()['scan_date'] ?? null;
        $jamKeluar = null;
        $isCrossDay = false;
        
        if ($jamMasuk) {
            // SOLUSI TERBAIK: Logika sederhana dan konsisten dengan multi-outlet support
            
            // 1. Cari OUT scan di hari yang sama
            $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
            
            // 2. Cari OUT scan di hari berikutnya (cross-day)
            $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
            $nextDayKey = $data['user_id'] . '_' . $nextDay;
            $nextDayOuts = collect();
            
            if (isset($allProcessedData[$nextDayKey])) {
                $nextDayScans = collect($allProcessedData[$nextDayKey]['scans'])->sortBy('scan_date');
                $nextDayOuts = $nextDayScans->where('inoutmode', 2);
            }
            
            
            // 3. Tentukan OUT scan yang paling masuk akal - FIXED for multi-outlet
            if ($sameDayOuts->isNotEmpty() && $nextDayOuts->isNotEmpty()) {
                // Ada both same-day dan cross-day OUT scan
                $lastSameDayOut = $sameDayOuts->last()['scan_date'];
                $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                
                // Cek durasi same-day OUT
                $sameDayDuration = strtotime($lastSameDayOut) - strtotime($jamMasuk);
                $outHour = (int)date('H', strtotime($firstNextDayOut));
                
                \Log::info('Multi-outlet cross-day decision', [
                    'tanggal' => $data['tanggal'],
                    'user_id' => $data['user_id'],
                    'jam_masuk' => $jamMasuk,
                    'same_day_out' => $lastSameDayOut,
                    'cross_day_out' => $firstNextDayOut,
                    'same_day_duration' => $sameDayDuration,
                    'out_hour' => $outHour,
                    'same_day_duration_hours' => round($sameDayDuration / 3600, 2)
                ]);
                
                // Untuk multi-outlet cross-day, prioritas cross-day jika:
                // 1. Same-day OUT terlalu pendek (< 5 jam) ATAU
                // 2. Cross-day OUT di pagi sangat awal (00:00-06:00)
                if ($sameDayDuration < 18000 || ($outHour >= 0 && $outHour <= 6)) {
                    $jamKeluar = $firstNextDayOut;
                    $isCrossDay = true;
                    $totalKeluar = 1;
                    
                    \Log::info('Chose cross-day checkout', [
                        'reason' => $sameDayDuration < 18000 ? 'same_day_too_short' : 'cross_day_early_morning',
                        'chosen_out' => $jamKeluar
                    ]);
                    
                    // Hapus scan keluar dari hari berikutnya
                    $allProcessedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                    
                } else {
                    $jamKeluar = $lastSameDayOut;
                    $isCrossDay = false;
                    
                    \Log::info('Chose same-day checkout', [
                        'reason' => 'same_day_long_enough',
                        'chosen_out' => $jamKeluar
                    ]);
                }
            } elseif ($sameDayOuts->isNotEmpty()) {
                // Hanya ada same-day OUT scan
                $jamKeluar = $sameDayOuts->last()['scan_date'];
                $isCrossDay = false;
                
                
            } elseif ($nextDayOuts->isNotEmpty()) {
                // Hanya ada cross-day OUT scan
                $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                $outHour = (int)date('H', strtotime($firstNextDayOut));
                
                // Untuk cross-day, hanya gunakan jika di pagi sangat awal (00:00-12:00)
                if ($outHour >= 0 && $outHour <= 12) {
                    $jamKeluar = $firstNextDayOut;
                    $isCrossDay = true;
                    $totalKeluar = 1;
                    
                    
                    // Hapus scan keluar dari hari berikutnya
                    $allProcessedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                }
            }
            
        }
        
        
        return [
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

    /**
     * Deteksi pola cross-day berdasarkan waktu dan jumlah scan (DIPERBAIKI)
     */
    private function detectCrossDayPattern($jamMasuk, $inScans, $outScans) {
        $masukHour = (int)date('H', strtotime($jamMasuk));
        
        // HANYA deteksi cross-day jika benar-benar jelas cross-day
        // Jangan deteksi cross-day untuk shift normal pagi-sore
        
        // Pola 1: Masuk pagi (06:00-09:00) dengan multiple IN scans DAN tidak ada OUT scan
        // Ini kemungkinan kelanjutan shift malam sebelumnya (multi-outlet)
        if ($masukHour >= 6 && $masukHour <= 9 && $inScans->count() > 1 && $outScans->count() == 0) {
            return true;
        }
        
        // Pola 2: Masuk pagi (06:00-08:00) dengan OUT scan di pagi sangat awal (06:00-10:00)
        // Kemungkinan shift malam yang berakhir pagi sangat awal
        if ($masukHour >= 6 && $masukHour <= 8) {
            $outScansAfterIn = $outScans->where('scan_date', '>', $jamMasuk);
            if ($outScansAfterIn->isNotEmpty()) {
                $firstOutAfterIn = $outScansAfterIn->first()['scan_date'];
                $outHour = (int)date('H', strtotime($firstOutAfterIn));
                
                // HANYA jika OUT di pagi sangat awal (06:00-10:00)
                if ($outHour >= 6 && $outHour <= 10) {
                    return true;
                }
            }
        }
        
        // Pola 3: Masuk pagi (06:00-09:00) tanpa OUT scan di hari yang sama
        // Kemungkinan shift malam yang berakhir pagi
        if ($masukHour >= 6 && $masukHour <= 9 && $outScans->count() == 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Perhitungan lembur yang SEDERHANA dan AMAN - FIXED
     */
    private function calculateSmartOvertime($jamMasuk, $jamKeluar, $shiftEnd, $tanggal, $isCrossDay) {
        if (!$jamMasuk || !$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // Ambil jam saja dari jam keluar dan shift end
        $jamKeluarTime = date('H:i:s', strtotime($jamKeluar));
        $shiftEndTime = $shiftEnd; // Sudah dalam format H:i:s
        
        // Konversi ke timestamp untuk perhitungan
        $keluarTimestamp = strtotime($jamKeluarTime);
        $shiftEndTimestamp = strtotime($shiftEndTime);
        
        // Hitung selisih dalam detik
        $diffSeconds = $keluarTimestamp - $shiftEndTimestamp;
        
        // Konversi ke jam (hanya jika positif)
        $overtimeHours = $diffSeconds > 0 ? floor($diffSeconds / 3600) : 0;
        
        // Batasi maksimal 12 jam untuk mencegah error
        $overtimeHours = min($overtimeHours, 12);
        
        
        return $overtimeHours;
    }

    /**
     * Perhitungan lembur yang MENANGANI CROSS-DAY dengan benar - FIXED
     */
    private function calculateSimpleOvertime($jamKeluar, $shiftEnd) {
        if (!$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // VALIDATION: Cek apakah data jam keluar valid
        $jamKeluarTimestamp = strtotime($jamKeluar);
        if ($jamKeluarTimestamp === false) {
            \Log::error('Invalid jam keluar format', [
                'jam_keluar' => $jamKeluar,
                'shift_end' => $shiftEnd
            ]);
            return 0;
        }
        
        // Ambil jam saja (abaikan tanggal)
        $jamKeluarTime = date('H:i:s', $jamKeluarTimestamp);
        
        // Konversi ke timestamp untuk perhitungan
        $keluarTimestamp = strtotime($jamKeluarTime);
        $shiftEndTimestamp = strtotime($shiftEnd);
        
        // Hitung selisih dalam detik
        $diffSeconds = $keluarTimestamp - $shiftEndTimestamp;
        
        
        // Jika selisih negatif, kemungkinan cross-day ATAU checkout lebih awal
        if ($diffSeconds < 0) {
            // Cek apakah ini benar-benar cross-day atau checkout lebih awal
            $checkoutHour = (int)date('H', strtotime($jamKeluarTime));
            $shiftEndHour = (int)date('H', strtotime($shiftEnd));
            
            // Jika checkout di pagi sangat awal (00:00-06:00) dan shift end di sore/malam, ini cross-day
            if ($checkoutHour >= 0 && $checkoutHour <= 6 && $shiftEndHour >= 17) {
                // Untuk cross-day, hitung dari shift end sampai jam keluar di hari berikutnya
                // Misal: shift end 17:00, keluar 06:00 = 13 jam overtime
                $crossDaySeconds = (24 * 3600) + $diffSeconds; // 24 jam + selisih negatif
                $overtimeHours = floor($crossDaySeconds / 3600);
                
            } else {
                // Ini bukan cross-day, tapi checkout lebih awal dari shift end
                // Tidak ada lembur
                $overtimeHours = 0;
                
            }
        } else {
            // Normal overtime
            $overtimeHours = floor($diffSeconds / 3600);
            
        }
        
        // Batasi maksimal 12 jam untuk mencegah error
        $overtimeHours = min($overtimeHours, 12);
        
        // FIXED: Jangan hitung lembur jika checkout lebih awal dari shift end
        if ($overtimeHours < 0) {
            $overtimeHours = 0;
        }
        
        // SAFETY CHECK: Jika nilai lembur terlalu besar, reset ke 0
        if ($overtimeHours > 12) {
            $overtimeHours = 0;
        }
        
        // ADDITIONAL SAFETY: Jika nilai lembur negatif atau sangat besar, reset ke 0
        if ($overtimeHours < 0 || $overtimeHours > 24) {
            $overtimeHours = 0;
        }
        
        return $overtimeHours;
    }

    /**
     * Perhitungan telat yang menangani cross-day
     */
    private function calculateLateness($jamMasuk, $shiftStart, $isCrossDay) {
        if (!$jamMasuk || !$shiftStart) {
            return 0;
        }
        
        // Untuk cross-day, tidak ada telat karena ini kelanjutan shift malam sebelumnya
        if ($isCrossDay) {
            return 0;
        }
        
        // Perhitungan telat normal
        $start = strtotime($shiftStart);
        $masuk = strtotime($jamMasuk);
        $diff = $masuk - $start;
        
        return $diff > 0 ? round($diff/60) : 0;
    }

    /**
     * Perhitungan telat dari early checkout (checkout lebih awal dari shift end)
     * FIXED: Tidak menghitung telat untuk cross-day scenario
     */
    private function calculateEarlyCheckoutLateness($jamKeluar, $shiftEnd, $isCrossDay = false) {
        if (!$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // Untuk cross-day, tidak ada telat dari early checkout karena ini shift malam
        if ($isCrossDay) {
            \Log::info('Cross-day attendance - no early checkout lateness', [
                'jam_keluar' => $jamKeluar,
                'shift_end' => $shiftEnd,
                'is_cross_day' => $isCrossDay
            ]);
            return 0;
        }
        
        // Ambil jam saja (abaikan tanggal)
        $jamKeluarTime = date('H:i:s', strtotime($jamKeluar));
        $shiftEndTime = $shiftEnd; // Sudah dalam format H:i:s
        
        // Konversi ke timestamp untuk perhitungan
        $keluarTimestamp = strtotime($jamKeluarTime);
        $shiftEndTimestamp = strtotime($shiftEndTime);
        
        // Hitung selisih dalam detik
        $diffSeconds = $shiftEndTimestamp - $keluarTimestamp;
        
        // Jika checkout lebih awal dari shift end, hitung telat
        if ($diffSeconds > 0) {
            $telatMinutes = round($diffSeconds / 60);
            \Log::info('Early checkout lateness calculation', [
                'jam_keluar' => $jamKeluarTime,
                'shift_end' => $shiftEndTime,
                'diff_seconds' => $diffSeconds,
                'telat_minutes' => $telatMinutes,
                'is_cross_day' => $isCrossDay
            ]);
            return $telatMinutes;
        }
        
        return 0;
    }

    /**
     * Fallback ke perhitungan lembur lama jika diperlukan
     */
    private function calculateLegacyOvertime($jamMasuk, $jamKeluar, $shiftEnd, $tanggal) {
        if (!$jamMasuk || !$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // Logika lama: shift end selalu di hari yang sama
        $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shiftEnd;
        $scanOutDateTime = $jamKeluar;
        
        $shiftEndTime = strtotime($shiftEndDateTime);
        $scanOutTime = strtotime($scanOutDateTime);
        
        return $scanOutTime > $shiftEndTime ? floor(($scanOutTime - $shiftEndTime) / 3600) : 0;
    }
} 