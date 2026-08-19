<?php

namespace App\Http\Controllers\JustAcademy;

use App\Exports\JustAcademy\AttendanceRecapExport;
use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceRecapController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $context = $this->recapContext($request);

        return Inertia::render('JustAcademy/AttendanceRecap/Index', [
            'sections' => $context['sections'],
            'scheduleOptions' => $context['scheduleOptions'],
            'divisions' => $context['divisions'],
            'filters' => $context['filters'],
            'reportMeta' => $context['reportMeta'],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $context = $this->recapContext($request);
        $filters = $context['filters'];
        $fileName = sprintf(
            'rekap_kehadiran_training_%02d_%04d.xlsx',
            $filters['month'],
            $filters['year']
        );

        return Excel::download(
            new AttendanceRecapExport($context['sections'], $context['reportMeta']),
            $fileName
        );
    }

    /**
     * @return array{
     *     sections: \Illuminate\Support\Collection,
     *     scheduleOptions: \Illuminate\Support\Collection,
     *     divisions: \Illuminate\Support\Collection,
     *     filters: array{year: int, month: int, division_id: int|null, schedule_id: int|null},
     *     reportMeta: array{month_label: string, department_label: string, schedule_label: string}
     * }
     */
    private function recapContext(Request $request): array
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;
        $scheduleId = $request->filled('schedule_id') ? (int) $request->input('schedule_id') : null;

        $divisions = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);
        $selectedDivision = $divisionId
            ? $divisions->firstWhere('id', $divisionId)
            : null;

        $scheduleOptions = $this->service->attendanceRecapScheduleOptions($year, $month);
        if ($scheduleId && ! $scheduleOptions->contains(fn ($opt) => (int) $opt['id'] === $scheduleId)) {
            $scheduleId = null;
        }

        $sections = $this->service->buildAttendanceRecap($year, $month, $divisionId, $scheduleId);
        $selectedSchedule = $scheduleId
            ? $scheduleOptions->firstWhere('id', $scheduleId)
            : null;

        return [
            'sections' => $sections,
            'scheduleOptions' => $scheduleOptions,
            'divisions' => $divisions,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'division_id' => $divisionId,
                'schedule_id' => $scheduleId,
            ],
            'reportMeta' => [
                'month_label' => sprintf('%02d/%04d', $month, $year),
                'department_label' => $selectedDivision?->nama_divisi ?? 'Semua Departemen',
                'schedule_label' => $selectedSchedule['title'] ?? 'Semua Training Plan',
            ],
        ];
    }
}
