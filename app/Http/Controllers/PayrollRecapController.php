<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollRecapController extends Controller
{
    public function index(Request $request)
    {
        $month = str_pad((string) ((int) $request->input('month', (int) date('m'))), 2, '0', STR_PAD_LEFT);
        $year = (int) $request->input('year', (int) date('Y'));

        $monthInt = (int) $month;
        $year = max(2000, min(2100, $year));

        $payrolls = DB::table('payroll_generated as pg')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'pg.outlet_id')
            ->where('pg.month', $monthInt)
            ->where('pg.year', $year)
            ->select(
                'pg.id',
                'pg.outlet_id',
                'o.nama_outlet as outlet_name'
            )
            ->orderBy('o.nama_outlet')
            ->get();

        $rows = [];
        $totalGaji1 = 0.0;
        $totalGaji2 = 0.0;
        $totalGrand = 0.0;
        $totalBpjs = 0.0;

        foreach ($payrolls as $payroll) {
            $sum = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payroll->id)
                ->selectRaw('
                    SUM(COALESCE(total_gaji_akhir_bulan, 0)) as total_gaji_1,
                    SUM(COALESCE(total_gaji_tanggal_8, 0)) as total_gaji_2,
                    SUM(
                        CASE
                            WHEN JSON_VALID(bpjs_perusahaan_detail) THEN
                                CAST(
                                    COALESCE(
                                        JSON_UNQUOTE(JSON_EXTRACT(bpjs_perusahaan_detail, "$.total_perusahaan")),
                                        "0"
                                    ) AS DECIMAL(18,2)
                                )
                            ELSE 0
                        END
                    ) as total_bpjs_perusahaan
                ')
                ->first();

            $gaji1 = round((float) ($sum->total_gaji_1 ?? 0));
            $gaji2 = round((float) ($sum->total_gaji_2 ?? 0));
            $bpjs = round((float) ($sum->total_bpjs_perusahaan ?? 0));
            $grand = $gaji1 + $gaji2;

            $rows[] = [
                'outlet' => $payroll->outlet_name ?: 'Unknown Outlet',
                'total_gaji_1' => $gaji1,
                'total_gaji_2' => $gaji2,
                'grand_total_gaji' => $grand,
                'bpjs_perusahaan' => $bpjs,
            ];

            $totalGaji1 += $gaji1;
            $totalGaji2 += $gaji2;
            $totalGrand += $grand;
            $totalBpjs += $bpjs;
        }

        return Inertia::render('Payroll/RekapPayroll', [
            'months' => $this->getMonths(),
            'years' => $this->getYears(),
            'filter' => [
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $rows,
            'summary' => [
                'total_gaji_1' => round($totalGaji1),
                'total_gaji_2' => round($totalGaji2),
                'grand_total_gaji' => round($totalGrand),
                'bpjs_perusahaan' => round($totalBpjs),
            ],
        ]);
    }

    private function getMonths(): array
    {
        return [
            ['id' => '01', 'name' => 'Januari'],
            ['id' => '02', 'name' => 'Februari'],
            ['id' => '03', 'name' => 'Maret'],
            ['id' => '04', 'name' => 'April'],
            ['id' => '05', 'name' => 'Mei'],
            ['id' => '06', 'name' => 'Juni'],
            ['id' => '07', 'name' => 'Juli'],
            ['id' => '08', 'name' => 'Agustus'],
            ['id' => '09', 'name' => 'September'],
            ['id' => '10', 'name' => 'Oktober'],
            ['id' => '11', 'name' => 'November'],
            ['id' => '12', 'name' => 'Desember'],
        ];
    }

    private function getYears(): array
    {
        $currentYear = (int) date('Y');

        return collect(range($currentYear - 2, $currentYear + 1))
            ->map(fn (int $year) => ['id' => $year, 'name' => (string) $year])
            ->all();
    }
}

