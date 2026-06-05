<?php

namespace App\Http\Controllers;

use App\Services\AttendanceOutletAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceTrackingController extends Controller
{
    public function __construct(
        private AttendanceOutletAnalyticsService $outletAnalytics,
        private AttendanceReportController $attendanceReport
    ) {}

    public function index(Request $request)
    {
        $userId = $request->get('user_id');
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));

        $startDate = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
        $endDate = date('Y-m-d', strtotime("$tahun-$bulan-25"));

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);

        $employee = null;
        $summary = null;
        $outletStats = [];

        if ($userId) {
            $employee = DB::table('users as u')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
                ->where('u.id', $userId)
                ->select([
                    'u.id',
                    'u.nama_lengkap',
                    'u.nik',
                    'j.nama_jabatan',
                    'o.nama_outlet',
                    'd.nama_divisi',
                ])
                ->first();

            if ($employee) {
                $summary = $this->attendanceReport->buildEmployeePeriodSummary((int) $userId, $startDate, $endDate);
                $outletStats = $this->outletAnalytics->getOutletStatsForPeriod((int) $userId, $startDate, $endDate);
            }
        }

        return Inertia::render('AttendanceTracking/Index', [
            'employee' => $employee,
            'summary' => $summary,
            'outletStats' => $outletStats,
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filters' => [
                'user_id' => $userId,
                'bulan' => (int) $bulan,
                'tahun' => (int) $tahun,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
