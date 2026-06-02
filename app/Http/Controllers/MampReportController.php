<?php

namespace App\Http\Controllers;

use App\Exports\MampReportExport;
use App\Models\PurchaseRequisitionCategory;
use App\Services\MampReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MampReportController extends Controller
{
    public function __construct(
        private readonly MampReportService $mampReportService
    ) {}

    public function index(Request $request)
    {
        $year = (int) $request->input('year', (int) date('Y'));
        $month = (int) $request->input('month', (int) date('n'));
        $categoryId = (int) $request->input('category_id', 0);

        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        $categories = PurchaseRequisitionCategory::active()
            ->orderBy('division')
            ->orderBy('name')
            ->get(['id', 'name', 'division', 'subcategory', 'budget_limit']);

        $report = null;
        if ($categoryId > 0) {
            $report = $this->mampReportService->build($categoryId, $year, $month);
        }

        return Inertia::render('MampReport/Index', [
            'categories' => $categories,
            'filters' => [
                'category_id' => $categoryId ?: null,
                'year' => $year,
                'month' => $month,
            ],
            'report' => $report,
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
        $categoryName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['category']['name'] ?? 'category');
        $fileName = sprintf('MAMP_%s_%d-%02d.xlsx', $categoryName, $year, $month);

        return Excel::download(
            new MampReportExport($report),
            $fileName
        );
    }
}
