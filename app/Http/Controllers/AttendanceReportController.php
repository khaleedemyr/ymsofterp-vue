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

            // Ambil semua tanggal dalam periode
            $period = [];
            $dt = new \DateTime($start);
            $dtEnd = new \DateTime($end);
            while ($dt <= $dtEnd) {
                $period[] = $dt->format('Y-m-d');
                $dt->modify('+1 day');
            }

            // Query data absensi seperti sebelumnya
            $sub = DB::table('att_log as a')
                ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                ->join('user_pins as up', function($q) {
                    $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                })
                ->join('users as u', 'up.user_id', '=', 'u.id')
                ->select(
                    DB::raw('DATE(a.scan_date) as tanggal'),
                    'u.id as user_id',
                    'u.nama_lengkap',
                    DB::raw('MIN(TIME(a.scan_date)) as jam_masuk'),
                    DB::raw('MAX(TIME(a.scan_date)) as jam_keluar')
                )
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            if (!empty($outletId)) {
                $sub->where('u.id_outlet', $outletId);
            }
            if (!empty($divisionId)) {
                $sub->where('u.division_id', $divisionId);
            }
            if (!empty($search)) {
                $sub->where('u.nama_lengkap', 'like', "%$search%");
            }
            $sub->groupBy('tanggal', 'u.id', 'u.nama_lengkap');
            $dataRows = $sub->orderBy('tanggal')->orderBy('u.nama_lengkap')->get();

            // Index dataRows by tanggal
            $dataByTanggal = [];
            foreach ($dataRows as $row) {
                $dataByTanggal[$row->tanggal] = $row;
            }

            // Ambil nama karyawan dari hasil query (atau dari search)
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

            // Ambil semua tanggal libur dalam periode
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->whereBetween('tgl_libur', [$start, $end])
                ->pluck('keterangan', 'tgl_libur'); // key: tgl_libur, value: keterangan

            // Build rows for each tanggal in period
            $rows = collect();
            foreach ($period as $tanggal) {
                $row = isset($dataByTanggal[$tanggal]) ? $dataByTanggal[$tanggal] : null;
                $jam_masuk = $row ? $row->jam_masuk : null;
                $jam_keluar = $row ? $row->jam_keluar : null;
                $rowUserId = $row ? $row->user_id : $userId;
                $rowNama = $row ? $row->nama_lengkap : $namaKaryawan;
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
                            $end = strtotime($shift->time_end);
                            $keluar = strtotime($jam_keluar);
                            $diff = $keluar - $end;
                            $lembur = $diff > 0 ? floor($diff/3600) : 0;
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
                    'jam_masuk' => $jam_masuk,
                    'jam_keluar' => $jam_keluar,
                    'telat' => $telat,
                    'lembur' => $lembur,
                    'is_off' => $is_off,
                    'shift_name' => $shift_name,
                    'is_holiday' => $is_holiday,
                    'holiday_name' => $holiday_name,
                ]);
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
        $rows = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->whereDate('a.scan_date', $tanggal)
            ->select('o.id_outlet', 'o.nama_outlet', DB::raw('MIN(TIME(a.scan_date)) as jam_in'), DB::raw('MAX(TIME(a.scan_date)) as jam_out'))
            ->groupBy('o.id_outlet', 'o.nama_outlet')
            ->orderBy('o.nama_outlet')
            ->get();
        // Ambil shift per outlet
        $result = $rows->map(function($row) use ($userId, $tanggal) {
            $shift = DB::table('user_shifts as us')
                ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                ->where('us.user_id', $userId)
                ->where('us.tanggal', $tanggal)
                ->where('us.outlet_id', $row->id_outlet)
                ->select('s.time_start', 's.time_end')
                ->first();
            $telat = 0;
            $lembur = 0;
            if ($shift && $shift->time_start && $row->jam_in) {
                $start = strtotime($shift->time_start);
                $masuk = strtotime($row->jam_in);
                $diff = $masuk - $start;
                $telat = $diff > 0 ? round($diff/60) : 0;
            }
            if ($shift && $shift->time_end && $row->jam_out) {
                $end = strtotime($shift->time_end);
                $keluar = strtotime($row->jam_out);
                $diff = $keluar - $end;
                $lembur = $diff > 0 ? round($diff/3600,2) : 0;
            }
            return [
                'nama_outlet' => $row->nama_outlet,
                'jam_in' => $row->jam_in,
                'jam_out' => $row->jam_out,
                'time_start' => $shift->time_start ?? null,
                'time_end' => $shift->time_end ?? null,
                'telat' => $telat,
                'lembur' => $lembur,
            ];
        });
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
                    DB::raw('DATE(a.scan_date) as tanggal'),
                    'u.id as user_id',
                    'u.nama_lengkap',
                    DB::raw('MIN(TIME(a.scan_date)) as jam_masuk'),
                    DB::raw('MAX(TIME(a.scan_date)) as jam_keluar')
                )
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            if (!empty($outletId)) {
                $sub->where('u.id_outlet', $outletId);
            }
            if (!empty($divisionId)) {
                $sub->where('u.division_id', $divisionId);
            }
            if (!empty($search)) {
                $sub->where('u.nama_lengkap', 'like', "%$search%");
            }
            $sub->groupBy('tanggal', 'u.id', 'u.nama_lengkap');
            $dataRows = $sub->orderBy('tanggal')->orderBy('u.nama_lengkap')->get();

            $dataByTanggal = [];
            foreach ($dataRows as $row) {
                $dataByTanggal[$row->tanggal] = $row;
            }

            $namaKaryawan = null;
            $userId = null;
            if (count($dataRows) > 0) {
                $namaKaryawan = $dataRows[0]->nama_lengkap;
                $userId = $dataRows[0]->user_id;
            } elseif (!empty($search)) {
                $user = DB::table('users')->where('nama_lengkap', $search)->first();
                if ($user) {
                    $namaKaryawan = $user->nama_lengkap;
                    $userId = $user->id;
                }
            }

            // Ambil semua tanggal libur dalam periode
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->whereBetween('tgl_libur', [$start, $end])
                ->pluck('keterangan', 'tgl_libur');

            foreach ($period as $tanggal) {
                $row = isset($dataByTanggal[$tanggal]) ? $dataByTanggal[$tanggal] : null;
                $jam_masuk = $row ? $row->jam_masuk : null;
                $jam_keluar = $row ? $row->jam_keluar : null;
                $rowUserId = $row ? $row->user_id : $userId;
                $rowNama = $row ? $row->nama_lengkap : $namaKaryawan;
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
                            $end = strtotime($shift->time_end);
                            $keluar = strtotime($jam_keluar);
                            $diff = $keluar - $end;
                            $lembur = $diff > 0 ? floor($diff/3600) : 0;
                        }
                    } else {
                        $jam_masuk = null;
                        $jam_keluar = null;
                        $telat = 0;
                        $lembur = 0;
                    }
                    // Ambil detail jam masuk/keluar per outlet
                    $detailRows = DB::table('att_log as a')
                        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                        ->join('user_pins as up', function($q) {
                            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                        })
                        ->where('up.user_id', $rowUserId)
                        ->whereDate('a.scan_date', $tanggal)
                        ->select('o.nama_outlet', DB::raw('MIN(TIME(a.scan_date)) as jam_in'), DB::raw('MAX(TIME(a.scan_date)) as jam_out'))
                        ->groupBy('o.nama_outlet')
                        ->orderBy('o.nama_outlet')
                        ->get();
                    $detail = $detailRows->map(function($r) {
                        return $r->nama_outlet . ': ' . ($r->jam_in ?? '-') . ' - ' . ($r->jam_out ?? '-');
                    })->implode('; ');
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
                    'telat' => $telat,
                    'lembur' => $lembur,
                    'is_off' => $is_off,
                    'shift_name' => $shift_name,
                    'shift_time_start' => $shift_time_start,
                    'shift_time_end' => $shift_time_end,
                    'is_holiday' => $is_holiday,
                    'holiday_name' => $holiday_name,
                    'detail' => $detail,
                ]);
            }
        }
        $fileName = 'attendance_';
        $fileName .= $namaKaryawan ? str_replace(' ', '_', $namaKaryawan) : 'all';
        $fileName .= '_' . $start . '_sampai_' . $end . '.xlsx';
        $export = new AttendanceReportExport($rows);
        $export->fileName = $fileName;
        return $export;
    }
} 