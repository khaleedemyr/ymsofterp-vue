<?php

namespace App\Http\Controllers\JustAcademy;

use App\Exports\JustAcademy\AttendanceRecapExport;
use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\JustAcademy\JaCategory;
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
            'categories' => $context['categories'],
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
     *     categories: \Illuminate\Support\Collection,
     *     filters: array{year: int, month: int, division_id: int|null, schedule_title: string|null, category_id: int|null},
     *     reportMeta: array{month_label: string, department_label: string, schedule_label: string, method_label: string}
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
        $scheduleTitle = $request->filled('schedule_title') ? trim((string) $request->input('schedule_title')) : null;
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        $divisions = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);
        $selectedDivision = $divisionId
            ? $divisions->firstWhere('id', $divisionId)
            : null;

        $categories = JaCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $selectedCategory = $categoryId
            ? $categories->firstWhere('id', $categoryId)
            : null;
        if ($categoryId && ! $selectedCategory) {
            $categoryId = null;
        }

        $scheduleOptions = $this->service->attendanceRecapScheduleOptions($year, $month, $categoryId);
        if ($scheduleTitle && ! $scheduleOptions->contains(fn ($opt) => (string) $opt['title'] === $scheduleTitle)) {
            $matched = $scheduleOptions->first(fn ($opt) => mb_strtolower((string) $opt['title']) === mb_strtolower($scheduleTitle));
            $scheduleTitle = $matched['title'] ?? null;
        }

        $sections = $this->service->buildAttendanceRecap($year, $month, $divisionId, $scheduleTitle, $categoryId);
        $selectedSchedule = $scheduleTitle
            ? $scheduleOptions->firstWhere('title', $scheduleTitle)
            : null;

        return [
            'sections' => $sections,
            'scheduleOptions' => $scheduleOptions,
            'divisions' => $divisions,
            'categories' => $categories,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'division_id' => $divisionId,
                'schedule_title' => $scheduleTitle,
                'category_id' => $categoryId,
            ],
            'reportMeta' => [
                'month_label' => sprintf('%02d/%04d', $month, $year),
                'department_label' => $selectedDivision?->nama_divisi ?? 'Semua Departemen',
                'schedule_label' => $selectedSchedule['title'] ?? 'Semua Training Plan',
                'method_label' => $selectedCategory?->name ?? 'Semua Method',
            ],
        ];
    }
}
