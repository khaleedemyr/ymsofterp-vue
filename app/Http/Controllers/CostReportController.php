<?php

namespace App\Http\Controllers;

use App\Exports\CostReportExport;
use App\Exports\Day1OpnameCutoffWithoutIbExport;
use App\Http\Traits\ReportHelperTrait;
use App\Services\OpexOutletDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CostReportController extends Controller
{
    use ReportHelperTrait;

    private array $internalUseWasteAggregatesCache = [];

    public function __construct(private OpexOutletDashboardService $opexOutletDashboard)
    {
    }
    /**
     * Cost Report: kolom Outlet (is_outlet=1, status=A) dan Begin Inventory (Total MAC).
     * Begin inventory logic sama seperti Outlet Stock Report, tapi nilai yang ditampilkan
     * adalah total MAC (nilai rupiah) = sum(begin_qty_small * mac_per_small) per outlet.
     */
    /**
     * Lazy load: data tidak di-load saat pertama masuk. Load hanya saat user klik "Load Data".
     */
    public function index(Request $request)
    {
        $period = $this->resolveReportPeriod($request);
        $shouldLoadData = $request->boolean('load') || $request->input('load') === '1';

        if (!$shouldLoadData) {
            $outlets = \Illuminate\Support\Facades\DB::table('tbl_data_outlet')
                ->where('is_outlet', 1)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
            return Inertia::render('CostReport/Index', [
                'outlets' => $outlets,
                'reportRows' => [],
                'cogsRows' => [],
                'categoryCostRows' => [],
                'filters' => $period['filters'],
            ]);
        }

        $data = $this->getReportData($period['date_from'], $period['date_to']);
        return Inertia::render('CostReport/Index', [
            'outlets' => $data['outlets'],
            'reportRows' => $data['reportRows'],
            'cogsRows' => $data['cogsRows'],
            'categoryCostRows' => $data['categoryCostRows'],
            'filters' => $period['filters'],
        ]);
    }

    /**
     * Export Cost Report to Excel (3 sheets: Cost Inventory, COGS, Category Cost).
     */
    public function export(Request $request)
    {
        $period = $this->resolveReportPeriod($request);
        $data = $this->getReportData($period['date_from'], $period['date_to']);
        $label = $period['date_from'].'_'.$period['date_to'];
        $fileName = 'cost_report_'.$label.'.xlsx';
        return Excel::download(
            new CostReportExport($data['reportRows'], $data['cogsRows'], $data['categoryCostRows'], $label),
            $fileName
        );
    }

    /**
     * Export item tanpa IB tgl 1 DAN tanpa stock opname koreksi fisik tgl 1 (semua outlet).
     */
    public function exportDay1CutoffWithoutIb(Request $request)
    {
        $period = $this->resolveReportPeriod($request);
        $bulan = Carbon::parse($period['date_from'])->format('Y-m');
        $rows = $this->opexOutletDashboard->listItemsWithoutIbAndWithoutDay1OpnameAllOutlets($bulan);
        $fileName = 'tanpa_ib_tanpa_opname_'.$period['date_from'].'_'.$period['date_to'].'.xlsx';

        return Excel::download(
            new Day1OpnameCutoffWithoutIbExport($rows, $bulan),
            $fileName
        );
    }

    /**
     * Load data per tab (AJAX) to avoid heavy full-report computation on every request.
     */
    public function tabData(Request $request)
    {
        $period = $this->resolveReportPeriod($request);
        $tab = $request->input('tab', 'cost_inventory');

        if (!in_array($tab, ['cost_inventory', 'cogs', 'category_cost'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tab tidak valid.',
            ], 422);
        }

        $dateFrom = $period['date_from'];
        $dateTo = $period['date_to'];
        $dayBefore = Carbon::parse($dateFrom)->subDay();
        $tanggalAkhirSebelumPeriode = $dayBefore->toDateString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $build = fn () => $this->buildCostInventoryRows(
            $outlets,
            $dayBefore,
            $tanggalAkhirSebelumPeriode,
            $dateFrom,
            $dateFrom,
            $dateTo
        );

        $reportRows = Cache::remember(
            $this->getReportRowsCacheKey($dateFrom, $dateTo),
            now()->addMinutes(10),
            $build
        );

        if ($tab === 'cost_inventory') {
            return response()->json([
                'success' => true,
                'tab' => $tab,
                'reportRows' => $reportRows,
                'filters' => $period['filters'],
            ]);
        }

        if ($tab === 'cogs') {
            $cogsRows = $this->buildCogsRows($outlets, $reportRows, $dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'tab' => $tab,
                'cogsRows' => $cogsRows,
                'filters' => $period['filters'],
            ]);
        }

        $categoryCostRows = $this->buildCategoryCostRows($outlets, $reportRows, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'tab' => $tab,
            'categoryCostRows' => $categoryCostRows,
            'filters' => $period['filters'],
        ]);
    }

    /**
     * Clear cached Cost Report rows for selected month.
     */
    public function clearCache(Request $request)
    {
        $period = $this->resolveReportPeriod($request);
        Cache::forget($this->getReportRowsCacheKey($period['date_from'], $period['date_to']));

        return response()->json([
            'success' => true,
            'message' => 'Cache cost report berhasil dibersihkan.',
            'filters' => $period['filters'],
        ]);
    }


    /**
     * @return array{date_from: string, date_to: string, filters: array{date_from: string, date_to: string, bulan: string}}
     */
    private function resolveReportPeriod(Request $request): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ((!$dateFrom || !$dateTo) && $request->filled('bulan') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('bulan'))) {
            $month = Carbon::parse($request->input('bulan').'-01');
            $dateFrom = $month->copy()->startOfMonth()->toDateString();
            $dateTo = $month->copy()->endOfMonth()->toDateString();
        }

        if (!$dateFrom || !$dateTo) {
            $today = Carbon::today();
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        try {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $to = Carbon::parse($dateTo)->startOfDay();
        } catch (\Throwable $e) {
            $today = Carbon::today();
            $from = $today->copy()->startOfMonth();
            $to = $today->copy();
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        if ($from->diffInDays($to) > 93) {
            $to = $from->copy()->addDays(93);
        }

        $dateFrom = $from->toDateString();
        $dateTo = $to->toDateString();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'bulan' => $from->format('Y-m'),
            ],
        ];
    }

    private function periodFromValidated(array $validated): array
    {
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            return [$validated['date_from'], $validated['date_to']];
        }
        if (!empty($validated['bulan'])) {
            return $this->officialCostMonthRange($validated['bulan']);
        }

        $today = Carbon::today();

        return [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()];
    }
    /**
     * Detail lazy-loaded untuk Begin Inventory sebuah outlet.
     * Formula nilai mengikuti kolom Total MAC di Cost Report.
     */
    public function beginInventoryDetail(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:category_name,item_name,item_sku,warehouse_name,begin_qty_small,mac,begin_value'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $initialBalanceDate = $dateFrom;
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'begin_value';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);

        $stockBase = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->join('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->where('s.id_outlet', $outletId)
            ->where('wo.status', 'active');

        $hasInitialBalance = (clone $stockBase)
            ->join('outlet_food_inventory_cards as card', function ($join) use ($initialBalanceDate) {
                $join->on('card.inventory_item_id', '=', 's.inventory_item_id')
                    ->on('card.id_outlet', '=', 's.id_outlet')
                    ->on('card.warehouse_outlet_id', '=', 's.warehouse_outlet_id')
                    ->where('card.reference_type', '=', 'initial_balance')
                    ->whereDate('card.date', '=', $initialBalanceDate);
            })
            ->exists();

        if ($hasInitialBalance) {
            $latestInitialBalance = DB::table('outlet_food_inventory_cards as card')
                ->where('card.id_outlet', $outletId)
                ->where('card.reference_type', 'initial_balance')
                ->whereDate('card.date', $initialBalanceDate)
                ->selectRaw("card.inventory_item_id, card.warehouse_outlet_id, MAX(CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0'))) as latest_key")
                ->groupBy('card.inventory_item_id', 'card.warehouse_outlet_id');

            $query = $stockBase
                ->joinSub($latestInitialBalance, 'latest_card', function ($join) {
                    $join->on('latest_card.inventory_item_id', '=', 's.inventory_item_id')
                        ->on('latest_card.warehouse_outlet_id', '=', 's.warehouse_outlet_id');
                })
                ->join('outlet_food_inventory_cards as card', function ($join) {
                    $join->on('card.inventory_item_id', '=', 'latest_card.inventory_item_id')
                        ->on('card.warehouse_outlet_id', '=', 'latest_card.warehouse_outlet_id')
                        ->whereRaw("CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0')) = latest_card.latest_key");
                })
                ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, wo.name as warehouse_name, COALESCE(card.saldo_qty_small, 0) as begin_qty_small, COALESCE(card.cost_per_small, 0) as mac, COALESCE(card.saldo_value, 0) as begin_value");
        } else {
            $query = $stockBase->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, wo.name as warehouse_name, COALESCE(s.qty_small, 0) as begin_qty_small, COALESCE(s.last_cost_small, 0) as mac, COALESCE(s.qty_small, 0) * COALESCE(s.last_cost_small, 0) as begin_value");
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('i.name', 'like', '%' . $search . '%')
                    ->orWhere('i.sku', 'like', '%' . $search . '%')
                    ->orWhere('c.name', 'like', '%' . $search . '%')
                    ->orWhere('wo.name', 'like', '%' . $search . '%');
            });
        }

        $sortColumns = [
            'category_name' => 'category_name',
            'item_name' => 'item_name',
            'item_sku' => 'item_sku',
            'warehouse_name' => 'warehouse_name',
            'begin_qty_small' => 'begin_qty_small',
            'mac' => 'mac',
            'begin_value' => 'begin_value',
        ];

        $items = $query
            ->orderBy($sortColumns[$sortBy], $sortDirection)
            ->orderBy('item_name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'source' => $hasInitialBalance ? 'initial_balance' : 'current_stock',
            'initial_balance_date' => $initialBalanceDate,
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Detail lazy-loaded Official Cost: Good Receive, GSR, dan Retail Food per item.
     */
    public function officialCostDetail(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:category_name,item_name,item_sku,transaction_date,source,qty,unit_cost,amount'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'amount';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);

        $excludedSubCategories = [strtoupper('Stationary'), strtoupper('Marketing'), strtoupper('Chemical')];

        $goodReceive = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('gri.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->where('gr.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(gr.receive_date)'), [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excludedSubCategories)
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, DATE(gr.receive_date) as transaction_date, 'Good Receive' as source, CONCAT('GR #', gr.id) as reference_number, COALESCE(gri.received_qty, 0) as qty, COALESCE(fo.price, 0) as unit_cost, COALESCE(gri.received_qty, 0) * COALESCE(fo.price, 0) as amount");

        $itemNameMap = DB::table('items as im')
            ->selectRaw('MIN(im.id) as item_id, TRIM(im.name) as item_name_key')
            ->groupBy(DB::raw('TRIM(im.name)'));

        $retailFood = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->joinSub($itemNameMap, 'map_item', function ($join) {
                $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
            })
            ->join('items as i', 'map_item.item_id', '=', 'i.id')
            ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->where('rf.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
            ->where('rf.status', 'approved')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excludedSubCategories)
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, DATE(rf.transaction_date) as transaction_date, 'Retail Food' as source, CONCAT('Retail #', rf.id) as reference_number, COALESCE(rfi.qty, 0) as qty, CASE WHEN COALESCE(rfi.qty, 0) <> 0 THEN COALESCE(rfi.subtotal, 0) / rfi.qty ELSE 0 END as unit_cost, COALESCE(rfi.subtotal, 0) as amount");

        $unionQuery = $goodReceive->unionAll($retailFood);

        if ($this->rekapFjHasSerialGrTables()) {
            $effectivePriceExpr = $this->rekapFjSerialGrEffectivePriceSql('i');
            $gsr = DB::table('outlet_serial_receive_headers as h')
                ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
                ->join('items as i', 'si.item_id', '=', 'i.id')
                ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->where('h.outlet_id', $outletId)
                ->whereBetween(DB::raw('DATE(h.receive_date)'), [$dateFrom, $dateTo])
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excludedSubCategories)
                ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, DATE(h.receive_date) as transaction_date, 'GSR' as source, CONCAT('GSR #', h.id) as reference_number, COALESCE(si.qty, 0) as qty, ({$effectivePriceExpr}) as unit_cost, COALESCE(si.qty, 0) * ({$effectivePriceExpr}) as amount");

            $unionQuery = $unionQuery->unionAll($gsr);
        }

        $query = DB::query()->fromSub($unionQuery, 'official_cost_lines');
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_sku', 'like', '%' . $search . '%')
                    ->orWhere('category_name', 'like', '%' . $search . '%')
                    ->orWhere('source', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('item_name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Level 1 Official Cost: breakdown Food GR / GSR / Retail Food per outlet.
     */
    public function officialCostSummary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $excluded = $this->officialCostExcludedSubCategories();

        $foodGr = $this->sumOfficialCostFoodGr($outletId, $dateFrom, $dateTo, $excluded);
        $gsr = $this->sumOfficialCostGsr($outletId, $dateFrom, $dateTo, $excluded);
        $retailFood = $this->sumOfficialCostRetailFood($outletId, $dateFrom, $dateTo, $excluded);
        $total = round($foodGr + $gsr + $retailFood, 2);

        return response()->json([
            'success' => true,
            'sources' => [
                [
                    'key' => 'food_gr',
                    'label' => 'Food GR',
                    'amount' => round($foodGr, 2),
                ],
                [
                    'key' => 'gsr',
                    'label' => 'GSR',
                    'amount' => round($gsr, 2),
                    'available' => $this->rekapFjHasSerialGrTables(),
                ],
                [
                    'key' => 'retail_food',
                    'label' => 'Retail Food',
                    'amount' => round($retailFood, 2),
                ],
            ],
            'total' => $total,
        ]);
    }

    /**
     * Level 2 Official Cost: daftar transaksi per sumber.
     */
    public function officialCostTransactions(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'source' => ['required', 'in:food_gr,gsr,retail_food'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:transaction_date,transaction_number,amount,item_count'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $source = $validated['source'];
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'amount';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $excluded = $this->officialCostExcludedSubCategories();

        if ($source === 'gsr' && !$this->rekapFjHasSerialGrTables()) {
            return response()->json([
                'success' => true,
                'source' => $source,
                'source_label' => 'GSR',
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        $baseQuery = match ($source) {
            'food_gr' => $this->buildOfficialCostFoodGrTransactionsQuery($outletId, $dateFrom, $dateTo, $excluded),
            'gsr' => $this->buildOfficialCostGsrTransactionsQuery($outletId, $dateFrom, $dateTo, $excluded),
            'retail_food' => $this->buildOfficialCostRetailTransactionsQuery($outletId, $dateFrom, $dateTo, $excluded),
        };

        $query = DB::query()->fromSub($baseQuery, 'official_cost_transactions');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', '%' . $search . '%')
                    ->orWhere('transaction_date', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->orderBy($sortBy, $sortDirection)
            ->orderByDesc('transaction_id')
            ->paginate($perPage)
            ->withQueryString();

        $sourceLabels = [
            'food_gr' => 'Food GR',
            'gsr' => 'GSR',
            'retail_food' => 'Retail Food',
        ];

        return response()->json([
            'success' => true,
            'source' => $source,
            'source_label' => $sourceLabels[$source] ?? $source,
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Level 3 Official Cost: detail item dalam satu transaksi.
     */
    public function officialCostTransactionItems(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'source' => ['required', 'in:food_gr,gsr,retail_food'],
            'transaction_id' => ['required', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:item_name,item_sku,category_name,qty,unit_cost,amount'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $source = $validated['source'];
        $transactionId = (int) $validated['transaction_id'];
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'amount';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $excluded = $this->officialCostExcludedSubCategories();

        if ($source === 'gsr' && !$this->rekapFjHasSerialGrTables()) {
            return response()->json([
                'success' => true,
                'transaction' => null,
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        $meta = $this->resolveOfficialCostTransactionMeta($source, $outletId, $transactionId, $dateFrom, $dateTo);
        $linesQuery = match ($source) {
            'food_gr' => $this->buildOfficialCostFoodGrItemLinesQuery($outletId, $transactionId, $dateFrom, $dateTo, $excluded),
            'gsr' => $this->buildOfficialCostGsrItemLinesQuery($outletId, $transactionId, $dateFrom, $dateTo, $excluded),
            'retail_food' => $this->buildOfficialCostRetailItemLinesQuery($outletId, $transactionId, $dateFrom, $dateTo, $excluded),
        };

        $query = DB::query()->fromSub($linesQuery, 'official_cost_transaction_items');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_sku', 'like', '%' . $search . '%')
                    ->orWhere('category_name', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('item_name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'transaction' => $meta,
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    private function officialCostExcludedSubCategories(): array
    {
        return [strtoupper('Stationary'), strtoupper('Marketing'), strtoupper('Chemical')];
    }

    private function officialCostMonthRange(string $bulanOrFrom, ?string $dateTo = null): array
    {
        if ($dateTo !== null) {
            return [$bulanOrFrom, $dateTo];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bulanOrFrom)) {
            return [$bulanOrFrom, $bulanOrFrom];
        }
        $month = Carbon::parse($bulanOrFrom . '-01');

        return [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function officialCostItemNameMap()
    {
        return DB::table('items as im')
            ->select(DB::raw('MIN(im.id) as item_id'), DB::raw('TRIM(im.name) as item_name_key'))
            ->groupBy(DB::raw('TRIM(im.name)'));
    }

    private function sumOfficialCostFoodGr(int $outletId, string $dateFrom, string $dateTo, array $excluded): float
    {
        $row = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->where('gr.outlet_id', $outletId)
            ->whereDate('gr.receive_date', '>=', $dateFrom)
            ->whereDate('gr.receive_date', '<=', $dateTo)
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->selectRaw('COALESCE(SUM(i.received_qty * COALESCE(fo.price, 0)), 0) as total')
            ->value('total');

        return (float) $row;
    }

    private function sumOfficialCostGsr(int $outletId, string $dateFrom, string $dateTo, array $excluded): float
    {
        if (!$this->rekapFjHasSerialGrTables()) {
            return 0;
        }

        $effectivePriceExpr = $this->rekapFjSerialGrEffectivePriceSql('it');
        $row = DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->selectRaw("COALESCE(SUM(si.qty * ({$effectivePriceExpr})), 0) as total")
            ->value('total');

        return (float) $row;
    }

    private function sumOfficialCostRetailFood(int $outletId, string $dateFrom, string $dateTo, array $excluded): float
    {
        $row = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->joinSub($this->officialCostItemNameMap(), 'map_item', function ($join) {
                $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
            })
            ->join('items as it', 'map_item.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('rf.outlet_id', $outletId)
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->where('rf.status', 'approved')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->selectRaw('COALESCE(SUM(rfi.subtotal), 0) as total')
            ->value('total');

        return (float) $row;
    }

    private function buildOfficialCostFoodGrTransactionsQuery(int $outletId, string $dateFrom, string $dateTo, array $excluded)
    {
        return DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->where('gr.outlet_id', $outletId)
            ->whereDate('gr.receive_date', '>=', $dateFrom)
            ->whereDate('gr.receive_date', '<=', $dateTo)
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->groupBy('gr.id', 'gr.number', 'gr.receive_date')
            ->select(
                'gr.id as transaction_id',
                DB::raw("COALESCE(gr.number, CONCAT('GR #', gr.id)) as transaction_number"),
                DB::raw('DATE(gr.receive_date) as transaction_date'),
                DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as amount'),
                DB::raw('COUNT(DISTINCT i.item_id) as item_count')
            );
    }

    private function buildOfficialCostGsrTransactionsQuery(int $outletId, string $dateFrom, string $dateTo, array $excluded)
    {
        $effectivePriceExpr = $this->rekapFjSerialGrEffectivePriceSql('it');

        return DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->groupBy('h.id', 'h.number', 'h.receive_date')
            ->select(
                'h.id as transaction_id',
                DB::raw("COALESCE(h.number, CONCAT('GSR #', h.id)) as transaction_number"),
                DB::raw('DATE(h.receive_date) as transaction_date'),
                DB::raw("SUM(si.qty * ({$effectivePriceExpr})) as amount"),
                DB::raw('COUNT(DISTINCT si.item_id) as item_count')
            );
    }

    private function buildOfficialCostRetailTransactionsQuery(int $outletId, string $dateFrom, string $dateTo, array $excluded)
    {
        return DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->joinSub($this->officialCostItemNameMap(), 'map_item', function ($join) {
                $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
            })
            ->join('items as it', 'map_item.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('rf.outlet_id', $outletId)
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->where('rf.status', 'approved')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->groupBy('rf.id', 'rf.retail_number', 'rf.transaction_date')
            ->select(
                'rf.id as transaction_id',
                DB::raw("COALESCE(rf.retail_number, CONCAT('Retail #', rf.id)) as transaction_number"),
                DB::raw('DATE(rf.transaction_date) as transaction_date'),
                DB::raw('SUM(rfi.subtotal) as amount'),
                DB::raw('COUNT(DISTINCT map_item.item_id) as item_count')
            );
    }

    private function resolveOfficialCostTransactionMeta(string $source, int $outletId, int $transactionId, string $dateFrom, string $dateTo): ?array
    {
        if ($source === 'food_gr') {
            $row = DB::table('outlet_food_good_receives as gr')
                ->where('gr.id', $transactionId)
                ->where('gr.outlet_id', $outletId)
                ->whereDate('gr.receive_date', '>=', $dateFrom)
                ->whereDate('gr.receive_date', '<=', $dateTo)
                ->whereNull('gr.deleted_at')
                ->where('gr.status', 'completed')
                ->select(
                    'gr.id as transaction_id',
                    DB::raw("COALESCE(gr.number, CONCAT('GR #', gr.id)) as transaction_number"),
                    DB::raw('DATE(gr.receive_date) as transaction_date')
                )
                ->first();
        } elseif ($source === 'gsr') {
            $row = DB::table('outlet_serial_receive_headers as h')
                ->where('h.id', $transactionId)
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->select(
                    'h.id as transaction_id',
                    DB::raw("COALESCE(h.number, CONCAT('GSR #', h.id)) as transaction_number"),
                    DB::raw('DATE(h.receive_date) as transaction_date')
                )
                ->first();
        } else {
            $row = DB::table('retail_food as rf')
                ->where('rf.id', $transactionId)
                ->where('rf.outlet_id', $outletId)
                ->whereDate('rf.transaction_date', '>=', $dateFrom)
                ->whereDate('rf.transaction_date', '<=', $dateTo)
                ->where('rf.status', 'approved')
                ->select(
                    'rf.id as transaction_id',
                    DB::raw("COALESCE(rf.retail_number, CONCAT('Retail #', rf.id)) as transaction_number"),
                    DB::raw('DATE(rf.transaction_date) as transaction_date')
                )
                ->first();
        }

        if (!$row) {
            return null;
        }

        return [
            'transaction_id' => (int) $row->transaction_id,
            'transaction_number' => $row->transaction_number,
            'transaction_date' => $row->transaction_date,
            'source' => $source,
            'source_label' => [
                'food_gr' => 'Food GR',
                'gsr' => 'GSR',
                'retail_food' => 'Retail Food',
            ][$source] ?? $source,
        ];
    }

    private function buildOfficialCostFoodGrItemLinesQuery(int $outletId, int $transactionId, string $dateFrom, string $dateTo, array $excluded)
    {
        return DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('gri.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->where('gr.id', $transactionId)
            ->where('gr.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(gr.receive_date)'), [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, COALESCE(u.name, '-') as unit_name, COALESCE(gri.received_qty, 0) as qty, COALESCE(fo.price, 0) as unit_cost, COALESCE(gri.received_qty, 0) * COALESCE(fo.price, 0) as amount");
    }

    private function buildOfficialCostGsrItemLinesQuery(int $outletId, int $transactionId, string $dateFrom, string $dateTo, array $excluded)
    {
        $effectivePriceExpr = $this->rekapFjSerialGrEffectivePriceSql('i');

        return DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
            ->join('items as i', 'si.item_id', '=', 'i.id')
            ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->where('h.id', $transactionId)
            ->where('h.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(h.receive_date)'), [$dateFrom, $dateTo])
            ->whereNull('h.deleted_at')
            ->where('h.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, COALESCE(u.name, '-') as unit_name, COALESCE(si.qty, 0) as qty, ({$effectivePriceExpr}) as unit_cost, COALESCE(si.qty, 0) * ({$effectivePriceExpr}) as amount");
    }

    private function buildOfficialCostRetailItemLinesQuery(int $outletId, int $transactionId, string $dateFrom, string $dateTo, array $excluded)
    {
        return DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->joinSub($this->officialCostItemNameMap(), 'map_item', function ($join) {
                $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
            })
            ->join('items as i', 'map_item.item_id', '=', 'i.id')
            ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->where('rf.id', $transactionId)
            ->where('rf.outlet_id', $outletId)
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
            ->where('rf.status', 'approved')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category_name, i.name as item_name, i.sku as item_sku, COALESCE(rfi.unit, '-') as unit_name, COALESCE(rfi.qty, 0) as qty, CASE WHEN COALESCE(rfi.qty, 0) <> 0 THEN COALESCE(rfi.subtotal, 0) / rfi.qty ELSE 0 END as unit_cost, COALESCE(rfi.subtotal, 0) as amount");
    }

    /**
     * Level 1 Outlet Transfer: daftar transaksi (in/out) per outlet di bulan laporan.
     */
    public function outletTransferTransactions(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:transaction_date,transaction_number,amount,direction'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'amount';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = (int) ($validated['page'] ?? 1);

        $rows = $this->buildOutletTransferTransactionRows($outletId, $dateFrom, $dateTo);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function ($row) use ($needle) {
                $hay = mb_strtolower(implode(' ', [
                    $row['transaction_number'] ?? '',
                    $row['transaction_date'] ?? '',
                    $row['from_outlet'] ?? '',
                    $row['to_outlet'] ?? '',
                    $row['from_warehouse'] ?? '',
                    $row['to_warehouse'] ?? '',
                    $row['direction'] ?? '',
                ]));

                return str_contains($hay, $needle);
            }));
        }

        usort($rows, function ($a, $b) use ($sortBy, $sortDirection) {
            $av = $a[$sortBy] ?? null;
            $bv = $b[$sortBy] ?? null;
            if ($sortBy === 'amount') {
                $av = abs((float) $av);
                $bv = abs((float) $bv);
            }
            if ($av == $bv) {
                return ((int) ($b['transaction_id'] ?? 0)) <=> ((int) ($a['transaction_id'] ?? 0));
            }
            if ($av < $bv) {
                return $sortDirection === 'asc' ? -1 : 1;
            }

            return $sortDirection === 'asc' ? 1 : -1;
        });

        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);
        $netTotal = round(array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $rows)), 2);

        return response()->json([
            'success' => true,
            'net_total' => $netTotal,
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Level 2 Outlet Transfer: detail item dalam satu transaksi.
     */
    public function outletTransferTransactionItems(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'transaction_id' => ['required', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:item_name,item_sku,qty,unit_cost,amount,direction'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->periodFromValidated($validated);
        $outletId = (int) $validated['outlet_id'];
        $transferId = (int) $validated['transaction_id'];
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'amount';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = (int) ($validated['page'] ?? 1);

        $header = DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('tbl_data_outlet as ofrom', 'wf.outlet_id', '=', 'ofrom.id_outlet')
            ->leftJoin('tbl_data_outlet as oto', 'wt.outlet_id', '=', 'oto.id_outlet')
            ->where('t.id', $transferId)
            ->where('t.status', 'approved')
            ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
            ->where(function ($q) use ($outletId) {
                $q->where('wf.outlet_id', $outletId)->orWhere('wt.outlet_id', $outletId);
            })
            ->select(
                't.id as transaction_id',
                't.transfer_number as transaction_number',
                't.transfer_date as transaction_date',
                't.status',
                't.notes',
                'wf.id as from_warehouse_id',
                'wf.name as from_warehouse',
                'wt.id as to_warehouse_id',
                'wt.name as to_warehouse',
                'ofrom.id_outlet as from_outlet_id',
                'ofrom.nama_outlet as from_outlet',
                'oto.id_outlet as to_outlet_id',
                'oto.nama_outlet as to_outlet'
            )
            ->first();

        if (!$header) {
            return response()->json([
                'success' => true,
                'transaction' => null,
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        $fromOutletId = (int) $header->from_outlet_id;
        $toOutletId = (int) $header->to_outlet_id;
        $items = $this->buildOutletTransferItemRows(
            $transferId,
            $outletId,
            $fromOutletId,
            $toOutletId,
            (int) $header->from_warehouse_id,
            (string) $header->transaction_date
        );

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, function ($row) use ($needle) {
                $hay = mb_strtolower(implode(' ', [
                    $row['item_name'] ?? '',
                    $row['item_sku'] ?? '',
                    $row['direction'] ?? '',
                    $row['warehouse_name'] ?? '',
                ]));

                return str_contains($hay, $needle);
            }));
        }

        usort($items, function ($a, $b) use ($sortBy, $sortDirection) {
            $av = $a[$sortBy] ?? null;
            $bv = $b[$sortBy] ?? null;
            if (in_array($sortBy, ['amount', 'qty', 'unit_cost'], true)) {
                $av = abs((float) $av);
                $bv = abs((float) $bv);
            }
            if ($av == $bv) {
                return strcmp((string) ($a['item_name'] ?? ''), (string) ($b['item_name'] ?? ''));
            }
            if ($av < $bv) {
                return $sortDirection === 'asc' ? -1 : 1;
            }

            return $sortDirection === 'asc' ? 1 : -1;
        });

        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $pageItems = array_slice($items, ($page - 1) * $perPage, $perPage);
        $netAmount = round(array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $items)), 2);

        $direction = $toOutletId === $outletId && $fromOutletId !== $outletId
            ? 'in'
            : ($fromOutletId === $outletId && $toOutletId !== $outletId ? 'out' : 'internal');

        return response()->json([
            'success' => true,
            'transaction' => [
                'transaction_id' => (int) $header->transaction_id,
                'transaction_number' => $header->transaction_number,
                'transaction_date' => $header->transaction_date,
                'status' => $header->status,
                'notes' => $header->notes,
                'from_outlet' => $header->from_outlet,
                'to_outlet' => $header->to_outlet,
                'from_warehouse' => $header->from_warehouse,
                'to_warehouse' => $header->to_warehouse,
                'direction' => $direction,
                'amount' => $netAmount,
            ],
            'items' => $pageItems,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildOutletTransferTransactionRows(int $outletId, string $dateFrom, string $dateTo): array
    {
        $transfers = DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->join('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('tbl_data_outlet as ofrom', 'wf.outlet_id', '=', 'ofrom.id_outlet')
            ->leftJoin('tbl_data_outlet as oto', 'wt.outlet_id', '=', 'oto.id_outlet')
            ->where('t.status', 'approved')
            ->whereBetween('t.transfer_date', [$dateFrom, $dateTo])
            ->where(function ($q) use ($outletId) {
                $q->where('wf.outlet_id', $outletId)->orWhere('wt.outlet_id', $outletId);
            })
            ->select(
                't.id',
                't.transfer_number',
                't.transfer_date',
                't.warehouse_outlet_from_id',
                't.warehouse_outlet_to_id',
                'wf.outlet_id as from_outlet_id',
                'wt.outlet_id as to_outlet_id',
                'ofrom.nama_outlet as from_outlet',
                'oto.nama_outlet as to_outlet',
                'wf.name as from_warehouse',
                'wt.name as to_warehouse'
            )
            ->orderByDesc('t.transfer_date')
            ->orderByDesc('t.id')
            ->get();

        if ($transfers->isEmpty()) {
            return [];
        }

        $transferIds = $transfers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $itemsByTransfer = DB::table('outlet_transfer_items')
            ->whereIn('outlet_transfer_id', $transferIds)
            ->get()
            ->groupBy('outlet_transfer_id');

        $receivedByTransfer = DB::table('outlet_food_inventory_cost_histories as h')
            ->join('outlet_transfers as t', 't.id', '=', 'h.reference_id')
            ->join('outlet_transfer_items as d', 'd.outlet_transfer_id', '=', 't.id')
            ->join('outlet_food_inventory_items as ofii', function ($j) {
                $j->on('ofii.id', '=', 'h.inventory_item_id')->on('ofii.item_id', '=', 'd.item_id');
            })
            ->where('h.reference_type', 'outlet_transfer')
            ->where('h.id_outlet', $outletId)
            ->whereIn('t.id', $transferIds)
            ->groupBy('t.id')
            ->select('t.id as transfer_id', DB::raw('SUM(d.qty_small * COALESCE(h.mac, 0)) as total_received'))
            ->pluck('total_received', 'transfer_id');

        $rows = [];
        foreach ($transfers as $t) {
            $fromOutletId = (int) $t->from_outlet_id;
            $toOutletId = (int) $t->to_outlet_id;
            $amount = 0.0;
            $direction = 'internal';

            if ($toOutletId === $outletId) {
                $amount += (float) ($receivedByTransfer[$t->id] ?? 0);
                $direction = $fromOutletId === $outletId ? 'internal' : 'in';
            }

            if ($fromOutletId === $outletId) {
                $sent = 0.0;
                foreach (($itemsByTransfer[$t->id] ?? collect()) as $item) {
                    $qtySmall = (float) ($item->qty_small ?? 0);
                    if ($qtySmall <= 0) {
                        continue;
                    }
                    $ofii = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
                    if (!$ofii) {
                        continue;
                    }
                    $mac = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $ofii->id)
                        ->where('id_outlet', $fromOutletId)
                        ->where('warehouse_outlet_id', $t->warehouse_outlet_from_id)
                        ->where('date', '<=', $t->transfer_date)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->value('mac');
                    $sent += $qtySmall * (float) ($mac ?? 0);
                }
                $amount -= $sent;
                if ($direction !== 'internal') {
                    $direction = 'out';
                } elseif ($toOutletId !== $outletId) {
                    $direction = 'out';
                }
            }

            $rows[] = [
                'transaction_id' => (int) $t->id,
                'transaction_number' => $t->transfer_number,
                'transaction_date' => $t->transfer_date,
                'from_outlet' => $t->from_outlet,
                'to_outlet' => $t->to_outlet,
                'from_warehouse' => $t->from_warehouse,
                'to_warehouse' => $t->to_warehouse,
                'direction' => $direction,
                'item_count' => count($itemsByTransfer[$t->id] ?? []),
                'amount' => round($amount, 2),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildOutletTransferItemRows(
        int $transferId,
        int $outletId,
        int $fromOutletId,
        int $toOutletId,
        int $fromWarehouseId,
        string $transferDate
    ): array {
        $items = DB::table('outlet_transfer_items as ti')
            ->join('items as i', 'ti.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as u', 'i.small_unit_id', '=', 'u.id')
            ->where('ti.outlet_transfer_id', $transferId)
            ->select(
                'ti.id',
                'ti.item_id',
                'ti.qty_small',
                'i.name as item_name',
                'i.sku as item_sku',
                DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category_name"),
                DB::raw("COALESCE(u.name, '-') as unit_name")
            )
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $qtySmall = (float) ($item->qty_small ?? 0);
            $ofii = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
            $mac = 0.0;
            $direction = 'out';
            $amount = 0.0;
            $warehouseName = '';

            if ($toOutletId === $outletId) {
                $direction = $fromOutletId === $outletId ? 'internal_in' : 'in';
                if ($ofii) {
                    $mac = (float) (DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $ofii->id)
                        ->where('id_outlet', $outletId)
                        ->where('reference_type', 'outlet_transfer')
                        ->where('reference_id', $transferId)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->value('mac') ?? 0);
                }
                $amount = $qtySmall * $mac;
                $warehouseName = 'IN';
            }

            if ($fromOutletId === $outletId && $toOutletId !== $outletId) {
                $direction = 'out';
                if ($ofii) {
                    $mac = (float) (DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $ofii->id)
                        ->where('id_outlet', $fromOutletId)
                        ->where('warehouse_outlet_id', $fromWarehouseId)
                        ->where('date', '<=', $transferDate)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->value('mac') ?? 0);
                }
                $amount = -1 * ($qtySmall * $mac);
                $warehouseName = 'OUT';
            } elseif ($fromOutletId === $outletId && $toOutletId === $outletId) {
                // Internal: also show OUT side as negative companion if needed; keep net via received mac above.
                // For detail, show one IN line already; add OUT line with from MAC.
                if ($ofii) {
                    $macOut = (float) (DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $ofii->id)
                        ->where('id_outlet', $fromOutletId)
                        ->where('warehouse_outlet_id', $fromWarehouseId)
                        ->where('date', '<=', $transferDate)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->value('mac') ?? 0);
                } else {
                    $macOut = 0.0;
                }
                $rows[] = [
                    'item_name' => $item->item_name,
                    'item_sku' => $item->item_sku,
                    'category_name' => $item->category_name,
                    'unit_name' => $item->unit_name,
                    'warehouse_name' => 'OUT',
                    'direction' => 'internal_out',
                    'qty' => $qtySmall,
                    'unit_cost' => round($macOut, 4),
                    'amount' => round(-1 * ($qtySmall * $macOut), 2),
                ];
            }

            $rows[] = [
                'item_name' => $item->item_name,
                'item_sku' => $item->item_sku,
                'category_name' => $item->category_name,
                'unit_name' => $item->unit_name,
                'warehouse_name' => $warehouseName ?: ($direction === 'out' ? 'OUT' : 'IN'),
                'direction' => $direction,
                'qty' => $qtySmall,
                'unit_cost' => round($mac, 4),
                'amount' => round($amount, 2),
            ];
        }

        return $rows;
    }

    private function getReportRowsCacheKey(string $dateFrom, string $dateTo = ''): string
    {
        if ($dateTo === '') {
            return 'cost_report:report_rows:v3:' . $dateFrom;
        }

        return 'cost_report:report_rows:v3:' . $dateFrom . '_' . $dateTo;
    }

    private function buildCostInventoryRows($outlets, Carbon $bulanSebelumnya, string $tanggalAkhirBulanSebelumnya, string $tanggal1BulanIni, string $tanggalAwalBulan, string $tanggalAkhirBulan): array
    {
        $reportRows = [];
        $outletIds = collect($outlets)->pluck('id_outlet')->map(fn ($id) => (int) $id)->all();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->whereIn('outlet_id', $outletIds)
            ->where('status', 'active')
            ->select('id', 'outlet_id', 'name')
            ->orderBy('name')
            ->get();

        $warehouseOutletIds = $warehouseOutlets->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stockRows = $this->loadOutletWarehouseStockRows($outletIds, $warehouseOutletIds);
        $beginMacByWarehouse = $this->computeBeginInventoryTotalMacByWarehouse(
            $stockRows,
            $outletIds,
            $warehouseOutletIds,
            $bulanSebelumnya,
            $tanggalAkhirBulanSebelumnya,
            $tanggal1BulanIni
        );
        $endingMacByWarehouse = $this->computeEndingInventoryTotalMacByWarehouse(
            $stockRows,
            $outletIds,
            $warehouseOutletIds,
            $tanggalAkhirBulan,
            $tanggalAwalBulan
        );

        $warehouseIdsByOutlet = [];
        foreach ($warehouseOutlets as $warehouseOutlet) {
            $warehouseIdsByOutlet[(int) $warehouseOutlet->outlet_id][] = (int) $warehouseOutlet->id;
        }

        foreach ($outlets as $outlet) {
            $outletId = $outlet->id_outlet;
            $totalBeginMacOutlet = 0;
            $totalEndingMacOutlet = 0;

            foreach (($warehouseIdsByOutlet[$outletId] ?? []) as $warehouseOutletId) {
                $totalBeginMacOutlet += (float) ($beginMacByWarehouse[$warehouseOutletId] ?? 0);
                $totalEndingMacOutlet += (float) ($endingMacByWarehouse[$warehouseOutletId] ?? 0);
            }

            $endingWeekly = round($totalEndingMacOutlet, 2);
            $endingMtd = $this->opexOutletDashboard->computeEndingInventoryFormula(
                (int) $outletId,
                $tanggalAwalBulan,
                $tanggalAkhirBulan
            );

            $reportRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'total_begin_mac' => round($totalBeginMacOutlet, 2),
                'ending_inventory_weekly' => $endingWeekly,
                'ending_inventory_mtd' => $endingMtd,
                // Alias lama = weekly (opname), dipakai COGS Aktual.
                'ending_inventory' => $endingWeekly,
                'official_cost' => 0,
                'cost_rnd' => 0,
                'outlet_transfer' => 0,
                'sales_before_discount' => 0,
                'discount' => 0,
            ];
        }

        $officialCostByOutlet = $this->computeOfficialCostByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $costRndByOutlet = $this->computeCostRndByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $outletTransferByOutlet = $this->computeOutletTransferByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $salesBeforeDiscountByOutlet = $this->computeSalesBeforeDiscountByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $discountByOutlet = $this->computeDiscountByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);

        foreach ($reportRows as &$row) {
            $row['official_cost'] = round($officialCostByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['cost_rnd'] = round($costRndByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['outlet_transfer'] = round($outletTransferByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['sales_before_discount'] = round($salesBeforeDiscountByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['discount'] = round($discountByOutlet[$row['outlet_id']] ?? 0, 2);
            $row['sales_after_discount'] = round(($row['sales_before_discount'] ?? 0) - ($row['discount'] ?? 0), 2);

            $salesBefore = (float) ($row['sales_before_discount'] ?? 0);
            $row['pct_discount'] = $salesBefore > 0
                ? round(((float) ($row['discount'] ?? 0) / $salesBefore) * 100, 2)
                : 0;

            $row['total_barang_tersedia'] = round(
                ($row['total_begin_mac'] ?? 0) + ($row['official_cost'] ?? 0) - ($row['cost_rnd'] ?? 0) + ($row['outlet_transfer'] ?? 0),
                2
            );
            $row['cogs_aktual'] = round(($row['total_barang_tersedia'] ?? 0) - ($row['ending_inventory_weekly'] ?? $row['ending_inventory'] ?? 0), 2);

            $cogsAktual = (float) ($row['cogs_aktual'] ?? 0);
            $salesAfter = (float) ($row['sales_after_discount'] ?? 0);
            $row['cogs_before'] = $salesBefore > 0 ? round(($cogsAktual / $salesBefore) * 100, 2) : null;
            $row['cogs_after'] = $salesAfter > 0 ? round(($cogsAktual / $salesAfter) * 100, 2) : null;
        }
        unset($row);

        return $reportRows;
    }

    private function loadOutletWarehouseStockRows(array $outletIds, array $warehouseOutletIds)
    {
        if (empty($outletIds) || empty($warehouseOutletIds)) {
            return collect();
        }

        return DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->whereIn('s.id_outlet', $outletIds)
            ->whereIn('s.warehouse_outlet_id', $warehouseOutletIds)
            ->select(
                's.id_outlet',
                's.warehouse_outlet_id',
                'fi.id as inventory_item_id',
                'fi.item_id',
                's.qty_small',
                's.last_cost_small'
            )
            ->distinct()
            ->get();
    }

    private function computeBeginInventoryTotalMacByWarehouse(
        $stockRows,
        array $outletIds,
        array $warehouseOutletIds,
        Carbon $bulanSebelumnya,
        string $tanggalAkhirBulanSebelumnya,
        string $tanggal1BulanIni
    ): array {
        if ($stockRows->isEmpty() || empty($outletIds) || empty($warehouseOutletIds)) {
            return [];
        }

        $inventoryItemIds = $stockRows->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (empty($inventoryItemIds)) {
            return [];
        }

        /**
         * BEGIN INVENTORY (AUTO MODE):
         * - Jika outlet punya upload saldo awal (reference_type=initial_balance) pada tanggal 1 bulan laporan,
         *   maka Begin Inventory outlet tsb HANYA dari initial_balance day-1 (latest per item+warehouse).
         * - Jika outlet TIDAK punya initial_balance day-1 sama sekali, maka Begin Inventory outlet tsb
         *   murni dari saldo snapshot terakhir s/d akhir bulan sebelumnya (cutoff = tanggalAkhirBulanSebelumnya),
         *   dengan pengecualian: stock opname penutupan bulan yang selesai subuh (opname_date = tgl 1 bulan laporan)
         *   tetap dianggap sebagai saldo akhir bulan sebelumnya.
         */
        $outletIdsSql = implode(',', array_map('intval', $outletIds));
        $warehouseIdsSql = implode(',', array_map('intval', $warehouseOutletIds));
        $inventoryIdsSql = implode(',', array_map('intval', $inventoryItemIds));

        $outletsWithDay1Initial = DB::table('outlet_food_inventory_cards as c')
            ->whereIn('c.id_outlet', $outletIds)
            ->whereIn('c.warehouse_outlet_id', $warehouseOutletIds)
            ->whereIn('c.inventory_item_id', $inventoryItemIds)
            ->where('c.reference_type', 'initial_balance')
            ->whereDate('c.date', $tanggal1BulanIni)
            ->distinct()
            ->pluck('c.id_outlet')
            ->map(fn ($id) => (int) $id)
            ->all();
        $outletsWithDay1InitialSet = array_fill_keys($outletsWithDay1Initial, true);

        // (A) initial_balance day-1 snapshot (latest per outlet+warehouse+item)
        $subInit = "
            SELECT
                card.id_outlet,
                card.warehouse_outlet_id,
                card.inventory_item_id,
                MAX(
                    CONCAT(
                        DATE(card.date),
                        ' ',
                        LPAD(card.id, 20, '0')
                    )
                ) AS mx
            FROM outlet_food_inventory_cards card
            WHERE card.id_outlet IN ({$outletIdsSql})
              AND card.warehouse_outlet_id IN ({$warehouseIdsSql})
              AND card.inventory_item_id IN ({$inventoryIdsSql})
              AND card.reference_type = 'initial_balance'
              AND DATE(card.date) = '{$tanggal1BulanIni}'
            GROUP BY card.id_outlet, card.warehouse_outlet_id, card.inventory_item_id
        ";

        $saldoRowsInit = DB::table('outlet_food_inventory_cards as card')
            ->join(DB::raw("({$subInit}) t"), function ($join) {
                $join->on('t.id_outlet', '=', 'card.id_outlet')
                    ->on('t.warehouse_outlet_id', '=', 'card.warehouse_outlet_id')
                    ->on('t.inventory_item_id', '=', 'card.inventory_item_id');
            })
            ->whereRaw("t.mx = CONCAT(DATE(card.date), ' ', LPAD(card.id, 20, '0'))")
            ->select(
                'card.id_outlet',
                'card.warehouse_outlet_id',
                'card.inventory_item_id',
                'card.saldo_value'
            )
            ->get();

        $saldoValueInitMap = [];
        foreach ($saldoRowsInit as $r) {
            $k = (int) $r->id_outlet . '|' . (int) $r->warehouse_outlet_id . '|' . (int) $r->inventory_item_id;
            $saldoValueInitMap[$k] = (float) ($r->saldo_value ?? 0);
        }

        // (B) last stock from system (stocks table): qty_small * last_cost_small (per outlet+warehouse+item)
        $stockValueMap = [];
        foreach ($stockRows as $row) {
            $k = (int) $row->id_outlet . '|' . (int) $row->warehouse_outlet_id . '|' . (int) $row->inventory_item_id;
            if (!isset($stockValueMap[$k])) {
                $qtySmall = (float) ($row->qty_small ?? 0);
                $macSmall = (float) ($row->last_cost_small ?? 0);
                $stockValueMap[$k] = $qtySmall * $macSmall;
            }
        }

        // Hindari double count jika stockRows punya duplikasi per inventory_item_id.
        $uniqueKeys = [];
        foreach ($stockRows as $row) {
            $k = (int) $row->id_outlet . '|' . (int) $row->warehouse_outlet_id . '|' . (int) $row->inventory_item_id;
            $uniqueKeys[$k] = true;
        }

        $totalsByWarehouse = [];
        foreach (array_keys($uniqueKeys) as $k) {
            [$outletIdKey, $warehouseIdKey] = array_map('intval', explode('|', $k, 3));
            if (!isset($totalsByWarehouse[$warehouseIdKey])) {
                $totalsByWarehouse[$warehouseIdKey] = 0;
            }
            $useInitOnly = isset($outletsWithDay1InitialSet[$outletIdKey]);
            $totalsByWarehouse[$warehouseIdKey] += (float) (
                $useInitOnly
                    ? ($saldoValueInitMap[$k] ?? 0)
                    : ($stockValueMap[$k] ?? 0)
            );
        }

        return $totalsByWarehouse;
    }

    private function computeEndingInventoryTotalMacByWarehouse(
        $stockRows,
        array $outletIds,
        array $warehouseOutletIds,
        string $tanggalAkhirBulan,
        ?string $tanggalAwalPeriode = null
    ): array {
        if ($stockRows->isEmpty() || empty($outletIds) || empty($warehouseOutletIds)) {
            return [];
        }

        $tanggalAwalBulan = $tanggalAwalPeriode ?: Carbon::parse($tanggalAkhirBulan)->startOfMonth()->toDateString();

        // Ambil stock opname TERAKHIR dalam bulan ini per (outlet, warehouse_outlet)
        // (bukan harus tepat tanggal akhir bulan) agar ending tidak 0 semua.
        $outletIdsSql = implode(',', array_map('intval', $outletIds));
        $warehouseIdsSql = implode(',', array_map('intval', $warehouseOutletIds));

        $latestSoSub = "
            SELECT
                so.outlet_id,
                so.warehouse_outlet_id,
                MAX(CONCAT(DATE(so.opname_date), ' ', LPAD(so.id, 20, '0'))) AS mx
            FROM outlet_stock_opnames so
            WHERE so.outlet_id IN ({$outletIdsSql})
              AND so.warehouse_outlet_id IN ({$warehouseIdsSql})
              AND so.status IN ('APPROVED', 'COMPLETED')
              AND DATE(so.opname_date) >= '{$tanggalAwalBulan}'
              AND DATE(so.opname_date) <= '{$tanggalAkhirBulan}'
            GROUP BY so.outlet_id, so.warehouse_outlet_id
        ";

        $endingTotals = DB::table('outlet_stock_opnames as so')
            ->join(DB::raw("({$latestSoSub}) t"), function ($join) {
                $join->on('t.outlet_id', '=', 'so.outlet_id')
                    ->on('t.warehouse_outlet_id', '=', 'so.warehouse_outlet_id');
            })
            ->whereRaw("t.mx = CONCAT(DATE(so.opname_date), ' ', LPAD(so.id, 20, '0'))")
            ->join('outlet_stock_opname_items as soi', 'soi.stock_opname_id', '=', 'so.id')
            ->select(
                'so.outlet_id',
                'so.warehouse_outlet_id',
                DB::raw('SUM(COALESCE(soi.qty_physical_small,0) * COALESCE(soi.mac_after, soi.mac_before, 0)) as total_mac')
            )
            ->groupBy('so.outlet_id', 'so.warehouse_outlet_id')
            ->get();

        $totalsByWarehouse = [];
        foreach ($endingTotals as $r) {
            $warehouseId = (int) $r->warehouse_outlet_id;
            $totalsByWarehouse[$warehouseId] = (float) ($r->total_mac ?? 0);
        }

        return $totalsByWarehouse;
    }

    private function buildCogsRows($outlets, array $reportRows, string $tanggalAwalBulan, string $tanggalAkhirBulan): array
    {
        $cogsByOutlet = $this->computeCogsStockCutByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $categoryCostByOutlet = $this->computeCategoryCostByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $mealEmployeesByOutlet = $this->computeMealEmployeesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $cogsAktualByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['cogs_aktual'] ?? 0))->all();
        $salesBeforeDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_before_discount'] ?? 0))->all();
        $salesAfterDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_after_discount'] ?? 0))->all();

        $cogsRows = [];
        foreach ($outlets as $outlet) {
            $cogs = round($cogsByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCost = round($categoryCostByOutlet[$outlet->id_outlet] ?? 0, 2);
            $mealEmployees = round($mealEmployeesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $cogsPembanding = round($cogs + $categoryCost + $mealEmployees, 2);
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $deviasi = round($cogsPembanding - $cogsAktual, 2);
            $toleransi2Pct = round($cogsAktual * 0.02, 2);
            $salesBeforeDiscount = $salesBeforeDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $salesAfterDiscount = $salesAfterDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $pctCogsPembanding = $salesBeforeDiscount > 0 ? round(($cogsPembanding / $salesBeforeDiscount) * 100, 2) : null;
            $pctCogsActualBeforeDisc = $salesBeforeDiscount > 0 ? round(($cogsAktual / $salesBeforeDiscount) * 100, 2) : null;
            $pctCogsActualAfterDisc = $salesAfterDiscount > 0 ? round(($cogsAktual / $salesAfterDiscount) * 100, 2) : null;
            $pctCogsFoods = $salesBeforeDiscount > 0 ? round(($cogs / $salesBeforeDiscount) * 100, 2) : null;
            $pctDeviasi = $cogsPembanding > 0 ? round(($deviasi / $cogsPembanding) * 100, 2) : null;
            $pctCategoryCost = $cogsAktual > 0 ? round(($categoryCost / $cogsAktual) * 100, 2) : null;

            $cogsRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'cogs' => $cogs,
                'category_cost' => $categoryCost,
                'meal_employees' => $mealEmployees,
                'cogs_pembanding' => $cogsPembanding,
                'deviasi' => $deviasi,
                'toleransi_2_pct' => $toleransi2Pct,
                'pct_cogs_pembanding' => $pctCogsPembanding,
                'pct_cogs_actual_before_disc' => $pctCogsActualBeforeDisc,
                'pct_cogs_actual_after_disc' => $pctCogsActualAfterDisc,
                'pct_cogs_foods' => $pctCogsFoods,
                'pct_deviasi' => $pctDeviasi,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        return $cogsRows;
    }

    private function buildCategoryCostRows($outlets, array $reportRows, string $tanggalAwalBulan, string $tanggalAkhirBulan): array
    {
        $guestSuppliesByOutlet = $this->computeGuestSuppliesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $spoilageByOutlet = $this->computeSpoilageByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $wasteByOutlet = $this->computeWasteByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $nonCommodityByOutlet = $this->computeNonCommodityByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $cogsAktualByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['cogs_aktual'] ?? 0))->all();

        $categoryCostRows = [];
        foreach ($outlets as $outlet) {
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $guestSupplies = round($guestSuppliesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $spoilage = round($spoilageByOutlet[$outlet->id_outlet] ?? 0, 2);
            $waste = round($wasteByOutlet[$outlet->id_outlet] ?? 0, 2);
            $nonCommodity = round($nonCommodityByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCostTotal = round($guestSupplies + $spoilage + $waste + $nonCommodity, 2);
            $pctGuestSupplies = $cogsAktual > 0 ? round(($guestSupplies / $cogsAktual) * 100, 2) : null;
            $pctSpoilage = $cogsAktual > 0 ? round(($spoilage / $cogsAktual) * 100, 2) : null;
            $pctWaste = $cogsAktual > 0 ? round(($waste / $cogsAktual) * 100, 2) : null;
            $pctNonCommodity = $cogsAktual > 0 ? round(($nonCommodity / $cogsAktual) * 100, 2) : null;
            $pctCategoryCost = $cogsAktual > 0 ? round(($categoryCostTotal / $cogsAktual) * 100, 2) : null;

            $categoryCostRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'guest_supplies' => $guestSupplies,
                'pct_guest_supplies' => $pctGuestSupplies,
                'spoilage' => $spoilage,
                'pct_spoilage' => $pctSpoilage,
                'waste' => $waste,
                'pct_waste' => $pctWaste,
                'non_commodity' => $nonCommodity,
                'pct_non_commodity' => $pctNonCommodity,
                'category_cost' => $categoryCostTotal,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        return $categoryCostRows;
    }

    /**
     * Build report data for the given month (shared by index and export).
     */
    private function getReportData(string $dateFrom, ?string $dateTo = null): array
    {
        if ($dateTo === null && preg_match('/^\d{4}-\d{2}$/', $dateFrom)) {
            $bulanCarbon = Carbon::parse($dateFrom.'-01');
            $dateFrom = $bulanCarbon->format('Y-m-01');
            $dateTo = $bulanCarbon->format('Y-m-t');
        }
        $dateTo = $dateTo ?: $dateFrom;
        $dayBefore = Carbon::parse($dateFrom)->subDay();
        $bulanSebelumnya = $dayBefore;
        $tanggalAkhirBulanSebelumnya = $dayBefore->toDateString();
        $tanggal1BulanIni = $dateFrom;
        $tanggalAwalBulan = $dateFrom;
        $tanggalAkhirBulan = $dateTo;
        $bulan = Carbon::parse($dateFrom)->format('Y-m');

        // 1. Outlets: is_outlet=1, status='A'
        $outlets = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $reportRows = Cache::remember(
            $this->getReportRowsCacheKey($tanggalAwalBulan, $tanggalAkhirBulan),
            now()->addMinutes(10),
            fn () => $this->buildCostInventoryRows(
                $outlets,
                $bulanSebelumnya,
                $tanggalAkhirBulanSebelumnya,
                $tanggal1BulanIni,
                $tanggalAwalBulan,
                $tanggalAkhirBulan
            )
        );

        // Tab COGS: outlet sama, kolom COGS + Category Cost + Meal Employees + COGS Pembanding + Deviasi
        $cogsByOutlet = $this->computeCogsStockCutByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $categoryCostByOutlet = $this->computeCategoryCostByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $mealEmployeesByOutlet = $this->computeMealEmployeesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $cogsAktualByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['cogs_aktual'] ?? 0))->all();
        $salesBeforeDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_before_discount'] ?? 0))->all();
        $salesAfterDiscountByOutlet = collect($reportRows)->keyBy('outlet_id')->map(fn ($r) => (float) ($r['sales_after_discount'] ?? 0))->all();
        $cogsRows = [];
        foreach ($outlets as $outlet) {
            $cogs = round($cogsByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCost = round($categoryCostByOutlet[$outlet->id_outlet] ?? 0, 2);
            $mealEmployees = round($mealEmployeesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $cogsPembanding = round($cogs + $categoryCost + $mealEmployees, 2);
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $deviasi = round($cogsPembanding - $cogsAktual, 2);
            $toleransi2Pct = round($cogsAktual * 0.02, 2);
            $salesBeforeDiscount = $salesBeforeDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $salesAfterDiscount = $salesAfterDiscountByOutlet[$outlet->id_outlet] ?? 0;
            $pctCogsPembanding = $salesBeforeDiscount > 0
                ? round(($cogsPembanding / $salesBeforeDiscount) * 100, 2)
                : null;
            $pctCogsActualBeforeDisc = $salesBeforeDiscount > 0
                ? round(($cogsAktual / $salesBeforeDiscount) * 100, 2)
                : null;
            $pctCogsActualAfterDisc = $salesAfterDiscount > 0
                ? round(($cogsAktual / $salesAfterDiscount) * 100, 2)
                : null;
            // % COGS Foods = cogs / sales before discount
            $pctCogsFoods = $salesBeforeDiscount > 0
                ? round(($cogs / $salesBeforeDiscount) * 100, 2)
                : null;
            // % Deviasi = deviasi / cogs pembanding (persentase dari COGS Pembanding)
            $pctDeviasi = $cogsPembanding > 0
                ? round(($deviasi / $cogsPembanding) * 100, 2)
                : null;
            // % Category Cost = category cost / cogs actual
            $pctCategoryCost = $cogsAktual > 0
                ? round(($categoryCost / $cogsAktual) * 100, 2)
                : null;
            $cogsRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'cogs' => $cogs,
                'category_cost' => $categoryCost,
                'meal_employees' => $mealEmployees,
                'cogs_pembanding' => $cogsPembanding,
                'deviasi' => $deviasi,
                'toleransi_2_pct' => $toleransi2Pct,
                'pct_cogs_pembanding' => $pctCogsPembanding,
                'pct_cogs_actual_before_disc' => $pctCogsActualBeforeDisc,
                'pct_cogs_actual_after_disc' => $pctCogsActualAfterDisc,
                'pct_cogs_foods' => $pctCogsFoods,
                'pct_deviasi' => $pctDeviasi,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        // Tab Category Cost: outlet + Guest Supplies, Spoilage, Waste, Non Commodity (masing-masing + %), Category Cost (total + %)
        $guestSuppliesByOutlet = $this->computeGuestSuppliesByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $spoilageByOutlet = $this->computeSpoilageByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $wasteByOutlet = $this->computeWasteByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $nonCommodityByOutlet = $this->computeNonCommodityByOutlet($tanggalAwalBulan, $tanggalAkhirBulan);
        $categoryCostRows = [];
        foreach ($outlets as $outlet) {
            $cogsAktual = $cogsAktualByOutlet[$outlet->id_outlet] ?? 0;
            $guestSupplies = round($guestSuppliesByOutlet[$outlet->id_outlet] ?? 0, 2);
            $spoilage = round($spoilageByOutlet[$outlet->id_outlet] ?? 0, 2);
            $waste = round($wasteByOutlet[$outlet->id_outlet] ?? 0, 2);
            $nonCommodity = round($nonCommodityByOutlet[$outlet->id_outlet] ?? 0, 2);
            $categoryCostTotal = round($guestSupplies + $spoilage + $waste + $nonCommodity, 2);
            $pctGuestSupplies = $cogsAktual > 0 ? round(($guestSupplies / $cogsAktual) * 100, 2) : null;
            $pctSpoilage = $cogsAktual > 0 ? round(($spoilage / $cogsAktual) * 100, 2) : null;
            $pctWaste = $cogsAktual > 0 ? round(($waste / $cogsAktual) * 100, 2) : null;
            $pctNonCommodity = $cogsAktual > 0 ? round(($nonCommodity / $cogsAktual) * 100, 2) : null;
            $pctCategoryCost = $cogsAktual > 0 ? round(($categoryCostTotal / $cogsAktual) * 100, 2) : null;
            $categoryCostRows[] = [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->name,
                'guest_supplies' => $guestSupplies,
                'pct_guest_supplies' => $pctGuestSupplies,
                'spoilage' => $spoilage,
                'pct_spoilage' => $pctSpoilage,
                'waste' => $waste,
                'pct_waste' => $pctWaste,
                'non_commodity' => $nonCommodity,
                'pct_non_commodity' => $pctNonCommodity,
                'category_cost' => $categoryCostTotal,
                'pct_category_cost' => $pctCategoryCost,
            ];
        }

        return [
            'outlets' => $outlets,
            'reportRows' => $reportRows,
            'cogsRows' => $cogsRows,
            'categoryCostRows' => $categoryCostRows,
        ];
    }

    /**
     * Hitung total MAC begin inventory untuk satu (outlet, warehouse).
     * LEGACY helper (saat ini tidak dipakai oleh CostReport).
     * Disamakan dengan logic utama: gunakan saldo terakhir dari outlet_food_inventory_cards
     * dengan cutoff akhir bulan sebelumnya + pengecualian stock opname (opname_date = tgl 1).
     */
    private function computeBeginInventoryTotalMac(
        int $outletId,
        int $warehouseOutletId,
        Carbon $bulanSebelumnya,
        string $tanggalAkhirBulanSebelumnya,
        string $tanggal1BulanIni
    ): float {
        $stockRows = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->where('s.id_outlet', $outletId)
            ->where('s.warehouse_outlet_id', $warehouseOutletId)
            ->select(
                's.id_outlet',
                's.warehouse_outlet_id',
                'fi.id as inventory_item_id',
                'fi.item_id',
                's.qty_small',
                's.last_cost_small'
            )
            ->distinct()
            ->get();

        $byWarehouse = $this->computeBeginInventoryTotalMacByWarehouse(
            $stockRows,
            [$outletId],
            [$warehouseOutletId],
            $bulanSebelumnya,
            $tanggalAkhirBulanSebelumnya,
            $tanggal1BulanIni
        );

        return (float) ($byWarehouse[$warehouseOutletId] ?? 0);
    }

    /**
     * Hitung total MAC ending inventory untuk satu (outlet, warehouse).
     * Hanya dari stock opname yang opname_date = tanggal terakhir bulan laporan (opname akhir bulan).
     * Jika belum ada stock opname akhir bulan, ending = 0 (tidak pakai opname tengah bulan).
     * Nilai MAC = qty_small * mac (per small unit).
     */
    private function computeEndingInventoryTotalMac(
        int $outletId,
        int $warehouseOutletId,
        string $tanggalAwalBulan,
        string $tanggalAkhirBulan
    ): float {
        $inventoryItems = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->where('s.id_outlet', $outletId)
            ->where('s.warehouse_outlet_id', $warehouseOutletId)
            ->select('fi.id as inventory_item_id', 'fi.item_id')
            ->distinct()
            ->get();

        if ($inventoryItems->isEmpty()) {
            return 0;
        }

        // Hanya dari opname yang tanggalnya = akhir bulan laporan (stock opname akhir bulan)
        $endingFromOpname = [];
        $endingOpnameMonth = DB::table('outlet_stock_opname_items as soi')
            ->join('outlet_stock_opnames as so', 'soi.stock_opname_id', '=', 'so.id')
            ->join('outlet_food_inventory_items as fi', 'soi.inventory_item_id', '=', 'fi.id')
            ->where('so.outlet_id', $outletId)
            ->where('so.warehouse_outlet_id', $warehouseOutletId)
            ->whereIn('so.status', ['APPROVED', 'COMPLETED'])
            ->where('so.opname_date', '=', $tanggalAkhirBulan)
            ->select(
                'fi.item_id',
                'soi.qty_physical_small',
                'soi.mac_after',
                'soi.mac_before'
            )
            ->orderBy('so.id', 'desc')
            ->get();

        foreach ($endingOpnameMonth as $d) {
            if (isset($endingFromOpname[$d->item_id])) {
                continue;
            }
            $mac = (float) ($d->mac_after ?? $d->mac_before ?? 0);
            $endingFromOpname[$d->item_id] = [
                'qty_small' => (float) ($d->qty_physical_small ?? 0),
                'mac' => $mac,
            ];
        }

        $totalMac = 0;
        foreach ($inventoryItems as $row) {
            $fromOpname = $endingFromOpname[$row->item_id] ?? null;
            if ($fromOpname === null) {
                continue; // Belum ada opname bulan ini → item ini tidak masuk ending
            }
            $totalMac += $fromOpname['qty_small'] * $fromOpname['mac'];
        }

        return $totalMac;
    }

    /**
     * Official Cost = Food GR (completed) + GSR (completed) + Retail Food untuk bulan tertentu,
     * EXCLUDE barang dengan sub_category: Stationary, Marketing, Chemical.
     * Food GR & GSR mengikuti rumus Report Invoice Outlet / Rekap FJ (harga FO / cost_small).
     * Return array [ outlet_id => total_official_cost ].
     */
    private function computeOfficialCostByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $excludedSubCategories = [strtoupper('Stationary'), strtoupper('Marketing'), strtoupper('Chemical')];

        // 1) Food GR: sama sumber harga Invoice Outlet (food_floor_order_items.price),
        //    filter status completed + receive_date bulan laporan.
        $grItems = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->whereDate('gr.receive_date', '>=', $tanggalAwal)
            ->whereDate('gr.receive_date', '<=', $tanggalAkhir)
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excludedSubCategories)
            ->groupBy('gr.outlet_id', 'it.id', 'sc.name')
            ->select(
                'gr.outlet_id',
                DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as item_subtotal')
            )
            ->get();

        $grByOutlet = [];
        foreach ($grItems as $item) {
            $outletId = (int) $item->outlet_id;
            if (!isset($grByOutlet[$outletId])) {
                $grByOutlet[$outletId] = 0;
            }
            $grByOutlet[$outletId] += (float) ($item->item_subtotal ?? 0);
        }

        // 2) GSR: sama rumus Invoice Outlet / Rekap FJ (qty × cost_small dikonversi ke unit baris).
        $gsrByOutlet = [];
        if ($this->rekapFjHasSerialGrTables()) {
            $effectivePriceExpr = $this->rekapFjSerialGrEffectivePriceSql('it');
            $gsrItems = DB::table('outlet_serial_receive_headers as h')
                ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->whereDate('h.receive_date', '>=', $tanggalAwal)
                ->whereDate('h.receive_date', '<=', $tanggalAkhir)
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excludedSubCategories)
                ->groupBy('h.outlet_id', 'it.id', 'sc.name')
                ->select(
                    'h.outlet_id',
                    DB::raw("SUM(si.qty * ({$effectivePriceExpr})) as item_subtotal")
                )
                ->get();

            foreach ($gsrItems as $item) {
                $outletId = (int) $item->outlet_id;
                if (!isset($gsrByOutlet[$outletId])) {
                    $gsrByOutlet[$outletId] = 0;
                }
                $gsrByOutlet[$outletId] += (float) ($item->item_subtotal ?? 0);
            }
        }

        // 3) Retail Food tetap ditambahkan (bukan bagian Invoice Outlet).
        $itemNameMap = DB::table('items as im')
            ->select(DB::raw('MIN(im.id) as item_id'), DB::raw('TRIM(im.name) as item_name_key'))
            ->groupBy(DB::raw('TRIM(im.name)'));

        $retailRows = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->joinSub($itemNameMap, 'map_item', function ($join) {
                $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
            })
            ->join('items as it', 'map_item.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->whereDate('rf.transaction_date', '>=', $tanggalAwal)
            ->whereDate('rf.transaction_date', '<=', $tanggalAkhir)
            ->where('rf.status', 'approved')
            ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excludedSubCategories)
            ->groupBy('rf.outlet_id')
            ->select('rf.outlet_id', DB::raw('SUM(rfi.subtotal) as total_retail'))
            ->get();

        $retailByOutlet = [];
        foreach ($retailRows as $row) {
            $retailByOutlet[(int) $row->outlet_id] = (float) ($row->total_retail ?? 0);
        }

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $gr = (float) ($grByOutlet[(int) $oid] ?? 0);
            $gsr = (float) ($gsrByOutlet[(int) $oid] ?? 0);
            $retail = (float) ($retailByOutlet[(int) $oid] ?? 0);
            $result[$oid] = $gr + $gsr + $retail;
        }
        return $result;
    }

    /**
     * Cost RND = total dari menu Category Cost Outlet dengan type RnD dan Marketing.
     * Sumber: outlet_internal_use_waste_headers (type IN r_and_d, marketing), status APPROVED,
     * date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total_cost_rnd ].
     */
    private function computeCostRndByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $aggregates = $this->getInternalUseWasteAggregates($tanggalAwal, $tanggalAkhir);

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = (float) (($aggregates['r_and_d'][$oid] ?? 0) + ($aggregates['marketing'][$oid] ?? 0));
        }

        return $result;
    }

    /**
     * Category Cost = total dari menu Category Cost Outlet dengan type spoil, waste, guest_supplies, non_commodity.
     * Sumber: outlet_internal_use_waste_headers, status APPROVED, date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total ].
     */
    private function computeCategoryCostByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $aggregates = $this->getInternalUseWasteAggregates($tanggalAwal, $tanggalAkhir);

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = (float) (
                ($aggregates['spoil'][$oid] ?? 0)
                + ($aggregates['waste'][$oid] ?? 0)
                + ($aggregates['guest_supplies'][$oid] ?? 0)
                + ($aggregates['non_commodity'][$oid] ?? 0)
            );
        }

        return $result;
    }

    /**
     * Meal Employees = total dari menu Category Cost Outlet dengan type internal_use.
     * Sumber: outlet_internal_use_waste_headers, status APPROVED, date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total ].
     */
    private function computeMealEmployeesByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('internal_use', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Guest Supplies = total dari menu Category Cost Outlet dengan type guest_supplies.
     * Sumber: outlet_internal_use_waste_headers, status APPROVED/PROCESSED, date dalam bulan laporan. Nilai = SUM(subtotal_mac).
     * Return array [ outlet_id => total ].
     */
    private function computeGuestSuppliesByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('guest_supplies', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Spoilage = total dari menu Category Cost Outlet dengan type spoil.
     * Return array [ outlet_id => total ].
     */
    private function computeSpoilageByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('spoil', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Waste = total dari menu Category Cost Outlet dengan type waste.
     * Return array [ outlet_id => total ].
     */
    private function computeWasteByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('waste', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Non Commodity = total dari menu Category Cost Outlet dengan type non_commodity.
     * Return array [ outlet_id => total ].
     */
    private function computeNonCommodityByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        return $this->computeCategoryCostTypeByOutlet('non_commodity', $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Helper: total per outlet untuk satu type dari outlet_internal_use_waste_headers.
     * Return array [ outlet_id => total ].
     */
    private function computeCategoryCostTypeByOutlet(string $type, string $tanggalAwal, string $tanggalAkhir): array
    {
        $aggregates = $this->getInternalUseWasteAggregates($tanggalAwal, $tanggalAkhir);

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = (float) ($aggregates[$type][$oid] ?? 0);
        }

        return $result;
    }

    /**
     * Hitung sekali nilai detail+MAC untuk semua type Category Cost yang dipakai Cost Report.
     * Cache per periode untuk menghindari query berat berulang saat build tab.
     *
     * Rule status:
     * - r_and_d, marketing: APPROVED saja
     * - internal_use, spoil, waste, guest_supplies, non_commodity: APPROVED/PROCESSED
     *
     * @return array<string, array<int, float>>
     */
    private function getInternalUseWasteAggregates(string $tanggalAwal, string $tanggalAkhir): array
    {
        $cacheKey = $tanggalAwal . '|' . $tanggalAkhir;
        if (isset($this->internalUseWasteAggregatesCache[$cacheKey])) {
            return $this->internalUseWasteAggregatesCache[$cacheKey];
        }

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $trackedTypes = ['r_and_d', 'marketing', 'internal_use', 'spoil', 'waste', 'guest_supplies', 'non_commodity'];

        $aggregates = [];
        foreach ($trackedTypes as $trackedType) {
            $aggregates[$trackedType] = [];
            foreach ($outletIds as $oid) {
                $aggregates[$trackedType][$oid] = 0;
            }
        }

        $headers = DB::table('outlet_internal_use_waste_headers as h')
            ->whereIn('h.type', $trackedTypes)
            ->whereBetween('h.date', [$tanggalAwal, $tanggalAkhir]);

        $headers->where(function ($query) {
            $query->where(function ($sub) {
                $sub->whereIn('h.type', ['r_and_d', 'marketing'])
                    ->where('h.status', 'APPROVED');
            })->orWhere(function ($sub) {
                $sub->whereIn('h.type', ['internal_use', 'spoil', 'waste', 'guest_supplies', 'non_commodity'])
                    ->whereIn('h.status', ['APPROVED', 'PROCESSED']);
            });
        });

        $headers = $headers
            ->select('h.id', 'h.type', 'h.outlet_id', 'h.warehouse_outlet_id', 'h.date')
            ->get();

        if ($headers->isEmpty()) {
            $this->internalUseWasteAggregatesCache[$cacheKey] = $aggregates;
            return $aggregates;
        }

        $headerIds = $headers->pluck('id')->all();
        $headersById = $headers->keyBy('id');

        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->whereIn('d.header_id', $headerIds)
            ->select(
                'd.header_id',
                'd.item_id',
                'd.unit_id',
                'd.qty',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->get();

        if ($details->isEmpty()) {
            $this->internalUseWasteAggregatesCache[$cacheKey] = $aggregates;
            return $aggregates;
        }

        $itemIds = $details->pluck('item_id')->filter()->unique()->values()->all();
        $inventoryItems = [];
        if (!empty($itemIds)) {
            $inventoryItems = DB::table('outlet_food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->select('id', 'item_id')
                ->get()
                ->keyBy('item_id')
                ->toArray();
        }

        $macQueryConditions = [];
        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            if (!$header || !isset($inventoryItems[$detail->item_id])) {
                continue;
            }

            $inventoryItemId = $inventoryItems[$detail->item_id]->id;
            $key = "{$inventoryItemId}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
            if (!isset($macQueryConditions[$key])) {
                $macQueryConditions[$key] = [
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $header->outlet_id,
                    'warehouse_outlet_id' => $header->warehouse_outlet_id,
                    'date' => $header->date,
                ];
            }
        }

        $macHistories = [];
        foreach ($macQueryConditions as $key => $condition) {
            $macHistories[$key] = null;
        }

        if (!empty($macQueryConditions)) {
            $inventoryItemIdsForMac = [];
            $outletIdsForMac = [];
            $warehouseIdsForMac = [];
            $maxDate = null;

            foreach ($macQueryConditions as $condition) {
                $inventoryItemIdsForMac[] = (int) $condition['inventory_item_id'];
                $outletIdsForMac[] = (int) $condition['id_outlet'];
                $warehouseIdsForMac[] = (int) $condition['warehouse_outlet_id'];
                $maxDate = $maxDate === null ? $condition['date'] : max($maxDate, $condition['date']);
            }

            $historyRows = DB::table('outlet_food_inventory_cost_histories')
                ->whereIn('inventory_item_id', array_values(array_unique($inventoryItemIdsForMac)))
                ->whereIn('id_outlet', array_values(array_unique($outletIdsForMac)))
                ->whereIn('warehouse_outlet_id', array_values(array_unique($warehouseIdsForMac)))
                ->where('date', '<=', $maxDate)
                ->select('inventory_item_id', 'id_outlet', 'warehouse_outlet_id', 'date', 'id', 'mac')
                ->orderBy('inventory_item_id')
                ->orderBy('id_outlet')
                ->orderBy('warehouse_outlet_id')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

            $historiesByTuple = [];
            foreach ($historyRows as $historyRow) {
                $tupleKey = (int) $historyRow->inventory_item_id . '|'
                    . (int) $historyRow->id_outlet . '|'
                    . (int) $historyRow->warehouse_outlet_id;
                $historiesByTuple[$tupleKey][] = $historyRow;
            }

            foreach ($macQueryConditions as $key => $condition) {
                $tupleKey = (int) $condition['inventory_item_id'] . '|'
                    . (int) $condition['id_outlet'] . '|'
                    . (int) $condition['warehouse_outlet_id'];
                $targetDate = $condition['date'];

                foreach (($historiesByTuple[$tupleKey] ?? []) as $historyRow) {
                    if ($historyRow->date <= $targetDate) {
                        $macHistories[$key] = (float) ($historyRow->mac ?? 0);
                        break;
                    }
                }
            }
        }

        foreach ($details as $detail) {
            $header = $headersById->get($detail->header_id);
            if (!$header || !isset($inventoryItems[$detail->item_id])) {
                continue;
            }

            $inventoryItemId = $inventoryItems[$detail->item_id]->id;
            $macKey = "{$inventoryItemId}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
            $mac = $macHistories[$macKey] ?? null;
            if ($mac === null) {
                continue;
            }

            $macConverted = $mac;
            if ((int) $detail->unit_id === (int) $detail->medium_unit_id && (float) $detail->small_conversion_qty > 0) {
                $macConverted = $mac * (float) $detail->small_conversion_qty;
            } elseif (
                (int) $detail->unit_id === (int) $detail->large_unit_id
                && (float) $detail->small_conversion_qty > 0
                && (float) $detail->medium_conversion_qty > 0
            ) {
                $macConverted = $mac * (float) $detail->small_conversion_qty * (float) $detail->medium_conversion_qty;
            }

            $subtotalMac = $macConverted * (float) ($detail->qty ?? 0);
            $headerType = $header->type ?? null;

            if ($headerType && isset($aggregates[$headerType][$header->outlet_id])) {
                $aggregates[$headerType][$header->outlet_id] += $subtotalMac;
            }
        }

        $this->internalUseWasteAggregatesCache[$cacheKey] = $aggregates;
        return $aggregates;
    }

    /**
     * Outlet Transfer = net per outlet dari menu Outlet Transfer (status approved).
     * Outlet yang mengirim stock: nilai minus (pengurangan).
     * Outlet yang menerima stock: nilai plus (penambahan).
     * Return array [ outlet_id => net_value ] dengan net = received - sent.
     */
    private function computeOutletTransferByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');
        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }

        // 1. Nilai diterima (receiver): dari outlet_food_inventory_cost_histories (reference_type=outlet_transfer)
        //    join transfer items → sum(qty_small * mac) per id_outlet
        $receivedRows = DB::table('outlet_food_inventory_cost_histories as h')
            ->join('outlet_transfers as t', 't.id', '=', 'h.reference_id')
            ->join('outlet_transfer_items as d', function ($j) {
                $j->on('d.outlet_transfer_id', '=', 't.id');
            })
            ->join('outlet_food_inventory_items as ofii', function ($j) {
                $j->on('ofii.id', '=', 'h.inventory_item_id')->on('ofii.item_id', '=', 'd.item_id');
            })
            ->where('h.reference_type', 'outlet_transfer')
            ->where('t.status', 'approved')
            ->whereBetween('h.date', [$tanggalAwal, $tanggalAkhir])
            ->groupBy('h.id_outlet')
            ->select('h.id_outlet', DB::raw('SUM(d.qty_small * COALESCE(h.mac, 0)) as total_received'))
            ->get();
        foreach ($receivedRows as $r) {
            $result[$r->id_outlet] = (float) ($r->total_received ?? 0);
        }

        // 2. Nilai dikirim (sender): per transfer approved, outlet asal = warehouse_outlet_from → outlet_id
        //    nilai = sum over items (qty_small * MAC di outlet asal pada transfer_date)
        $transfers = DB::table('outlet_transfers as t')
            ->join('warehouse_outlets as wo_from', 'wo_from.id', '=', 't.warehouse_outlet_from_id')
            ->where('t.status', 'approved')
            ->whereBetween('t.transfer_date', [$tanggalAwal, $tanggalAkhir])
            ->select('t.id', 't.transfer_date', 't.warehouse_outlet_from_id', 'wo_from.outlet_id as from_outlet_id')
            ->get();

        foreach ($transfers as $t) {
            $items = DB::table('outlet_transfer_items')->where('outlet_transfer_id', $t->id)->get();
            foreach ($items as $item) {
                $qtySmall = (float) ($item->qty_small ?? 0);
                if ($qtySmall <= 0) {
                    continue;
                }
                $ofii = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
                if (!$ofii) {
                    continue;
                }
                $mac = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $ofii->id)
                    ->where('id_outlet', $t->from_outlet_id)
                    ->where('warehouse_outlet_id', $t->warehouse_outlet_from_id)
                    ->where('date', '<=', $t->transfer_date)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->value('mac');
                $valueSent = $qtySmall * (float) ($mac ?? 0);
                $result[$t->from_outlet_id] -= $valueSent;
            }
        }

        return $result;
    }

    /**
     * Sales Before Discount per outlet: sumber sama seperti menu Engineering (Item Engineering).
     * orders + order_items, SUM(qty * price) per outlet, filter tanggal created_at dalam bulan laporan.
     * Mapping outlet via orders.kode_outlet = tbl_data_outlet.qr_code.
     * Return array [ outlet_id => total_sales_before_discount ].
     */
    private function computeSalesBeforeDiscountByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $rows = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
            ->where('o.is_outlet', 1)
            ->where('o.status', 'A')
            ->whereDate('orders.created_at', '>=', $tanggalAwal)
            ->whereDate('orders.created_at', '<=', $tanggalAkhir)
            ->groupBy('o.id_outlet')
            ->select('o.id_outlet', DB::raw('SUM(order_items.qty * order_items.price) as total_sales'))
            ->get();

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }
        foreach ($rows as $r) {
            $result[$r->id_outlet] = (float) ($r->total_sales ?? 0);
        }
        return $result;
    }

    /**
     * Total discount per outlet: discount promo + manual discount (sumber: report rekap discount).
     * Promo = orders.discount, manual = orders.manual_discount_amount.
     * Filter: orders.created_at dalam bulan laporan, outlet via kode_outlet = qr_code (is_outlet=1, status=A).
     * Return array [ outlet_id => total_discount ].
     */
    private function computeDiscountByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $rows = DB::table('orders')
            ->join('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
            ->where('o.is_outlet', 1)
            ->where('o.status', 'A')
            ->whereDate('orders.created_at', '>=', $tanggalAwal)
            ->whereDate('orders.created_at', '<=', $tanggalAkhir)
            ->groupBy('o.id_outlet')
            ->select(
                'o.id_outlet',
                DB::raw('COALESCE(SUM(orders.discount), 0) + COALESCE(SUM(orders.manual_discount_amount), 0) as total_discount')
            )
            ->get();

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }
        foreach ($rows as $r) {
            $result[$r->id_outlet] = (float) ($r->total_discount ?? 0);
        }
        return $result;
    }

    /**
     * COGS dari hasil stock cut bulan tersebut (menu Stock Cut).
     * Sumber: stock_cut_details join stock_cut_logs, SUM(value_out) per outlet.
     * value_out = nilai cost yang keluar saat potong stock (qty * cost per small).
     * Filter: scl.tanggal dalam bulan laporan, scl.status = 'success'.
     * Return array [ outlet_id => total_cogs ].
     */
    private function computeCogsStockCutByOutlet(string $tanggalAwal, string $tanggalAkhir): array
    {
        $rows = DB::table('stock_cut_details as scd')
            ->join('stock_cut_logs as scl', 'scd.stock_cut_log_id', '=', 'scl.id')
            ->whereBetween('scl.tanggal', [$tanggalAwal, $tanggalAkhir])
            ->where('scl.status', 'success')
            ->groupBy('scl.outlet_id')
            ->select('scl.outlet_id', DB::raw('SUM(COALESCE(scd.value_out, 0)) as total_cogs'))
            ->get();

        $outletIds = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->pluck('id_outlet');

        $result = [];
        foreach ($outletIds as $oid) {
            $result[$oid] = 0;
        }
        foreach ($rows as $r) {
            $result[$r->outlet_id] = (float) ($r->total_cogs ?? 0);
        }
        return $result;
    }
}
