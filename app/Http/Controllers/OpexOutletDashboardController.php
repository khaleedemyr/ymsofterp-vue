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

        // Lazy load: halaman awal hanya shell + filter (tanpa query berat).
        return Inertia::render('OpexOutletDashboard/Index', [
            'dashboardData' => $this->opexService->emptyDashboard(),
            'outlets' => $outlets,
            'userOutletId' => $userOutletId,
            'canSelectOutlet' => $isHo,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId,
            ],
            'lazy' => true,
        ]);
    }

    public function getSection(Request $request)
    {
        $user = auth()->user();
        $userOutletId = (int) $user->id_outlet;
        $section = (string) $request->get('section', '');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        $outletId = $userOutletId === 1
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        $allowed = ['meta', 'overview', 'member', 'ro_forecast', 'payments', 'charts'];
        if (! $outletId || ! in_array($section, $allowed, true)) {
            return response()->json(['error' => 'Outlet and valid section required'], 400);
        }

        return response()->json(
            $this->opexService->buildSection($section, $outletId, $dateFrom, $dateTo)
        );
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
                    $row->member_name ?? null,
                    $row->manual_discount_reason ?? null,
                    $row->beneficiary_name ?? null,
                    $row->bill_amount ?? null,
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
            'discount' => $this->listDiscount($qrCode, $dateFrom, $dateTo),
            'discount_compliment' => $this->listManualDiscountByType($qrCode, $dateFrom, $dateTo, 'compliment'),
            'discount_guest_satisfaction' => $this->listManualDiscountByType($qrCode, $dateFrom, $dateTo, 'guest_satisfaction'),
            'officer_check' => $this->listOfficerCheck($qrCode, $dateFrom, $dateTo),
            'member_top_up' => $this->listMemberTopUp($outletId, $dateFrom, $dateTo),
            'member_redeem' => $this->listMemberRedeem($outletId, $qrCode, $dateFrom, $dateTo),
            'gsr_ro' => $this->listGsrRo($outletId, $dateFrom, $dateTo),
            'rws' => $this->listRws($outletId, $dateFrom, $dateTo),
            'retail_food' => $this->listRetailFood($outletId, $dateFrom, $dateTo),
            'retail_non_food' => $this->listRetailNonFood($outletId, $dateFrom, $dateTo),
            'petty_cash' => $this->listPettyCash($outletId, $dateFrom, $dateTo),
            'total_spend' => $this->listGsrRo($outletId, $dateFrom, $dateTo)
                ->concat($this->listRws($outletId, $dateFrom, $dateTo))
                ->concat($this->listRetailFood($outletId, $dateFrom, $dateTo))
                ->concat($this->listRetailNonFood($outletId, $dateFrom, $dateTo))
                ->sortByDesc(fn ($r) => $r->date ?? '')
                ->values(),
            default => collect(),
        };
    }

    private function listDiscount(?string $qrCode, string $dateFrom, string $dateTo)
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
            ->whereRaw('(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) > 0')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get([
                'id',
                DB::raw("COALESCE(paid_number, CONCAT('ORD-', id)) as number"),
                DB::raw('(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) as amount'),
                'created_at as date',
                'member_name',
                'manual_discount_reason',
                'status',
            ])
            ->map(function ($row) {
                $row->type = 'discount';
                $row->source = 'Diskon Order';
                $reason = trim((string) ($row->manual_discount_reason ?? ''));
                $member = trim((string) ($row->member_name ?? ''));
                if ($reason !== '' && $member !== '') {
                    $row->creator_name = $member.' · '.$reason;
                } elseif ($reason !== '') {
                    $row->creator_name = $reason;
                } else {
                    $row->creator_name = $member !== '' ? $member : '-';
                }

                return $row;
            });
    }

    private function listManualDiscountByType(?string $qrCode, string $dateFrom, string $dateTo, string $type)
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return collect();
        }

        $query = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('manual_discount_reason')
            ->where('manual_discount_reason', '!=', '')
            ->whereRaw('(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) > 0');

        $this->opexService->applyManualDiscountReasonFilter($query, $type);

        $label = $type === 'compliment' ? 'Compliment' : 'Guest Satisfaction';

        return $query
            ->orderByDesc('created_at')
            ->limit(500)
            ->get([
                'id',
                DB::raw("COALESCE(paid_number, CONCAT('ORD-', id)) as number"),
                DB::raw('(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) as amount'),
                DB::raw('COALESCE(total, 0) as bill_amount'),
                'created_at as date',
                'member_name',
                'manual_discount_reason',
                'status',
            ])
            ->map(function ($row) use ($label, $type) {
                $row->type = $type === 'compliment' ? 'discount_compliment' : 'discount_guest_satisfaction';
                $row->source = $label;
                $reason = trim((string) ($row->manual_discount_reason ?? ''));
                $member = trim((string) ($row->member_name ?? ''));
                $row->creator_name = $reason !== '' ? $reason : ($member !== '' ? $member : '-');
                $row->beneficiary_name = $row->creator_name;

                return $row;
            });
    }

    private function listOfficerCheck(?string $qrCode, string $dateFrom, string $dateTo)
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return collect();
        }

        $hasOcUserName = Schema::hasColumn('officer_checks', 'user_name');

        $query = DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->leftJoin('officer_checks as oc', 'o.id_oc', '=', 'oc.id')
            ->leftJoin('users as u', 'oc.user_id', '=', 'u.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OFFICER_CHECK')
                    ->orWhere('op.payment_type', 'OFFICER_CHECK');
            })
            ->orderByDesc('o.created_at')
            ->limit(500);

        $select = [
            'op.id',
            DB::raw("COALESCE(o.paid_number, CONCAT('ORD-', o.id)) as number"),
            'op.amount',
            DB::raw('COALESCE(o.grand_total, 0) as bill_amount'),
            'o.created_at as date',
            'o.member_name',
            'o.id_oc',
            'u.nama_lengkap as oc_user_fullname',
            'op.note',
            'op.kasir',
        ];
        if ($hasOcUserName) {
            $select[] = 'oc.user_name as oc_user_name';
        }

        return $query->get($select)->map(function ($row) use ($hasOcUserName) {
            $row->type = 'officer_check';
            $row->source = 'Officer Check';
            $ocName = trim((string) (($hasOcUserName ? ($row->oc_user_name ?? null) : null) ?: ($row->oc_user_fullname ?? '')));
            $member = trim((string) ($row->member_name ?? ''));
            $note = trim((string) ($row->note ?? ''));
            $beneficiary = $ocName !== '' ? $ocName : ($member !== '' ? $member : ($note !== '' ? $note : '-'));
            $row->beneficiary_name = $beneficiary;
            $row->creator_name = $beneficiary;
            $row->supplier_name = $beneficiary;

            return $row;
        });
    }

    private function listMemberTopUp(int $outletId, string $dateFrom, string $dateTo)
    {
        $qrCode = (string) (DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('qr_code') ?? '');
        if ($qrCode === '' || ! Schema::hasTable('member_apps_point_transactions')) {
            return collect();
        }

        $orderIds = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($orderIds === []) {
            return collect();
        }

        $rows = collect();
        foreach (array_chunk($orderIds, 500) as $chunk) {
            $chunkRows = DB::table('member_apps_point_transactions as pt')
                ->leftJoin('orders as o', function ($join) {
                    $join->whereRaw('CAST(o.id AS CHAR) = CAST(pt.reference_id AS CHAR)');
                })
                ->whereIn('pt.reference_id', $chunk)
                ->where('pt.transaction_type', 'earn')
                ->whereDate('pt.transaction_date', '>=', $dateFrom)
                ->whereDate('pt.transaction_date', '<=', $dateTo)
                ->orderByDesc('pt.transaction_date')
                ->limit(500)
                ->get([
                    'pt.id',
                    DB::raw("COALESCE(o.paid_number, pt.reference_id) as number"),
                    'pt.transaction_amount as amount',
                    'pt.transaction_date as date',
                    'pt.point_amount as point',
                    'o.member_name as creator_name',
                ]);
            $rows = $rows->concat($chunkRows);
        }

        return $rows->take(500)->values()->map(function ($row) {
            $row->type = 'member_top_up';
            $row->source = 'Point Earn'.($row->point ? ' · '.$row->point.' pts' : '');

            return $row;
        });
    }

    private function listMemberRedeem(int $outletId, ?string $qrCode, string $dateFrom, string $dateTo)
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '' || ! Schema::hasTable('member_apps_point_redemptions')) {
            return collect();
        }

        $orderIds = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($orderIds === []) {
            return collect();
        }

        $rows = collect();
        foreach (array_chunk($orderIds, 500) as $chunk) {
            $chunkRows = DB::table('member_apps_point_redemptions as r')
                ->leftJoin('orders as o', function ($join) {
                    $join->whereRaw("CAST(o.id AS CHAR) = CAST(SUBSTRING_INDEX(r.reference_id, '|', -1) AS CHAR)");
                })
                ->leftJoin('member_apps_members as m', 'm.id', '=', 'r.member_id')
                ->where('r.status', 'completed')
                ->whereDate('r.redemption_date', '>=', $dateFrom)
                ->whereDate('r.redemption_date', '<=', $dateTo)
                ->whereIn(DB::raw("SUBSTRING_INDEX(r.reference_id, '|', -1)"), $chunk)
                ->orderByDesc('r.redemption_date')
                ->limit(500)
                ->get([
                    'r.id',
                    DB::raw("COALESCE(o.paid_number, r.reference_id) as number"),
                    DB::raw('COALESCE(r.product_price, r.cash_value, 0) as amount'),
                    'r.redemption_date as date',
                    'r.point_amount as point',
                    'r.product_name',
                    DB::raw("COALESCE(o.member_name, m.nama_lengkap, '-') as creator_name"),
                ]);
            $rows = $rows->concat($chunkRows);
        }

        return $rows->take(500)->values()->map(function ($row) {
            $row->type = 'member_redeem';
            $label = $row->product_name ?: 'Point Redeem';
            $row->source = $label.($row->point ? ' · '.$row->point.' pts' : '');

            return $row;
        });
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
                'rf.payment_method',
                's.name as supplier_name',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $method = $row->payment_method === 'contra_bon' ? 'Contra Bon' : 'Cash';
                $row->type = 'retail_food';
                $row->source = 'Retail Food · '.$method;

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
                'rnf.payment_method',
                'cat.name as category_name',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $method = $row->payment_method === 'contra_bon' ? 'Contra Bon' : 'Cash';
                $row->type = 'retail_non_food';
                $row->source = 'Retail Non Food · '.$method;
                $row->supplier_name = $row->category_name ?? null;

                return $row;
            });
    }

    private function listPettyCash(int $outletId, string $dateFrom, string $dateTo)
    {
        $rf = DB::table('retail_food as rf')
            ->leftJoin('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'rf.created_by', '=', 'u.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->where('rf.payment_method', 'cash')
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->orderByDesc('rf.transaction_date')
            ->limit(300)
            ->get([
                'rf.id',
                'rf.retail_number as number',
                'rf.transaction_date as date',
                'rf.total_amount as amount',
                's.name as supplier_name',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $row->type = 'petty_cash';
                $row->source = 'RF Cash';

                return $row;
            });

        $rnf = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as cat', 'rnf.category_budget_id', '=', 'cat.id')
            ->leftJoin('users as u', 'rnf.created_by', '=', 'u.id')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->where('rnf.payment_method', 'cash')
            ->whereDate('rnf.transaction_date', '>=', $dateFrom)
            ->whereDate('rnf.transaction_date', '<=', $dateTo)
            ->orderByDesc('rnf.transaction_date')
            ->limit(300)
            ->get([
                'rnf.id',
                'rnf.retail_number as number',
                'rnf.transaction_date as date',
                'rnf.total_amount as amount',
                'cat.name as supplier_name',
                'u.nama_lengkap as creator_name',
            ])
            ->map(function ($row) {
                $row->type = 'petty_cash';
                $row->source = 'RNF Cash';

                return $row;
            });

        return $rf->concat($rnf)->sortByDesc(fn ($r) => $r->date ?? '')->values();
    }
}
