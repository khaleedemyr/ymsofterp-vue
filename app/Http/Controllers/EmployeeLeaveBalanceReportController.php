<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeLeaveBalanceExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeLeaveBalanceReportController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $status = $request->input('status', 'A');
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 25);

        if ($authUser && $authUser->id_outlet && (int) $authUser->id_outlet !== 1) {
            $outletId = (string) $authUser->id_outlet;
        }

        $query = $this->baseQuery();
        $this->applyFilters($query, $outletId, $divisionId, $status, $search);

        $rows = $query
            ->orderBy('u.nama_lengkap')
            ->paginate($perPage)
            ->withQueryString();

        $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->where('status', 'A')->select('id', 'nama_divisi')->orderBy('nama_divisi')->get();

        return Inertia::render('Users/LeaveBalanceReport', [
            'rows' => $rows,
            'outlets' => $outlets,
            'divisions' => $divisions,
            'authUser' => [
                'id' => $authUser->id,
                'id_outlet' => $authUser->id_outlet,
            ],
            'filters' => [
                'outlet_id' => $outletId ?? '',
                'division_id' => $divisionId ?? '',
                'status' => $status,
                'search' => $search ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $authUser = auth()->user();
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $status = $request->input('status', 'A');
        $search = $request->input('search');

        if ($authUser && $authUser->id_outlet && (int) $authUser->id_outlet !== 1) {
            $outletId = (string) $authUser->id_outlet;
        }

        $query = $this->baseQuery();
        $this->applyFilters($query, $outletId, $divisionId, $status, $search);

        $data = $query->orderBy('u.nama_lengkap')->get();

        $filename = 'saldo_cuti_ph_extra_off_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new EmployeeLeaveBalanceExport($data), $filename);
    }

    private function baseQuery()
    {
        return DB::table('users as u')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('extra_off_balance as eob', 'u.id', '=', 'eob.user_id')
            ->selectRaw('u.id, u.nik, u.nama_lengkap, u.status, u.cuti, o.nama_outlet, d.nama_divisi, j.nama_jabatan,
                COALESCE(eob.balance, 0) as extra_off_balance_days,
                (
                    SELECT COALESCE(SUM(h.compensation_amount), 0)
                    FROM holiday_attendance_compensations h
                    WHERE h.user_id = u.id AND h.compensation_type = \'extra_off\' AND h.status = \'approved\'
                ) as ph_extra_off_days_approved
            ');
    }

    private function applyFilters($query, $outletId, $divisionId, $status, $search): void
    {
        if ($status === 'A') {
            $query->where('u.status', 'A');
        } elseif ($status === 'N') {
            $query->where('u.status', 'N');
        } elseif ($status === 'B') {
            $query->where('u.status', 'B');
        }
        // status === 'all' → tanpa filter status

        if ($outletId !== null && $outletId !== '') {
            $query->where('u.id_outlet', $outletId);
        }

        if ($divisionId !== null && $divisionId !== '') {
            $query->where('u.division_id', $divisionId);
        }

        if ($search) {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('u.nama_lengkap', 'like', $s)
                    ->orWhere('u.nik', 'like', $s)
                    ->orWhere('u.email', 'like', $s);
            });
        }
    }
}
