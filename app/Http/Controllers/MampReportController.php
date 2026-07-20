<?php

namespace App\Http\Controllers;

use App\Exports\MampReportExport;
use App\Models\PurchaseRequisitionCategory;
use App\Services\MampReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MampReportController extends Controller
{
    public function __construct(
        private readonly MampReportService $mampReportService
    ) {}

    public function index(Request $request)
    {
        $view = (string) $request->input('view', 'outlet');
        if (! in_array($view, ['detail', 'outlet', 'outlet_category'], true)) {
            $view = 'outlet';
        }

        $year = (int) $request->input('year', (int) date('Y'));
        $month = (int) $request->input('month', (int) date('n'));
        $categoryId = (int) $request->input('category_id', 0);
        $outletId = (int) $request->input('outlet_id', 0);
        $dateFrom = (string) $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        try {
            $dateFrom = Carbon::parse($dateFrom)->format('Y-m-d');
            $dateTo = Carbon::parse($dateTo)->format('Y-m-d');
        } catch (\Throwable) {
            $dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
            $dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $categories = PurchaseRequisitionCategory::active()
            ->orderBy('division')
            ->orderBy('name')
            ->get(['id', 'name', 'division', 'subcategory', 'budget_limit']);

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet'])
            ->map(fn ($outlet) => [
                'id' => (int) $outlet->id_outlet,
                'name' => (string) $outlet->nama_outlet,
            ])
            ->values();

        $outletSummary = $this->mampReportService->buildOutletSummary(
            $year,
            $month,
            $categoryId > 0 ? $categoryId : null
        );

        $report = null;
        if ($categoryId > 0) {
            $report = $this->mampReportService->build($categoryId, $year, $month);
        }

        $outletCategorySummary = null;
        if ($view === 'outlet_category' && $outletId > 0) {
            $outletCategorySummary = $this->mampReportService->buildOutletCategorySummary(
                $outletId,
                $dateFrom,
                $dateTo
            );
        }

        return Inertia::render('MampReport/Index', [
            'categories' => $categories,
            'outlets' => $outlets,
            'filters' => [
                'view' => $view,
                'category_id' => $categoryId ?: null,
                'year' => $year,
                'month' => $month,
                'outlet_id' => $outletId ?: null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'outlet_summary' => $outletSummary,
            'outlet_category_summary' => $outletCategorySummary,
            'report' => $report,
        ]);
    }

    public function rowItems(Request $request)
    {
        $validated = $request->validate([
            'row_key' => 'required|string|max:100',
            'category_id' => 'required|integer|exists:purchase_requisition_categories,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        return response()->json([
            'items' => $this->mampReportService->fetchRowItems(
                $validated['row_key'],
                (int) $validated['category_id'],
                isset($validated['year']) ? (int) $validated['year'] : null,
                isset($validated['month']) ? (int) $validated['month'] : null
            ),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:purchase_requisition_categories,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $categoryId = (int) $request->category_id;
        $year = (int) $request->year;
        $month = (int) $request->month;

        $report = $this->mampReportService->build($categoryId, $year, $month);
        $report['outlet_summary'] = $this->mampReportService->buildOutletSummary($year, $month, $categoryId);
        $categoryName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['category']['name'] ?? 'category');
        $fileName = sprintf('MAMP_%s_%d-%02d.xlsx', $categoryName, $year, $month);

        return Excel::download(
            new MampReportExport($report),
            $fileName
        );
    }
}
