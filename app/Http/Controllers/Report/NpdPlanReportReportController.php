<?php

namespace App\Http\Controllers\Report;

use App\Exports\NpdPlanReportReportExport;
use App\Http\Controllers\Controller;
use App\Models\NpdPlanReport;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class NpdPlanReportReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Report/NpdPlanReportReport', [
            'outlets' => Outlet::where('status', 'A')
                ->where('is_outlet', 1)
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']),
            'purposeOptions' => $this->purposeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function report(Request $request)
    {
        $validated = $this->validateFilters($request);

        return response()->json($this->buildReport($validated));
    }

    public function apiFilters()
    {
        return response()->json([
            'success' => true,
            'outlets' => Outlet::where('status', 'A')
                ->where('is_outlet', 1)
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']),
            'purpose_options' => $this->purposeOptions(),
            'status_options' => $this->statusOptions(),
        ]);
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
                'npd_plan_report_%s_%s_%s.xlsx',
                $validated['month_from'],
                $validated['month_to'],
                now()->format('Ymd_His')
            );

            return Excel::download(new NpdPlanReportReportExport($data), $filename);
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
            'npd_plan_report_%s_%s_%s.xlsx',
            $validated['month_from'],
            $validated['month_to'],
            now()->format('Ymd_His')
        );

        return Excel::download(new NpdPlanReportReportExport($data), $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(array $validated): array
    {
        $monthFrom = $validated['month_from'].'-01';
        $monthTo = date('Y-m-t', strtotime($validated['month_to'].'-01'));
        $search = trim((string) ($validated['search'] ?? ''));

        $query = NpdPlanReport::query()
            ->with(['items', 'creator:id,nama_lengkap'])
            ->whereDate('report_month', '>=', $monthFrom)
            ->whereDate('report_month', '<=', $monthTo)
            ->when(! empty($validated['outlet_id']), fn ($q) => $q->where('outlet_id', (int) $validated['outlet_id']))
            ->when(! empty($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->orderBy('report_month')
            ->orderBy('outlet_name')
            ->orderBy('number');

        $reports = $query->get();
        $purposeMap = collect($this->purposeOptions())->pluck('label', 'value');
        $statusMap = collect($this->statusOptions())->pluck('label', 'value');
        $rows = [];

        foreach ($reports as $report) {
            foreach ($report->items as $item) {
                if (! empty($validated['purpose']) && $item->purpose !== $validated['purpose']) {
                    continue;
                }

                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        $report->number,
                        $report->outlet_name,
                        $item->product_name,
                        (string) $item->category,
                    ]));
                    if (! str_contains($haystack, mb_strtolower($search))) {
                        continue;
                    }
                }

                $rows[] = [
                    'report_number' => $report->number,
                    'report_month' => $report->report_month?->format('Y-m'),
                    'outlet' => $report->outlet_name,
                    'status' => $report->status,
                    'status_label' => $statusMap[$report->status] ?? $report->status,
                    'created_by' => $report->creator?->nama_lengkap ?? '-',
                    'product_name' => $item->product_name,
                    'category' => $item->category ?: '-',
                    'pics' => $this->formatPics($item->pics),
                    'development_date' => $item->development_date?->format('Y-m-d'),
                    'purpose' => $item->purpose,
                    'purpose_label' => $purposeMap[$item->purpose] ?? $item->purpose,
                    'proposed_launch_date' => $item->proposed_launch_date?->format('Y-m-d'),
                    'launch_outlets' => $this->formatLaunchOutlets($item->proposed_launch_area_outlet),
                    'fb_cost' => (float) $item->fb_cost,
                    'selling_price' => (float) $item->selling_price,
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
                'outlet_id' => $validated['outlet_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'search' => $search !== '' ? $search : null,
            ],
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function purposeOptions(): array
    {
        return [
            ['value' => 'enhancement', 'label' => 'Enhancement'],
            ['value' => 'new_product', 'label' => 'New Product'],
            ['value' => 'adjustment', 'label' => 'Adjustment'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Semua Status'],
            ['value' => 'submitted', 'label' => 'Submitted'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Not Approved'],
            ['value' => 'requires_revision', 'label' => 'Requires Revision'],
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
     * @param  mixed  $outlets
     */
    private function formatLaunchOutlets($outlets): string
    {
        if (! is_array($outlets) || empty($outlets)) {
            return '-';
        }

        return collect($outlets)
            ->map(fn ($entry) => $entry['name'] ?? $entry['nama_outlet'] ?? '')
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
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'status' => ['nullable', Rule::in(['', 'submitted', 'approved', 'rejected', 'requires_revision'])],
            'purpose' => ['nullable', Rule::in(['', 'enhancement', 'new_product', 'adjustment'])],
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
