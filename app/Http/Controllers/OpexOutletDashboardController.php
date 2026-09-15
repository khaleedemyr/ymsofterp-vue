<?php

namespace App\Http\Controllers;

use App\Services\OpexOutletDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class OpexOutletDashboardController extends Controller
{
    public function __construct(
        private OpexOutletDashboardService $opexService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $userOutletId = (int) $user->id_outlet;
        $isHo = $userOutletId === 1;

        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        $outletId = $isHo
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->when(! $isHo, fn ($q) => $q->where('id_outlet', $userOutletId))
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $dashboard = $outletId
            ? $this->opexService->buildDashboard($outletId, $dateFrom, $dateTo)
            : [
                'overview' => null,
                'trend' => [],
                'spend_mix' => [],
                'outlet_name' => null,
            ];

        return Inertia::render('OpexOutletDashboard/Index', [
            'dashboardData' => $dashboard,
            'outlets' => $outlets,
            'userOutletId' => $userOutletId,
            'canSelectOutlet' => $isHo,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId,
            ],
        ]);
    }

    public function getCardDetail(Request $request)
    {
        $user = auth()->user();
        $userOutletId = (int) $user->id_outlet;
        $type = (string) $request->get('type');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(50, max(10, (int) $request->get('per_page', 20)));
        $search = trim((string) $request->get('search', ''));

        $outletId = $userOutletId === 1
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        if (! $outletId || ! $type) {
            return response()->json(['error' => 'Outlet and type required'], 400);
        }

        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first(['qr_code']);
        $trend = $this->opexService->cardTrend($outletId, $outlet?->qr_code, $dateFrom, $dateTo, $type);
        $transactions = $this->transactionsForType($type, $outletId, $outlet?->qr_code, $dateFrom, $dateTo);

        if ($search !== '') {
            $transactions = $transactions->filter(function ($row) use ($search) {
                $hay = strtolower(implode(' ', array_filter([
                    $row->number ?? null,
                    $row->outlet_name ?? null,
                    $row->creator_name ?? null,
                    $row->source ?? null,
                    $row->supplier_name ?? null,
                ])));

                return str_contains($hay, strtolower($search));
            })->values();
        }

        $total = $transactions->count();
        $slice = $transactions->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'trend' => $trend,
            'transactions' => $slice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /** Kept for route compatibility — spend mix is loaded on index. */
    public function getCategoryDetail()
    {
        return response()->json(['trend' => [], 'transactions' => []]);
    }

    public function getFoodByCategory()
    {
        return response()->json([]);
    }

    public function getFoodCategoryItems()
    {
        return response()->json([]);
    }

    private function transactionsForType(string $type, int $outletId, ?string $qrCode, string $dateFrom, string $dateTo)
    {
        return match ($type) {
            'revenue' => $this->listRevenue($qrCode, $dateFrom, $dateTo),
            'gsr_ro' => $this->listGsrRo($outletId, $dateFrom, $dateTo),
            'rws' => $this->listRws($outletId, $dateFrom, $dateTo),
            'retail_food' => $this->listRetailFood($outletId, $dateFrom, $dateTo),
            'retail_non_food' => $this->listRetailNonFood($outletId, $dateFrom, $dateTo),
            'total_spend' => $this->listGsrRo($outletId, $dateFrom, $dateTo)
                ->concat($this->listRws($outletId, $dateFrom, $dateTo))
                ->concat($this->listRetailFood($outletId, $dateFrom, $dateTo))
                ->concat($this->listRetailNonFood($outletId, $dateFrom, $dateTo))
                ->sortByDesc(fn ($r) => $r->date ?? '')
                ->values(),
            default => collect(),
        };
    }

    private function listRevenue(?string $qrCode, string $dateFrom, string $dateTo)
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return collect();
        }

        return DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get([
                'id',
                DB::raw("CONCAT('ORD-', id) as number"),
                'grand_total as amount',
                'created_at as date',
                'pax',
                'status',
            ])
            ->map(function ($row) {
                $row->type = 'revenue';
                $row->source = 'POS Order';

                return $row;
            });
    }

    private function listGsrRo(int $outletId, string $dateFrom, string $dateTo)
    {
        $gr = DB::table('outlet_food_good_receives as ofgr')
            ->leftJoin('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('users as u', 'ofgr.created_by', '=', 'u.id')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', '>=', $dateFrom)
            ->whereDate('ofgr.receive_date', '<=', $dateTo)
            ->orderByDesc('ofgr.receive_date')
            ->limit(300)
            ->get([
                'ofgr.id',
                'ofgr.number',
                'ofgr.receive_date as date',
                'ffo.order_number as ro_number',
                'u.nama_lengkap as creator_name',
            ]);

        $grIds = $gr->pluck('id')->all();
        $grTotals = [];
        if ($grIds !== []) {
            $grTotals = DB::table('outlet_food_good_receive_items as ofgri')
                ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
                ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
                ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
                ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
                ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                    $join->on('ofgri.item_id', '=', 'ffoi.item_id')
                        ->where(function ($q) {
                            $q->whereColumn('ffoi.floor_order_id', 'do.floor_order_id')
                                ->orWhereColumn('ffoi.floor_order_id', 'ffo_ro.id');
                        });
                })
                ->whereIn('ofgr.id', $grIds)
                ->groupBy('ofgr.id')
                ->selectRaw('ofgr.id, SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
                ->pluck('total', 'id');
        }

        $rows = $gr->map(function ($row) use ($grTotals) {
            $row->amount = round((float) ($grTotals[$row->id] ?? 0), 2);
            $row->type = 'gsr_ro';
            $row->source = 'GR / RO';
            $row->number = $row->number.($row->ro_number ? ' · RO '.$row->ro_number : '');

            return $row;
        });

        if (Schema::hasTable('outlet_serial_receive_headers')) {
            $gsr = DB::table('outlet_serial_receive_headers as h')
                ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', '>=', $dateFrom)
                ->whereDate('h.receive_date', '<=', $dateTo)
                ->orderByDesc('h.receive_date')
                ->limit(300)
                ->get([
                    'h.id',
                    'h.number',
                    'h.receive_date as date',
                    'u.nama_lengkap as creator_name',
                ]);

            $gsrIds = $gsr->pluck('id')->all();
            $gsrTotals = [];
            if ($gsrIds !== []) {
                $priceSql = "(CASE
                    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
                    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
                    ELSE COALESCE(si.cost_small, 0)
                END)";
                $gsrTotals = DB::table('outlet_serial_receive_items as si')
                    ->join('items as it', 'si.item_id', '=', 'it.id')
                    ->whereIn('si.header_id', $gsrIds)
                    ->groupBy('si.header_id')
                    ->selectRaw("si.header_id as id, SUM(si.qty * ({$priceSql})) as total")
                    ->pluck('total', 'id');
            }

            $rows = $rows->concat($gsr->map(function ($row) use ($gsrTotals) {
                $row->amount = round((float) ($gsrTotals[$row->id] ?? 0), 2);
                $row->type = 'gsr_ro';
                $row->source = 'GSR';

                return $row;
            }));
        }

        return $rows->sortByDesc('date')->values();
    }

    private function listRws(int $outletId, string $dateFrom, string $dateTo)
    {
        return DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', '>=', $dateFrom)
            ->whereDate('rws.sale_date', '<=', $dateTo)
            ->orderByDesc('rws.sale_date')
            ->limit(500)
            ->get([
                'rws.id',
                'rws.number as number',
                'rws.sale_date as date',
                'rws.total_amount as amount',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $row->type = 'rws';
                $row->source = 'RWS';

                return $row;
            });
    }

    private function listRetailFood(int $outletId, string $dateFrom, string $dateTo)
    {
        return DB::table('retail_food as rf')
            ->leftJoin('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'rf.created_by', '=', 'u.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->orderByDesc('rf.transaction_date')
            ->limit(500)
            ->get([
                'rf.id',
                'rf.retail_number as number',
                'rf.transaction_date as date',
                'rf.total_amount as amount',
                's.name as supplier_name',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $row->type = 'retail_food';
                $row->source = 'Retail Food';

                return $row;
            });
    }

    private function listRetailNonFood(int $outletId, string $dateFrom, string $dateTo)
    {
        return DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as cat', 'rnf.category_budget_id', '=', 'cat.id')
            ->leftJoin('users as u', 'rnf.created_by', '=', 'u.id')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->whereDate('rnf.transaction_date', '>=', $dateFrom)
            ->whereDate('rnf.transaction_date', '<=', $dateTo)
            ->orderByDesc('rnf.transaction_date')
            ->limit(500)
            ->get([
                'rnf.id',
                'rnf.retail_number as number',
                'rnf.transaction_date as date',
                'rnf.total_amount as amount',
                'cat.name as category_name',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $row->type = 'retail_non_food';
                $row->source = 'Retail Non Food';
                $row->supplier_name = $row->category_name;

                return $row;
            });
    }
}
