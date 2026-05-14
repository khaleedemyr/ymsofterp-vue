<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FoodGoodReceiveReportController extends Controller
{
    public function index(Request $request)
    {
        // Get main GR data
        $grQuery = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.notes',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                's.code as supplier_code',
                'u.nama_lengkap as received_by_name',
                'gr.created_at',
                DB::raw('(SELECT COUNT(*) FROM food_good_receive_items WHERE good_receive_id = gr.id) as total_items')
            );

        // Filter berdasarkan tanggal
        if ($request->filled('from_date')) {
            $grQuery->whereDate('gr.receive_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $grQuery->whereDate('gr.receive_date', '<=', $request->to_date);
        }

        // Filter berdasarkan supplier
        if ($request->filled('supplier_id')) {
            $grQuery->where('gr.supplier_id', $request->supplier_id);
        }

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $grQuery->where('gr.status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $grQuery->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('po.number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $grResults = $grQuery->orderBy('gr.receive_date', 'desc')
                            ->orderBy('gr.gr_number', 'desc')
                            ->paginate($perPage)
                            ->withQueryString();

        // Get items for each GR (will be loaded on demand)
        $grIds = $grResults->pluck('id')->toArray();
        
        $itemsData = [];
        if (!empty($grIds)) {
            $itemsQuery = DB::table('food_good_receive_items as gri')
                ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
                ->leftJoin('units as u_item', 'gri.unit_id', '=', 'u_item.id')
                ->select(
                    'gri.good_receive_id',
                    'gri.id as item_id',
                    'i.name as item_name',
                    'i.sku as item_sku',
                    'gri.qty_ordered',
                    'gri.qty_received',
                    DB::raw('(gri.qty_received - gri.used_qty) as remaining_qty'),
                    'u_item.name as unit_name'
                )
                ->whereIn('gri.good_receive_id', $grIds);

            // Filter berdasarkan item jika ada
            if ($request->filled('item_id')) {
                $itemsQuery->where('gri.item_id', $request->item_id);
            }

            $items = $itemsQuery->get();
            
            // Group items by good_receive_id
            foreach ($items as $item) {
                $grId = $item->good_receive_id;
                if (!isset($itemsData[$grId])) {
                    $itemsData[$grId] = [];
                }
                $itemsData[$grId][] = $item;
            }
        }

        // Get summary data
        $summary = DB::table('food_good_receives as gr')
            ->leftJoin('food_good_receive_items as gri', 'gr.id', '=', 'gri.good_receive_id')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->when($request->filled('from_date'), function($q) use ($request) {
                $q->whereDate('gr.receive_date', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function($q) use ($request) {
                $q->whereDate('gr.receive_date', '<=', $request->to_date);
            })
            ->when($request->filled('supplier_id'), function($q) use ($request) {
                $q->where('gr.supplier_id', $request->supplier_id);
            })
            ->when($request->filled('item_id'), function($q) use ($request) {
                $q->where('gri.item_id', $request->item_id);
            })
            ->when($request->filled('status'), function($q) use ($request) {
                $q->where('gr.status', $request->status);
            })
            ->when($request->filled('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where(function($subQ) use ($search) {
                    $subQ->where('gr.gr_number', 'like', "%$search%")
                         ->orWhere('po.number', 'like', "%$search%")
                         ->orWhere('s.name', 'like', "%$search%");
                });
            })
            ->select(
                DB::raw('COUNT(DISTINCT gr.id) as total_gr'),
                DB::raw('SUM(gri.qty_received) as total_qty_received')
            )
            ->first();

        // Get filter options
        $suppliers = DB::table('suppliers')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $items = DB::table('items')
            ->select('id', 'name', 'sku')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('FoodGoodReceive/Report', [
            'results' => $grResults,
            'itemsData' => $itemsData,
            'summary' => $summary,
            'suppliers' => $suppliers,
            'items' => $items,
            'filters' => $request->only(['from_date', 'to_date', 'supplier_id', 'item_id', 'status', 'search', 'per_page']),
        ]);
    }

    public function reportSupplierSpending(Request $request)
    {
        $suppliers = DB::table('suppliers')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $warehouseDivisions = DB::table('warehouse_division')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $filtersPayload = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'supplier_id' => $request->input('supplier_id'),
            'search' => $request->input('search'),
            'warehouse_outlet_id' => $request->input('warehouse_outlet_id'),
            'warehouse_division_id' => $request->input('warehouse_division_id'),
        ];

        if (! $request->boolean('load')) {
            return Inertia::render('FoodGoodReceive/ReportSupplierSpending', [
                'supplierReports' => [],
                'suppliers' => $suppliers,
                'warehouse_outlets' => $warehouseOutlets,
                'warehouse_divisions' => $warehouseDivisions,
                'summary' => [
                    'total_suppliers' => 0,
                    'total_transactions' => 0,
                    'grand_total_amount' => 0,
                ],
                'filters' => $filtersPayload,
                'data_loaded' => false,
            ]);
        }

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'warehouse_outlet_id' => 'nullable|integer|exists:warehouse_outlets,id',
            'warehouse_division_id' => 'nullable|integer|exists:warehouse_division,id',
        ]);

        $rows = $this->getSupplierSpendingRows($request);

        $itemsByGr = $this->getSupplierSpendingLineItems($rows->pluck('good_receive_id')->unique()->values(), $request);

        $supplierReports = $rows
            ->groupBy('supplier_id')
            ->map(function ($supplierRows) use ($itemsByGr) {
                $first = $supplierRows->first();

                $mapTransaction = function ($row) use ($itemsByGr) {
                    return [
                        'good_receive_id' => $row->good_receive_id,
                        'gr_number' => $row->gr_number,
                        'receive_date' => $row->receive_date,
                        'po_number' => $row->po_number,
                        'gr_created_at' => $row->gr_created_at ?? null,
                        'po_created_at' => $row->po_created_at ?? null,
                        'pr_numbers' => $row->pr_numbers ?? null,
                        'ro_order_numbers' => $row->ro_order_numbers ?? null,
                        'fo_outlet_names' => $row->fo_outlet_names ?? null,
                        'fo_creator_names' => $row->fo_creator_names ?? null,
                        'po_created_by_name' => $row->po_created_by_name ?? null,
                        'gr_received_by_name' => $row->gr_received_by_name ?? null,
                        'pr_requester_names' => $row->pr_requester_names ?? null,
                        'total_amount' => (float) $row->total_amount,
                        'items' => $itemsByGr->get($row->good_receive_id, collect())->values()->all(),
                    ];
                };

                $byDate = $supplierRows
                    ->groupBy(function ($row) {
                        return $row->receive_date
                            ? date('Y-m-d', strtotime((string) $row->receive_date))
                            : 'unknown';
                    })
                    ->map(function ($dayRows, $dateKey) use ($mapTransaction) {
                        $sortedTrx = $dayRows
                            ->sortByDesc('gr_number')
                            ->values()
                            ->map($mapTransaction);

                        return [
                            'date' => $dateKey,
                            'total_amount' => (float) $dayRows->sum('total_amount'),
                            'transaction_count' => $dayRows->count(),
                            'transactions' => $sortedTrx,
                        ];
                    })
                    ->sortKeysDesc()
                    ->values();

                return [
                    'supplier_id' => $first->supplier_id,
                    'supplier_name' => $first->supplier_name,
                    'supplier_code' => $first->supplier_code,
                    'total_amount' => (float) $supplierRows->sum('total_amount'),
                    'total_transactions' => $supplierRows->count(),
                    'days' => $byDate,
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        $summary = [
            'total_suppliers' => $supplierReports->count(),
            'total_transactions' => $supplierReports->sum('total_transactions'),
            'grand_total_amount' => (float) $supplierReports->sum('total_amount'),
        ];

        return Inertia::render('FoodGoodReceive/ReportSupplierSpending', [
            'supplierReports' => $supplierReports,
            'suppliers' => $suppliers,
            'warehouse_outlets' => $warehouseOutlets,
            'warehouse_divisions' => $warehouseDivisions,
            'summary' => $summary,
            'filters' => $filtersPayload,
            'data_loaded' => true,
        ]);
    }

    public function exportSupplierSpending(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'warehouse_outlet_id' => 'nullable|integer|exists:warehouse_outlets,id',
            'warehouse_division_id' => 'nullable|integer|exists:warehouse_division,id',
        ]);

        $rows = $this->getSupplierSpendingRows($request);

        $itemsByGr = $this->getSupplierSpendingLineItems($rows->pluck('good_receive_id')->unique()->values(), $request);

        $emptyLine = [
            'item_name' => '',
            'unit_name' => '',
            'qty_pr' => null,
            'qty_po' => null,
            'qty_gr' => null,
            'line_pr_number' => '',
            'line_pr_created_at' => null,
            'line_ro_number' => '',
            'line_ro_created_at' => null,
            'line_fo_outlet_name' => '',
            'line_fo_creator_name' => '',
            'line_amount' => null,
        ];

        $exportRows = $rows->flatMap(function ($row) use ($itemsByGr, $emptyLine) {
            $base = [
                'supplier_name' => $row->supplier_name,
                'supplier_code' => $row->supplier_code,
                'gr_number' => $row->gr_number,
                'receive_date' => $row->receive_date,
                'gr_created_at' => $row->gr_created_at ?? null,
                'po_number' => $row->po_number,
                'po_created_at' => $row->po_created_at ?? null,
                'pr_numbers' => $row->pr_numbers ?? '',
                'ro_order_numbers' => $row->ro_order_numbers ?? '',
                'fo_outlet_names' => $row->fo_outlet_names ?? '',
                'fo_creator_names' => $row->fo_creator_names ?? '',
                'po_created_by_name' => $row->po_created_by_name ?? '',
                'gr_received_by_name' => $row->gr_received_by_name ?? '',
                'pr_requester_names' => $row->pr_requester_names ?? '',
                'total_amount' => (float) $row->total_amount,
            ];

            $lines = $itemsByGr->get($row->good_receive_id, collect());
            if ($lines->isEmpty()) {
                return [array_merge($base, $emptyLine)];
            }

            return $lines->map(function (array $line) use ($base) {
                return array_merge($base, [
                    'item_name' => $line['item_name'] ?? '',
                    'unit_name' => $line['unit_name'] ?? '',
                    'qty_pr' => $line['qty_pr'],
                    'qty_po' => $line['qty_po'],
                    'qty_gr' => $line['qty_gr'],
                    'line_pr_number' => $line['pr_number'] ?? '',
                    'line_pr_created_at' => $line['pr_created_at'] ?? null,
                    'line_ro_number' => $line['ro_number'] ?? '',
                    'line_ro_created_at' => $line['ro_created_at'] ?? null,
                    'line_fo_outlet_name' => $line['fo_outlet_name'] ?? '',
                    'line_fo_creator_name' => $line['fo_creator_name'] ?? '',
                    'line_amount' => $line['line_amount'],
                ]);
            })->all();
        })->values();

        return (new \App\Exports\FoodGoodReceiveSupplierSpendingExport($exportRows))->toResponse($request);
    }

    /**
     * @return array{date_from: ?string, date_to: ?string}
     */
    protected function supplierSpendingResolvedDates(Request $request): array
    {
        return [
            'date_from' => $request->filled('date_from') ? $request->date_from : null,
            'date_to' => $request->filled('date_to') ? $request->date_to : null,
        ];
    }

    /**
     * PO id yang relevan dengan filter GR (tanggal + supplier) — dipakai untuk membatasi agregasi PR/RO.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function supplierSpendingGrPoIdScope(Request $request)
    {
        $dates = $this->supplierSpendingResolvedDates($request);

        $sub = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->select('gr.po_id')
            ->whereNotNull('gr.po_id');

        if ($dates['date_from']) {
            $sub->whereDate('gr.receive_date', '>=', $dates['date_from']);
        }
        if ($dates['date_to']) {
            $sub->whereDate('gr.receive_date', '<=', $dates['date_to']);
        }
        if ($request->filled('supplier_id')) {
            $sub->where('gr.supplier_id', $request->supplier_id);
        }
        if ($request->filled('warehouse_outlet_id')) {
            $sub->where('po.warehouse_outlet_id', $request->warehouse_outlet_id);
        }
        $this->applySupplierSpendingDivisionExistsFilter($request, $sub);

        return $sub->distinct();
    }

    /**
     * Filter GR by divisi gudang (sumber PR → pr_foods.warehouse_division_id), konsisten dengan grouping item di GR Form.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query yang sudah memakai alias `gr` untuk food_good_receives
     */
    protected function applySupplierSpendingDivisionExistsFilter(Request $request, $query): void
    {
        if (! $request->filled('warehouse_division_id')) {
            return;
        }
        $divId = (int) $request->warehouse_division_id;
        $query->whereExists(function ($ex) use ($divId) {
            $ex->select(DB::raw('1'))
                ->from('food_good_receive_items as gri_div')
                ->join('purchase_order_food_items as poi_div', 'gri_div.po_item_id', '=', 'poi_div.id')
                ->join('pr_food_items as pfi_div', 'poi_div.pr_food_item_id', '=', 'pfi_div.id')
                ->join('pr_foods as pr_div', 'pfi_div.pr_food_id', '=', 'pr_div.id')
                ->whereColumn('gri_div.good_receive_id', 'gr.id')
                ->where('pr_div.warehouse_division_id', $divId);
        });
    }

    /**
     * Agregasi PR + pemohon per PO (join sekali, bukan subquery per baris hasil).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function supplierSpendingPrMetaSubquery($grPoIdScope)
    {
        return DB::table('purchase_order_food_items as poi_agg')
            ->join('pr_food_items as pri', 'pri.id', '=', 'poi_agg.pr_food_item_id')
            ->join('pr_foods as pr', 'pr.id', '=', 'pri.pr_food_id')
            ->leftJoin('users as ureq', 'pr.requested_by', '=', 'ureq.id')
            ->whereNotNull('poi_agg.pr_food_item_id')
            ->whereIn('poi_agg.purchase_order_food_id', $grPoIdScope)
            ->groupBy('poi_agg.purchase_order_food_id')
            ->select(
                'poi_agg.purchase_order_food_id as po_id',
                DB::raw('GROUP_CONCAT(DISTINCT pr.pr_number ORDER BY pr.pr_number SEPARATOR ", ") as pr_numbers'),
                DB::raw('GROUP_CONCAT(DISTINCT ureq.nama_lengkap ORDER BY ureq.nama_lengkap SEPARATOR ", ") as pr_requester_names')
            );
    }

    /**
     * Agregasi nomor RO per PO.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function supplierSpendingRoMetaSubquery($grPoIdScope)
    {
        return DB::table('purchase_order_food_items as poi_ro')
            ->join('food_floor_orders as fo', 'fo.id', '=', 'poi_ro.ro_id')
            ->whereNotNull('poi_ro.ro_id')
            ->whereIn('poi_ro.purchase_order_food_id', $grPoIdScope)
            ->groupBy('poi_ro.purchase_order_food_id')
            ->select(
                'poi_ro.purchase_order_food_id as po_id',
                DB::raw('GROUP_CONCAT(DISTINCT fo.order_number ORDER BY fo.order_number SEPARATOR ", ") as ro_order_numbers')
            );
    }

    /**
     * Outlet + pembuat food_floor_order per PO (baris PO yang punya ro_id).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function supplierSpendingFloorOrderMetaSubquery($grPoIdScope)
    {
        return DB::table('purchase_order_food_items as poi_fo')
            ->join('food_floor_orders as fo', 'fo.id', '=', 'poi_fo.ro_id')
            ->leftJoin('tbl_data_outlet as tout', 'tout.id_outlet', '=', 'fo.id_outlet')
            ->leftJoin('users as u_fo', 'u_fo.id', '=', 'fo.user_id')
            ->whereNotNull('poi_fo.ro_id')
            ->whereIn('poi_fo.purchase_order_food_id', $grPoIdScope)
            ->groupBy('poi_fo.purchase_order_food_id')
            ->select(
                'poi_fo.purchase_order_food_id as po_id',
                DB::raw('GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(tout.nama_outlet, "")), "") ORDER BY tout.nama_outlet SEPARATOR ", ") as fo_outlet_names'),
                DB::raw('GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(u_fo.nama_lengkap, "")), "") ORDER BY u_fo.nama_lengkap SEPARATOR ", ") as fo_creator_names')
            );
    }

    /**
     * Detail baris GR: item + qty PR/PO/GR + metadata dokumen per baris.
     *
     * @return Collection<int|string, \Illuminate\Support\Collection<int, array<string, mixed>>>
     */
    protected function getSupplierSpendingLineItems(Collection $grIds, ?Request $request = null): Collection
    {
        if ($grIds->isEmpty()) {
            return collect();
        }

        $linesQuery = DB::table('food_good_receive_items as gri')
            ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->leftJoin('purchase_order_foods as pof', 'poi.purchase_order_food_id', '=', 'pof.id')
            ->leftJoin('pr_food_items as pfi', 'poi.pr_food_item_id', '=', 'pfi.id')
            ->leftJoin('pr_foods as pr', 'pfi.pr_food_id', '=', 'pr.id')
            ->leftJoin('food_floor_orders as fo', 'poi.ro_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as tout', 'tout.id_outlet', '=', 'fo.id_outlet')
            ->leftJoin('users as u_fo', 'u_fo.id', '=', 'fo.user_id')
            ->whereIn('gri.good_receive_id', $grIds->all());

        if ($request && $request->filled('warehouse_division_id')) {
            $linesQuery->where('pr.warehouse_division_id', (int) $request->warehouse_division_id);
        }

        $lines = $linesQuery
            ->orderBy('gri.id')
            ->select(
                'gri.good_receive_id',
                'i.name as item_name',
                'u.name as unit_name',
                'gri.qty_received as qty_gr',
                'poi.quantity as qty_po',
                'pfi.qty as qty_pr',
                'pr.pr_number',
                DB::raw('COALESCE(pr.created_at, pr.tanggal) as pr_datetime'),
                'pof.created_at as po_created_at',
                'gr.created_at as gr_created_at',
                'gr.gr_number',
                'pof.number as po_number',
                'fo.order_number as ro_number',
                'fo.created_at as ro_created_at',
                'tout.nama_outlet as fo_outlet_name',
                'u_fo.nama_lengkap as fo_creator_name',
                DB::raw('(gri.qty_received * COALESCE(poi.price, 0)) as line_amount')
            )
            ->get();

        return $lines->groupBy('good_receive_id')->map(function ($group) {
            return $group->map(function ($l) {
                return [
                    'item_name' => $l->item_name,
                    'unit_name' => $l->unit_name,
                    'qty_pr' => $l->qty_pr !== null ? (float) $l->qty_pr : null,
                    'qty_po' => $l->qty_po !== null ? (float) $l->qty_po : null,
                    'qty_gr' => (float) $l->qty_gr,
                    'pr_number' => $l->pr_number,
                    'pr_created_at' => $l->pr_datetime,
                    'po_number' => $l->po_number,
                    'po_created_at' => $l->po_created_at,
                    'gr_number' => $l->gr_number,
                    'gr_created_at' => $l->gr_created_at,
                    'ro_number' => $l->ro_number,
                    'ro_created_at' => $l->ro_created_at,
                    'fo_outlet_name' => $l->fo_outlet_name,
                    'fo_creator_name' => $l->fo_creator_name,
                    'line_amount' => (float) $l->line_amount,
                ];
            })->values();
        });
    }

    /**
     * @return Collection<int, object>
     */
    protected function getSupplierSpendingRows(Request $request): Collection
    {
        $query = $this->supplierSpendingBaseQuery($request);

        return $query
            ->groupBy(
                's.id',
                's.name',
                's.code',
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'po.number'
            )
            ->orderByDesc('gr.receive_date')
            ->orderByDesc('gr.gr_number')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function supplierSpendingBaseQuery(Request $request)
    {
        $grPoIdScope = $this->supplierSpendingGrPoIdScope($request);

        $query = DB::table('food_good_receives as gr')
            ->join('food_good_receive_items as gri', 'gr.id', '=', 'gri.good_receive_id')
            ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('users as u_po_creator', 'po.created_by', '=', 'u_po_creator.id')
            ->leftJoin('users as u_gr_recv', 'gr.received_by', '=', 'u_gr_recv.id')
            ->leftJoinSub($this->supplierSpendingPrMetaSubquery($grPoIdScope), 'pr_meta', function ($join) {
                $join->on('pr_meta.po_id', '=', 'gr.po_id');
            })
            ->leftJoinSub($this->supplierSpendingRoMetaSubquery($grPoIdScope), 'ro_meta', function ($join) {
                $join->on('ro_meta.po_id', '=', 'gr.po_id');
            })
            ->leftJoinSub($this->supplierSpendingFloorOrderMetaSubquery($grPoIdScope), 'fo_meta', function ($join) {
                $join->on('fo_meta.po_id', '=', 'gr.po_id');
            })
            ->select(
                's.id as supplier_id',
                DB::raw('COALESCE(s.name, "Tanpa Supplier") as supplier_name'),
                DB::raw('COALESCE(s.code, "-") as supplier_code'),
                'gr.id as good_receive_id',
                'gr.gr_number',
                'gr.receive_date',
                'po.number as po_number',
                DB::raw('MAX(gr.created_at) as gr_created_at'),
                DB::raw('MAX(po.created_at) as po_created_at'),
                DB::raw('MAX(u_po_creator.nama_lengkap) as po_created_by_name'),
                DB::raw('MAX(u_gr_recv.nama_lengkap) as gr_received_by_name'),
                DB::raw('MAX(pr_meta.pr_numbers) as pr_numbers'),
                DB::raw('MAX(pr_meta.pr_requester_names) as pr_requester_names'),
                DB::raw('MAX(ro_meta.ro_order_numbers) as ro_order_numbers'),
                DB::raw('MAX(fo_meta.fo_outlet_names) as fo_outlet_names'),
                DB::raw('MAX(fo_meta.fo_creator_names) as fo_creator_names'),
                DB::raw('SUM(gri.qty_received * COALESCE(poi.price, 0)) as total_amount')
            );

        $dates = $this->supplierSpendingResolvedDates($request);
        if ($dates['date_from']) {
            $query->whereDate('gr.receive_date', '>=', $dates['date_from']);
        }
        if ($dates['date_to']) {
            $query->whereDate('gr.receive_date', '<=', $dates['date_to']);
        }

        if ($request->filled('supplier_id')) {
            $query->where('gr.supplier_id', $request->supplier_id);
        }

        if ($request->filled('warehouse_outlet_id')) {
            $query->where('po.warehouse_outlet_id', $request->warehouse_outlet_id);
        }
        if ($request->filled('warehouse_division_id')) {
            $divId = (int) $request->warehouse_division_id;
            $query->join('pr_food_items as pfi_div_line', 'poi.pr_food_item_id', '=', 'pfi_div_line.id')
                ->join('pr_foods as pr_div_line', function ($join) use ($divId) {
                    $join->on('pfi_div_line.pr_food_id', '=', 'pr_div_line.id')
                        ->where('pr_div_line.warehouse_division_id', '=', $divId);
                });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $like = '%' . $search . '%';
            $query->where(function ($subQuery) use ($like) {
                $subQuery->where('gr.gr_number', 'like', $like)
                    ->orWhere('po.number', 'like', $like)
                    ->orWhere('s.name', 'like', $like)
                    ->orWhere('s.code', 'like', $like)
                    ->orWhereRaw(
                        'EXISTS (
                            SELECT 1 FROM purchase_order_food_items poi_s
                            INNER JOIN pr_food_items pfi ON pfi.id = poi_s.pr_food_item_id
                            INNER JOIN pr_foods pr ON pr.id = pfi.pr_food_id
                            WHERE poi_s.purchase_order_food_id = gr.po_id
                            AND pr.pr_number LIKE ?
                        )',
                        [$like]
                    )
                    ->orWhereRaw(
                        'EXISTS (
                            SELECT 1 FROM purchase_order_food_items poi_s
                            INNER JOIN food_floor_orders fo ON fo.id = poi_s.ro_id
                            WHERE poi_s.purchase_order_food_id = gr.po_id
                            AND fo.order_number LIKE ?
                        )',
                        [$like]
                    );
            });
        }

        return $query;
    }

    public function export(Request $request)
    {
        // Get all GR data for export
        $grQuery = DB::table('food_good_receives as gr')
            ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
            ->select(
                'gr.id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.notes',
                'po.number as po_number',
                'po.date as po_date',
                's.name as supplier_name',
                's.code as supplier_code',
                'u.nama_lengkap as received_by_name'
            );

        // Apply filters
        if ($request->filled('from_date')) {
            $grQuery->whereDate('gr.receive_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $grQuery->whereDate('gr.receive_date', '<=', $request->to_date);
        }
        if ($request->filled('supplier_id')) {
            $grQuery->where('gr.supplier_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $grQuery->where('gr.status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $grQuery->where(function($q) use ($search) {
                $q->where('gr.gr_number', 'like', "%$search%")
                  ->orWhere('po.number', 'like', "%$search%")
                  ->orWhere('s.name', 'like', "%$search%");
            });
        }

        $grResults = $grQuery->orderBy('gr.receive_date', 'desc')
                            ->orderBy('gr.gr_number', 'desc')
                            ->get();

        // Get items for export
        $grIds = $grResults->pluck('id')->toArray();
        $itemsQuery = DB::table('food_good_receive_items as gri')
            ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('units as u_item', 'gri.unit_id', '=', 'u_item.id')
            ->select(
                'gri.good_receive_id',
                'i.name as item_name',
                'i.sku as item_sku',
                'gri.qty_ordered',
                'gri.qty_received',
                DB::raw('(gri.qty_received - gri.used_qty) as remaining_qty'),
                'u_item.name as unit_name'
            )
            ->whereIn('gri.good_receive_id', $grIds);

        if ($request->filled('item_id')) {
            $itemsQuery->where('gri.item_id', $request->item_id);
        }

        $items = $itemsQuery->get();

        // Combine GR and items data for export
        $exportData = [];
        foreach ($grResults as $gr) {
            $grItems = $items->where('good_receive_id', $gr->id);
            
            if ($grItems->count() > 0) {
                foreach ($grItems as $item) {
                    $exportData[] = [
                        'gr_number' => $gr->gr_number,
                        'receive_date' => $gr->receive_date,
                        'po_number' => $gr->po_number,
                        'po_date' => $gr->po_date,
                        'supplier_name' => $gr->supplier_name,
                        'supplier_code' => $gr->supplier_code,
                        'received_by_name' => $gr->received_by_name,
                        'item_name' => $item->item_name,
                        'item_sku' => $item->item_sku,
                        'qty_ordered' => $item->qty_ordered,
                        'qty_received' => $item->qty_received,
                        'remaining_qty' => $item->remaining_qty,
                        'unit_name' => $item->unit_name,
                        'notes' => $gr->notes
                    ];
                }
            } else {
                // Add GR without items
                $exportData[] = [
                    'gr_number' => $gr->gr_number,
                    'receive_date' => $gr->receive_date,
                    'po_number' => $gr->po_number,
                    'po_date' => $gr->po_date,
                    'supplier_name' => $gr->supplier_name,
                    'supplier_code' => $gr->supplier_code,
                    'received_by_name' => $gr->received_by_name,
                    'item_name' => '',
                    'item_sku' => '',
                    'qty_ordered' => 0,
                    'qty_received' => 0,
                    'remaining_qty' => 0,
                    'unit_name' => '',
                    'notes' => $gr->notes
                ];
            }
        }

        // Return Excel export using the Responsable interface
        return (new \App\Exports\FoodGoodReceiveReportExport(collect($exportData)))->toResponse($request);
    }

    /**
     * Report DO yang belum di GR by outlet
     */
    public function deliveryOrdersNotReceived(Request $request)
    {
        $query = DB::table('delivery_orders as do')
            ->leftJoin('outlet_food_good_receives as gr', function($join) {
                $join->on('gr.delivery_order_id', '=', 'do.id')
                    ->whereNull('gr.deleted_at');
            })
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->whereNull('gr.id') // DO yang belum di GR
            ->select(
                'do.id',
                'do.number as do_number',
                'do.created_at as do_date',
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                DB::raw('COALESCE(wd.name, "Perishable") as division_name'),
                DB::raw('DATEDIFF(NOW(), DATE(do.created_at)) as days_not_received'),
                'fo.fo_mode',
                'u.nama_lengkap as created_by'
            );

        // Filter berdasarkan tanggal DO
        if ($request->filled('from_date')) {
            $query->whereDate('do.created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('do.created_at', '<=', $request->to_date);
        }

        // Filter berdasarkan outlet
        if ($request->filled('outlet_id')) {
            $query->where('o.id_outlet', $request->outlet_id);
        }

        // Filter berdasarkan warehouse outlet
        if ($request->filled('warehouse_outlet_id')) {
            $query->where('fo.warehouse_outlet_id', $request->warehouse_outlet_id);
        }

        // Filter minimal hari belum GR
        if ($request->filled('min_days')) {
            $query->havingRaw('DATEDIFF(NOW(), DATE(do.created_at)) >= ?', [$request->min_days]);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('do.number', 'like', "%$search%")
                    ->orWhere('o.nama_outlet', 'like', "%$search%")
                    ->orWhere('wo.name', 'like', "%$search%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $doResults = $query->orderBy('do.created_at', 'asc')
            ->paginate($perPage)
            ->appends($request->all());

        // Get summary statistics
        $summaryQuery = DB::table('delivery_orders as do')
            ->leftJoin('outlet_food_good_receives as gr', function($join) {
                $join->on('gr.delivery_order_id', '=', 'do.id')
                    ->whereNull('gr.deleted_at');
            })
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->whereNull('gr.id');

        // Apply same filters to summary
        if ($request->filled('from_date')) {
            $summaryQuery->whereDate('do.created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $summaryQuery->whereDate('do.created_at', '<=', $request->to_date);
        }
        if ($request->filled('outlet_id')) {
            $summaryQuery->where('fo.id_outlet', $request->outlet_id);
        }
        if ($request->filled('warehouse_outlet_id')) {
            $summaryQuery->where('fo.warehouse_outlet_id', $request->warehouse_outlet_id);
        }

        $summary = $summaryQuery->select(
            DB::raw('COUNT(DISTINCT do.id) as total_do_not_received'),
            DB::raw('MIN(DATEDIFF(NOW(), DATE(do.created_at))) as min_days'),
            DB::raw('MAX(DATEDIFF(NOW(), DATE(do.created_at))) as max_days'),
            DB::raw('AVG(DATEDIFF(NOW(), DATE(do.created_at))) as avg_days')
        )->first();

        // Get outlet list for filter
        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get();

        // Get warehouse outlet list
        $warehouse_outlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('FoodGoodReceive/DeliveryOrdersNotReceived', [
            'results' => $doResults,
            'summary' => $summary,
            'outlets' => $outlets,
            'warehouse_outlets' => $warehouse_outlets,
            'filters' => $request->only(['from_date', 'to_date', 'outlet_id', 'warehouse_outlet_id', 'min_days', 'search', 'per_page']),
        ]);
    }

    /**
     * Export DO yang belum GR ke Excel
     */
    public function exportDeliveryOrdersNotReceived(Request $request)
    {
        $query = DB::table('delivery_orders as do')
            ->leftJoin('outlet_food_good_receives as gr', function($join) {
                $join->on('gr.delivery_order_id', '=', 'do.id')
                    ->whereNull('gr.deleted_at');
            })
            ->leftJoin('food_floor_orders as fo', 'do.floor_order_id', '=', 'fo.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'fo.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('users as u', 'do.created_by', '=', 'u.id')
            ->whereNull('gr.id')
            ->select(
                'do.number as do_number',
                'do.created_at as do_date',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                DB::raw('COALESCE(wd.name, "Perishable") as division_name'),
                DB::raw('DATEDIFF(NOW(), DATE(do.created_at)) as days_not_received'),
                'fo.fo_mode',
                'u.nama_lengkap as created_by'
            );

        // Apply filters
        if ($request->filled('from_date')) {
            $query->whereDate('do.created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('do.created_at', '<=', $request->to_date);
        }
        if ($request->filled('outlet_id')) {
            $query->where('o.id_outlet', $request->outlet_id);
        }
        if ($request->filled('warehouse_outlet_id')) {
            $query->where('fo.warehouse_outlet_id', $request->warehouse_outlet_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('do.number', 'like', "%$search%")
                    ->orWhere('o.nama_outlet', 'like', "%$search%")
                    ->orWhere('wo.name', 'like', "%$search%");
            });
        }

        $doResults = $query->orderBy('do.created_at', 'asc')->get();

        // Prepare export data
        $exportData = [];
        foreach ($doResults as $do) {
            $exportData[] = [
                'do_number' => $do->do_number,
                'do_date' => $do->do_date,
                'outlet_name' => $do->outlet_name,
                'warehouse_outlet_name' => $do->warehouse_outlet_name,
                'division_name' => $do->division_name,
                'days_not_received' => $do->days_not_received,
                'fo_mode' => $do->fo_mode,
                'created_by' => $do->created_by,
            ];
        }

        // Return Excel export
        return (new \App\Exports\DeliveryOrdersNotReceivedExport(collect($exportData)))->toResponse($request);
    }
}
