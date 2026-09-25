<?php

namespace App\Http\Controllers;

use App\Exports\RwsUnreceivedReportExport;
use App\Services\RwsRetailFoodReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class RwsUnreceivedReportController extends Controller
{
    public function __construct(
        protected RwsRetailFoodReconciliationService $reconciliation,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->normalizeFilters($request);
        $loadData = (bool) $request->boolean('load_data');

        $outlets = $this->reconciliation->branchOutlets();

        if (! $loadData) {
            return Inertia::render('RetailWarehouseSale/UnreceivedReport', [
                'rows' => [],
                'by_outlet' => [],
                'summary' => ['total_rws' => 0, 'total_amount' => 0, 'outlet_count' => 0],
                'outlets' => $outlets,
                'filters' => $filters,
                'dataLoaded' => false,
            ]);
        }

        $result = $this->reconciliation->unmatchedRws([
            ...$filters,
            'include_items' => true,
        ]);

        return Inertia::render('RetailWarehouseSale/UnreceivedReport', [
            'rows' => $result['rows']->values()->all(),
            'by_outlet' => $result['by_outlet']->values()->all(),
            'summary' => $result['summary'],
            'outlets' => $outlets,
            'filters' => $filters,
            'dataLoaded' => true,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->normalizeFilters($request);

        if (empty($filters['from_date']) || empty($filters['to_date'])) {
            return redirect()->back()->with('error', 'Pilih periode tanggal terlebih dahulu.');
        }

        $result = $this->reconciliation->unmatchedRws([
            ...$filters,
            'include_items' => true,
        ]);

        $filename = sprintf(
            'RWS_Belum_Diterima_%s_%s_%s.xlsx',
            $filters['from_date'],
            $filters['to_date'],
            now()->format('Ymd_His')
        );

        return Excel::download(
            new RwsUnreceivedReportExport(
                $result['rows'],
                $result['by_outlet'],
                $result['summary'],
                $filters,
            ),
            $filename
        );
    }

    /**
     * @return array{from_date:?string,to_date:?string,outlet_id:?int,search:?string}
     */
    protected function normalizeFilters(Request $request): array
    {
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        if (! $from && ! $to) {
            $from = now()->startOfMonth()->format('Y-m-d');
            $to = now()->format('Y-m-d');
        }

        return [
            'from_date' => $from ?: null,
            'to_date' => $to ?: null,
            'outlet_id' => $request->filled('outlet_id') ? (int) $request->input('outlet_id') : null,
            'search' => $request->input('search') ?: null,
        ];
    }
}
