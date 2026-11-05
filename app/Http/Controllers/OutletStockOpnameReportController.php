<?php

namespace App\Http\Controllers;

use App\Exports\OutletStockOpnameReportExport;
use App\Models\StockOpname;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class OutletStockOpnameReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $filters = $this->resolveFilters($request, $user);
        $loadData = (bool) $request->input('load', false);

        $outlets = $this->getOutlets($user);
        $warehouseOutlets = $this->getWarehouseOutlets($filters['outlet_id']);

        $stockOpnames = collect([]);
        $totalItems = 0;
        $totals = $this->emptyTotals();

        if ($loadData) {
            $query = $this->buildQuery($filters);
            $totalItems = $query->count();

            $stockOpnames = (clone $query)
                ->orderBy('opname_date', 'desc')
                ->orderBy('id', 'desc')
                ->skip(($filters['page'] - 1) * $filters['per_page'])
                ->take($filters['per_page'])
                ->get();

            $totals = $this->calculateTotals($filters);
        }

        $reportData = $this->formatReportData($stockOpnames);
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $filters['per_page']) : 0;

        return Inertia::render('OutletStockOpnameReport/Index', [
            'stockOpnames' => $reportData,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'totals' => $totals,
            'pagination' => [
                'current_page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'outlet_id' => $filters['outlet_id'],
                'warehouse_outlet_id' => $filters['warehouse_outlet_id'],
                'date' => $filters['date'],
                'search' => $filters['search'],
                'per_page' => $filters['per_page'],
                'page' => $filters['page'],
            ],
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $filters = $this->resolveFilters($request, $user);
        $stockOpnames = $this->formatReportData($this->fetchAllForExport($filters));

        if ($stockOpnames->isEmpty()) {
            return back()->with('error', 'Tidak ada data untuk diekspor. Silakan Load Data terlebih dahulu.');
        }

        $rows = [];
        $no = 1;
        foreach ($stockOpnames as $opname) {
            foreach ($opname['items'] as $item) {
                $rows[] = [
                    $no++,
                    $opname['opname_number'],
                    $opname['opname_date'],
                    $opname['outlet'],
                    $opname['warehouse_outlet'],
                    $opname['status'],
                    $item['item_code'],
                    $item['item_name'],
                    $item['category_name'],
                    $item['qty_system_small'],
                    $item['qty_system_medium'],
                    $item['qty_system_large'],
                    $item['qty_physical_small'],
                    $item['qty_physical_medium'],
                    $item['qty_physical_large'],
                    $item['qty_diff_small'],
                    $item['qty_diff_medium'],
                    $item['qty_diff_large'],
                    $item['mac_before'],
                    $item['value_adjustment'],
                    $item['reason'] ?: '-',
                ];
            }
        }

        $filename = 'outlet-stock-opname-report-' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new OutletStockOpnameReportExport($rows), $filename);
    }

    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $filters = $this->resolveFilters($request, $user);
        $stockOpnames = $this->formatReportData($this->fetchAllForExport($filters));

        if ($stockOpnames->isEmpty()) {
            return back()->with('error', 'Tidak ada data untuk diekspor. Silakan Load Data terlebih dahulu.');
        }

        $outletName = null;
        if ($filters['outlet_id']) {
            $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $filters['outlet_id'])->value('nama_outlet');
        }

        $warehouseName = null;
        if ($filters['warehouse_outlet_id']) {
            $warehouseName = DB::table('warehouse_outlets')->where('id', $filters['warehouse_outlet_id'])->value('name');
        }

        $pdf = Pdf::loadView('exports.outlet_stock_opname_report_pdf', [
            'stockOpnames' => $stockOpnames,
            'totals' => $this->calculateTotals($filters),
            'filters' => [
                'outlet_name' => $outletName,
                'warehouse_name' => $warehouseName,
                'date' => $filters['date'],
                'search' => $filters['search'],
            ],
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'outlet-stock-opname-report-' . now()->format('YmdHis') . '.pdf';

        return $pdf->download($filename);
    }

    private function resolveFilters(Request $request, $user): array
    {
        $outletId = $request->input('outlet_id');
        if ($user->id_outlet != 1) {
            $outletId = $user->id_outlet;
        }

        return [
            'outlet_id' => $outletId,
            'warehouse_outlet_id' => $request->input('warehouse_outlet_id'),
            'date' => $request->input('date'),
            'search' => $request->input('search', ''),
            'per_page' => (int) $request->input('per_page', 50),
            'page' => max(1, (int) $request->input('page', 1)),
        ];
    }

    private function getOutlets($user)
    {
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
        }

        return $outletsQuery->get();
    }

    private function getWarehouseOutlets($outletId)
    {
        if (!$outletId) {
            return [];
        }

        return DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function buildQuery(array $filters)
    {
        $query = StockOpname::with([
            'outlet',
            'warehouseOutlet',
            'creator',
            'items.inventoryItem.item',
            'items.inventoryItem.item.category',
            'items.inventoryItem.item.smallUnit',
            'items.inventoryItem.item.mediumUnit',
            'items.inventoryItem.item.largeUnit',
            'approvalFlows.approver',
        ]);

        if ($filters['outlet_id']) {
            $query->where('outlet_id', $filters['outlet_id']);
        }

        if ($filters['warehouse_outlet_id']) {
            $query->where('warehouse_outlet_id', $filters['warehouse_outlet_id']);
        }

        if ($filters['date']) {
            $query->whereDate('opname_date', $filters['date']);
        }

        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('opname_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('items.inventoryItem.item', function ($itemQuery) use ($search) {
                        $itemQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function fetchAllForExport(array $filters)
    {
        return $this->buildQuery($filters)
            ->orderBy('opname_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    private function formatReportData($stockOpnames)
    {
        return $stockOpnames->map(function ($stockOpname) {
            $items = $stockOpname->items->map(function ($item) {
                $inventoryItem = $item->inventoryItem ?? null;
                $itemData = $inventoryItem->item ?? null;
                $category = $itemData->category ?? null;

                return [
                    'id' => $item->id,
                    'item_code' => $itemData->sku ?? '-',
                    'item_name' => $itemData->name ?? '-',
                    'category_name' => $category->name ?? '-',
                    'qty_system_small' => (float) $item->qty_system_small,
                    'qty_system_medium' => (float) $item->qty_system_medium,
                    'qty_system_large' => (float) $item->qty_system_large,
                    'qty_physical_small' => (float) $item->qty_physical_small,
                    'qty_physical_medium' => (float) $item->qty_physical_medium,
                    'qty_physical_large' => (float) $item->qty_physical_large,
                    'qty_diff_small' => (float) $item->qty_diff_small,
                    'qty_diff_medium' => (float) $item->qty_diff_medium,
                    'qty_diff_large' => (float) $item->qty_diff_large,
                    'reason' => $item->reason,
                    'mac_before' => (float) $item->mac_before,
                    'mac_after' => (float) $item->mac_after,
                    'value_adjustment' => (float) $item->value_adjustment,
                    'small_unit_name' => $itemData && $itemData->smallUnit ? $itemData->smallUnit->name : '-',
                    'medium_unit_name' => $itemData && $itemData->mediumUnit ? $itemData->mediumUnit->name : '-',
                    'large_unit_name' => $itemData && $itemData->largeUnit ? $itemData->largeUnit->name : '-',
                ];
            });

            $approvers = $stockOpname->approvalFlows->map(function ($flow) {
                return [
                    'level' => $flow->approval_level,
                    'name' => $flow->approver->nama_lengkap ?? '-',
                    'status' => $flow->status,
                    'approved_at' => $flow->approved_at ? Carbon::parse($flow->approved_at)->format('Y-m-d H:i:s') : null,
                ];
            })->sortBy('level')->values();

            return [
                'id' => $stockOpname->id,
                'opname_number' => $stockOpname->opname_number,
                'opname_date' => $stockOpname->opname_date ? Carbon::parse($stockOpname->opname_date)->format('Y-m-d') : null,
                'outlet' => $stockOpname->outlet->nama_outlet ?? '-',
                'warehouse_outlet' => $stockOpname->warehouseOutlet->name ?? '-',
                'status' => $stockOpname->status,
                'notes' => $stockOpname->notes,
                'created_by' => $stockOpname->creator->nama_lengkap ?? '-',
                'created_at' => $stockOpname->created_at ? Carbon::parse($stockOpname->created_at)->format('Y-m-d H:i:s') : null,
                'items' => $items,
                'items_count' => $items->count(),
                'approvers' => $approvers,
                'total_value_adjustment' => $items->sum('value_adjustment'),
            ];
        });
    }

    private function emptyTotals(): array
    {
        return [
            'total_qty_diff_small' => 0,
            'total_qty_diff_medium' => 0,
            'total_qty_diff_large' => 0,
            'total_value_adjustment' => 0,
        ];
    }

    private function calculateTotals(array $filters): array
    {
        $totals = DB::table('outlet_stock_opname_items as items')
            ->join('outlet_stock_opnames as opnames', 'items.stock_opname_id', '=', 'opnames.id')
            ->when($filters['outlet_id'], function ($q) use ($filters) {
                $q->where('opnames.outlet_id', $filters['outlet_id']);
            })
            ->when($filters['warehouse_outlet_id'], function ($q) use ($filters) {
                $q->where('opnames.warehouse_outlet_id', $filters['warehouse_outlet_id']);
            })
            ->when($filters['date'], function ($q) use ($filters) {
                $q->whereDate('opnames.opname_date', $filters['date']);
            })
            ->when($filters['search'], function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('opnames.opname_number', 'like', "%{$search}%")
                        ->orWhere('opnames.notes', 'like', "%{$search}%");
                });
            })
            ->select(
                DB::raw('SUM(items.qty_diff_small) as total_qty_diff_small'),
                DB::raw('SUM(items.qty_diff_medium) as total_qty_diff_medium'),
                DB::raw('SUM(items.qty_diff_large) as total_qty_diff_large'),
                DB::raw('SUM(items.value_adjustment) as total_value_adjustment')
            )
            ->first();

        return [
            'total_qty_diff_small' => (float) ($totals->total_qty_diff_small ?? 0),
            'total_qty_diff_medium' => (float) ($totals->total_qty_diff_medium ?? 0),
            'total_qty_diff_large' => (float) ($totals->total_qty_diff_large ?? 0),
            'total_value_adjustment' => (float) ($totals->total_value_adjustment ?? 0),
        ];
    }
}
