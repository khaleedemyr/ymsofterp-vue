<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;

        $divisions = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);
        $selectedDivision = $divisionId
            ? $divisions->firstWhere('id', $divisionId)
            : null;

        $rows = $this->service->buildDepartmentalTrainingReport($year, $month, $divisionId);

        $monthLabel = sprintf('%02d/%04d', $month, $year);

        return Inertia::render('JustAcademy/Reports/Index', [
            'rows' => $rows,
            'divisions' => $divisions,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'division_id' => $divisionId,
            ],
            'reportMeta' => [
                'month_label' => $monthLabel,
                'department_label' => $selectedDivision?->nama_divisi ?? 'Semua Departemen',
            ],
        ]);
    }
}
