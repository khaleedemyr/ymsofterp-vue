<?php

namespace App\Http\Controllers\Report;

use App\Exports\CompetitorBenchmarkReportReportExport;
use App\Http\Controllers\Controller;
use App\Models\CompetitorBenchmarkReport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CompetitorBenchmarkReportReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Report/CompetitorBenchmarkReportReport');
    }

    public function report(Request $request)
    {
        $validated = $this->validateFilters($request);

        return response()->json($this->buildReport($validated));
    }

    public function apiReport(Request $request)
    {
        try {
            $validated = $this->validateFilters($request);

            return response()->json([
                'success' => true,
                ...$this->buildReport($validated),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apiExport(Request $request)
    {
        try {
            $validated = $this->validateFilters($request);
            $data = $this->buildReport($validated);

            $filename = sprintf(
                'competitor_benchmark_report_%s_%s_%s.xlsx',
                $validated['month_from'],
                $validated['month_to'],
                now()->format('Ymd_His')
            );

            return Excel::download(new CompetitorBenchmarkReportReportExport($data), $filename);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function export(Request $request)
    {
        $validated = $this->validateFilters($request);
        $data = $this->buildReport($validated);

        $filename = sprintf(
            'competitor_benchmark_report_%s_%s_%s.xlsx',
            $validated['month_from'],
            $validated['month_to'],
            now()->format('Ymd_His')
        );

        return Excel::download(new CompetitorBenchmarkReportReportExport($data), $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(array $validated): array
    {
        $monthFrom = $validated['month_from'].'-01';
        $monthTo = date('Y-m-t', strtotime($validated['month_to'].'-01'));
        $search = trim((string) ($validated['search'] ?? ''));

        $reports = CompetitorBenchmarkReport::query()
            ->with(['items', 'creator:id,nama_lengkap'])
            ->whereDate('report_month', '>=', $monthFrom)
            ->whereDate('report_month', '<=', $monthTo)
            ->orderBy('report_month')
            ->orderBy('number')
            ->get();

        $rows = [];

        foreach ($reports as $report) {
            foreach ($report->items as $item) {
                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        $report->number,
                        $item->brand_restaurant_visited,
                        (string) $item->location,
                        (string) $report->notes,
                    ]));
                    if (! str_contains($haystack, mb_strtolower($search))) {
                        continue;
                    }
                }

                $rows[] = [
                    'report_number' => $report->number,
                    'report_month' => $report->report_month?->format('Y-m'),
                    'pics' => $this->formatPics($report->pics),
                    'created_by' => $report->creator?->nama_lengkap ?? '-',
                    'brand_restaurant_visited' => $item->brand_restaurant_visited,
                    'location' => $item->location ?: '-',
                    'visit_date' => $item->visit_date?->format('Y-m-d'),
                    'product_benchmark' => $item->product_benchmark ?: '-',
                    'service_benchmark' => $item->service_benchmark ?: '-',
                    'pricing_benchmark' => $item->pricing_benchmark ?: '-',
                    'operational_benchmark' => $item->operational_benchmark ?: '-',
                    'market_positioning_benchmark' => $item->market_positioning_benchmark ?: '-',
                    'summary_report' => $item->summary_report ?: '-',
                    'development_action_plan' => $item->development_action_plan ?: '-',
                ];
            }
        }

        foreach ($rows as $index => &$row) {
            $row['no'] = $index + 1;
        }
        unset($row);

        return [
            'filters' => [
                'month_from' => $validated['month_from'],
                'month_to' => $validated['month_to'],
                'search' => $search !== '' ? $search : null,
            ],
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @param  mixed  $pics
     */
    private function formatPics($pics): string
    {
        if (! is_array($pics) || empty($pics)) {
            return '-';
        }

        return collect($pics)
            ->map(fn ($entry) => $entry['name'] ?? $entry['nama_lengkap'] ?? '')
            ->filter()
            ->implode(', ') ?: '-';
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'month_from' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'month_to' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'search' => 'nullable|string|max:120',
        ], [], [
            'month_from' => 'bulan from',
            'month_to' => 'bulan to',
        ]);

        if ($validated['month_from'] > $validated['month_to']) {
            throw ValidationException::withMessages([
                'month_to' => 'Bulan to harus sama atau setelah bulan from.',
            ]);
        }

        return $validated;
    }
}
