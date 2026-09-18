<?php

namespace App\Http\Controllers;

use App\Services\OpexOutletDashboardService;
use App\Support\AttendancePayrollPeriod;
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

        $period = $this->resolvePeriodFilters($request);

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
                'bulan' => $period['bulan'],
                'tahun' => $period['tahun'],
                'date_from' => $period['date_from'],
                'date_to' => $period['date_to'],
                'period_label' => $period['period_label'],
                'attendance_date_from' => $period['attendance_date_from'],
                'attendance_date_to' => $period['attendance_date_to'],
                'attendance_period_label' => $period['attendance_period_label'],
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
        $period = $this->resolvePeriodFilters($request);

        $outletId = $userOutletId === 1
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        $allowed = ['meta', 'overview', 'member', 'ro_forecast', 'payments', 'charts', 'attendance'];
        if (! $outletId || ! in_array($section, $allowed, true)) {
            return response()->json(['error' => 'Outlet and valid section required'], 400);
        }

        // Revenue/spend = kalender tgl 1–akhir bulan; absensi = payroll 26–25.
        $dateFrom = $section === 'attendance'
            ? $period['attendance_date_from']
            : $period['date_from'];
        $dateTo = $section === 'attendance'
            ? $period['attendance_date_to']
            : $period['date_to'];

        return response()->json(
            $this->opexService->buildSection($section, $outletId, $dateFrom, $dateTo)
        );
    }

    public function getCardDetail(Request $request)
    {
        $user = auth()->user();
        $userOutletId = (int) $user->id_outlet;
        $type = (string) $request->get('type');
        $period = $this->resolvePeriodFilters($request);
        $dateFrom = $period['date_from'];
        $dateTo = $period['date_to'];
        $page = max(1, (int) $request->get('page', 1));
        $defaultPerPage = in_array($type, ['revenue', 'total_spend', 'stock_cut', 'category_cost', 'mcs_purchase', 'purchase_category', 'begin_inventory'], true) ? 62 : 20;
        $maxPerPage = in_array($type, ['revenue', 'total_spend', 'stock_cut', 'category_cost', 'mcs_purchase', 'purchase_category', 'begin_inventory'], true) ? 93 : 50;
        $perPage = min($maxPerPage, max(10, (int) $request->get('per_page', $defaultPerPage)));
        $search = trim((string) $request->get('search', ''));
        $category = trim((string) $request->get('category', ''));

        $outletId = $userOutletId === 1
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        if (! $outletId || ! $type) {
            return response()->json(['error' => 'Outlet and type required'], 400);
        }

        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first(['qr_code']);

        if ($type === 'begin_inventory') {
            $detail = $this->opexService->buildBeginInventoryDetail($outletId, $dateFrom, $search);

            return response()->json([
                'trend' => [],
                'transactions' => [],
                'sheet_meta' => [
                    'source' => $detail['source'],
                    'initial_balance_date' => $detail['initial_balance_date'],
                    'total_value' => $detail['total_value'],
                    'groups' => $detail['groups'],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => count($detail['groups']),
                    'total' => count($detail['groups']),
                    'total_pages' => 1,
                ],
            ]);
        }

        $trend = $this->opexService->cardTrend($outletId, $outlet?->qr_code, $dateFrom, $dateTo, $type);
        $sheetMeta = null;
        if ($type === 'total_spend') {
            $sheet = $this->opexService->buildReceivingSheetStyleDaily($outletId, $dateFrom, $dateTo);
            $transactions = collect($sheet['rows']);
            $sheetMeta = [
                'warehouse_columns' => $sheet['warehouse_columns'],
                'suppliers' => $sheet['suppliers'],
                'retail_non_food_transactions' => $this->listRetailNonFood($outletId, $dateFrom, $dateTo)->values()->all(),
            ];
        } elseif ($type === 'stock_cut') {
            $transactions = collect($this->opexService->buildStockCutDaily($outletId, $dateFrom, $dateTo));
        } elseif ($type === 'category_cost') {
            $sheet = $this->opexService->buildCategoryCostDaily($outletId, $dateFrom, $dateTo);
            $transactions = collect($sheet['rows']);
            $sheetMeta = [
                'type_columns' => $sheet['type_columns'],
            ];
        } elseif ($type === 'mcs_purchase') {
            $transactions = collect($this->opexService->listMcsPurchaseTransactions(
                $outletId,
                $dateFrom,
                $dateTo,
                $category !== '' ? $category : null
            ));
            $sheetMeta = [
                'category' => $category !== '' ? $category : null,
                'mcs_only' => true,
            ];
        } elseif ($type === 'purchase_category') {
            $transactions = collect($this->opexService->listPurchaseCategoryTransactions(
                $outletId,
                $dateFrom,
                $dateTo,
                $category !== '' ? $category : null
            ));
            $sheetMeta = [
                'category' => $category !== '' ? $category : null,
                'mcs_only' => false,
            ];
        } else {
            $transactions = $this->transactionsForType($type, $outletId, $outlet?->qr_code, $dateFrom, $dateTo);
        }

        if ($search !== '') {
            $matchesSearch = function ($row) use ($search) {
                $itemHay = '';
                if (! empty($row->items) && is_array($row->items)) {
                    $itemHay = implode(' ', array_map(function ($item) {
                        return is_array($item)
                            ? (($item['item_name'] ?? '').' '.($item['name'] ?? '').' '.($item['category'] ?? ''))
                            : (($item->item_name ?? '').' '.($item->name ?? '').' '.($item->category ?? ''));
                    }, $row->items));
                }
                $hay = strtolower(implode(' ', array_filter([
                    $row->number ?? null,
                    $row->outlet_name ?? null,
                    $row->creator_name ?? null,
                    $row->source ?? null,
                    $row->supplier_name ?? null,
                    $row->category_name ?? null,
                    $row->party_label ?? null,
                    $row->member_name ?? null,
                    $row->manual_discount_reason ?? null,
                    $row->beneficiary_name ?? null,
                    $row->bill_amount ?? null,
                    $row->day_name ?? null,
                    $row->date ?? null,
                    $itemHay,
                ])));

                return str_contains($hay, strtolower($search));
            };

            $transactions = $transactions->filter($matchesSearch)->values();

            if ($type === 'total_spend' && is_array($sheetMeta['retail_non_food_transactions'] ?? null)) {
                $sheetMeta['retail_non_food_transactions'] = collect($sheetMeta['retail_non_food_transactions'])
                    ->filter($matchesSearch)
                    ->values()
                    ->all();
            }
        }

        $total = $transactions->count();
        $slice = $transactions->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'trend' => $trend,
            'transactions' => $slice,
            'sheet_meta' => $sheetMeta,
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

    public function getCategoryCostCellDetail(Request $request)
    {
        $user = auth()->user();
        $userOutletId = (int) $user->id_outlet;
        $date = (string) $request->get('date', '');
        $typeKey = (string) $request->get('type', 'all');

        $outletId = $userOutletId === 1
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        if (! $outletId || $date === '') {
            return response()->json(['error' => 'Outlet and date required'], 400);
        }

        return response()->json(
            $this->opexService->buildCategoryCostCellDetail($outletId, $date, $typeKey)
        );
    }

    public function getStockCutCellDetail(Request $request)
    {
        $user = auth()->user();
        $userOutletId = (int) $user->id_outlet;
        $date = (string) $request->get('date', '');
        $typeKey = (string) $request->get('type', 'all');

        $outletId = $userOutletId === 1
            ? ($request->filled('outlet_id') ? (int) $request->get('outlet_id') : null)
            : $userOutletId;

        if (! $outletId || $date === '') {
            return response()->json(['error' => 'Outlet and date required'], 400);
        }

        return response()->json(
            $this->opexService->buildStockCutCellDetail($outletId, $date, $typeKey)
        );
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
            'stock_cut' => collect($this->opexService->buildStockCutDaily($outletId, $dateFrom, $dateTo)),
            'category_cost' => collect($this->opexService->buildCategoryCostDaily($outletId, $dateFrom, $dateTo)['rows']),
            'mcs_purchase' => collect($this->opexService->listMcsPurchaseTransactions($outletId, $dateFrom, $dateTo)),
            'outlet_city_ledger' => $this->listOutletCityLedger($qrCode, $dateFrom, $dateTo),
            'total_spend' => collect($this->opexService->buildReceivingSheetStyleDaily($outletId, $dateFrom, $dateTo)['rows']),
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

        return DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OFFICER_CHECK')
                    ->orWhere('op.payment_type', 'OFFICER_CHECK');
            })
            ->orderByDesc('o.created_at')
            ->limit(500)
            ->get([
                'op.id',
                DB::raw("COALESCE(o.paid_number, CONCAT('ORD-', o.id)) as number"),
                'op.amount',
                DB::raw('COALESCE(o.grand_total, 0) as bill_amount'),
                'o.created_at as date',
                'o.manual_discount_reason',
                'o.member_name',
                'op.note',
            ])
            ->map(function ($row) {
                $row->type = 'officer_check';
                $row->source = 'Officer Check';
                $reason = trim((string) ($row->manual_discount_reason ?? ''));
                $member = trim((string) ($row->member_name ?? ''));
                $note = trim((string) ($row->note ?? ''));
                $officer = $reason !== '' ? $reason : ($member !== '' ? $member : ($note !== '' ? $note : '-'));
                $row->beneficiary_name = $officer;
                $row->creator_name = $officer;
                $row->supplier_name = $officer;

                return $row;
            });
    }

    private function listOutletCityLedger(?string $qrCode, string $dateFrom, string $dateTo)
    {
        $qrCode = trim((string) $qrCode);
        if ($qrCode === '') {
            return collect();
        }

        return DB::table('order_payment as op')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->where('o.kode_outlet', $qrCode)
            ->whereDate('o.created_at', '>=', $dateFrom)
            ->whereDate('o.created_at', '<=', $dateTo)
            ->where('o.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('op.payment_code', 'OUTLET_CITY_LEDGER')
                    ->orWhere('op.payment_type', 'OUTLET_CITY_LEDGER');
            })
            ->orderByDesc('o.created_at')
            ->limit(500)
            ->get([
                'op.id',
                DB::raw("COALESCE(o.paid_number, CONCAT('ORD-', o.id)) as number"),
                'op.amount',
                DB::raw('COALESCE(o.grand_total, 0) as bill_amount'),
                'o.created_at as date',
                'o.member_name',
                'op.note',
            ])
            ->map(function ($row) {
                $row->type = 'outlet_city_ledger';
                $row->source = 'Outlet City Ledger';
                $member = trim((string) ($row->member_name ?? ''));
                $note = trim((string) ($row->note ?? ''));
                $label = $member !== '' ? $member : ($note !== '' ? $note : '-');
                $row->beneficiary_name = $label;
                $row->creator_name = $label;
                $row->supplier_name = $label;

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

        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        $rows = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw("
                DATE(created_at) as order_date,
                CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END as period,
                SUM(COALESCE(pax, 0)) as cover,
                SUM(COALESCE(grand_total, 0)) as revenue,
                SUM(COALESCE(discount, 0) + COALESCE(manual_discount_amount, 0)) as disc
            ")
            ->groupByRaw("DATE(created_at), CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END")
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $date = (string) $row->order_date;
            if (! isset($byDate[$date])) {
                $byDate[$date] = [
                    'lunch' => ['cover' => 0.0, 'revenue' => 0.0, 'disc' => 0.0],
                    'dinner' => ['cover' => 0.0, 'revenue' => 0.0, 'disc' => 0.0],
                ];
            }
            $period = $row->period === 'dinner' ? 'dinner' : 'lunch';
            $byDate[$date][$period]['cover'] += (float) $row->cover;
            $byDate[$date][$period]['revenue'] += (float) $row->revenue;
            $byDate[$date][$period]['disc'] += (float) $row->disc;
        }

        krsort($byDate);

        return collect($byDate)->map(function ($periods, $date) use ($dayNames) {
            $lunchCover = $periods['lunch']['cover'];
            $lunchRevenue = $periods['lunch']['revenue'];
            $lunchDisc = $periods['lunch']['disc'];
            $dinnerCover = $periods['dinner']['cover'];
            $dinnerRevenue = $periods['dinner']['revenue'];
            $dinnerDisc = $periods['dinner']['disc'];
            $totalCover = $lunchCover + $dinnerCover;
            $totalRevenue = $lunchRevenue + $dinnerRevenue;
            $totalDisc = $lunchDisc + $dinnerDisc;

            $carbon = Carbon::parse($date);
            $dow = (int) $carbon->dayOfWeek;

            return (object) [
                'id' => $date,
                'type' => 'revenue',
                'source' => 'Daily Revenue',
                'date' => $date,
                'number' => $date,
                'day_name' => $dayNames[$dow] ?? $carbon->format('l'),
                'is_weekend' => in_array($dow, [0, 6], true),
                'amount' => round($totalRevenue, 2),
                'lunch_cover' => (int) round($lunchCover),
                'lunch_revenue' => round($lunchRevenue, 2),
                'lunch_avg_check' => $lunchCover > 0 ? (float) round($lunchRevenue / $lunchCover) : 0.0,
                'lunch_disc' => round($lunchDisc, 2),
                'dinner_cover' => (int) round($dinnerCover),
                'dinner_revenue' => round($dinnerRevenue, 2),
                'dinner_avg_check' => $dinnerCover > 0 ? (float) round($dinnerRevenue / $dinnerCover) : 0.0,
                'dinner_disc' => round($dinnerDisc, 2),
                'total_cover' => (int) round($totalCover),
                'total_revenue' => round($totalRevenue, 2),
                'total_avg_check' => $totalCover > 0 ? (float) round($totalRevenue / $totalCover) : 0.0,
                'total_disc' => round($totalDisc, 2),
                'creator_name' => $dayNames[$dow] ?? '',
            ];
        })->values();
    }

    private function listGsrRo(int $outletId, string $dateFrom, string $dateTo)
    {
        $gr = DB::table('outlet_food_good_receives as ofgr')
            ->leftJoin('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('users as u', 'ofgr.created_by', '=', 'u.id')
            ->leftJoin('users as u_ro', 'ffo.user_id', '=', 'u_ro.id')
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
                'u_ro.nama_lengkap as ro_creator',
            ]);

        $grIds = $gr->pluck('id')->all();
        $grTotals = [];
        $grItemsByHeader = collect();
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

            $grItemsByHeader = DB::table('outlet_food_good_receive_items as ofgri')
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
                ->leftJoin('items as it', 'ofgri.item_id', '=', 'it.id')
                ->leftJoin('units as un', 'ofgri.unit_id', '=', 'un.id')
                ->whereIn('ofgr.id', $grIds)
                ->orderBy('it.name')
                ->get([
                    'ofgr.id as header_id',
                    'it.name as item_name',
                    'un.name as unit_name',
                    'ofgri.received_qty as qty',
                    DB::raw('COALESCE(ffoi.price, 0) as price'),
                    DB::raw('(ofgri.received_qty * COALESCE(ffoi.price, 0)) as subtotal'),
                ])
                ->groupBy('header_id');
        }

        $rows = $gr->map(function ($row) use ($grTotals, $grItemsByHeader) {
            $row->amount = round((float) ($grTotals[$row->id] ?? 0), 2);
            $row->type = 'gsr_ro';
            $row->source = 'GR';
            $row->received_by = $row->creator_name;
            $row->number = $row->number.($row->ro_number ? ' · RO '.$row->ro_number : '');
            $row->items = $this->mapDetailItems($grItemsByHeader->get($row->id));

            return $row;
        });

        if (Schema::hasTable('outlet_serial_receive_headers') && Schema::hasTable('outlet_serial_receive_items')) {
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
            $gsrItemsByHeader = collect();
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

                $gsrItemsByHeader = DB::table('outlet_serial_receive_items as si')
                    ->join('items as it', 'si.item_id', '=', 'it.id')
                    ->leftJoin('units as un', 'si.unit_id', '=', 'un.id')
                    ->whereIn('si.header_id', $gsrIds)
                    ->orderBy('it.name')
                    ->get([
                        'si.header_id as header_id',
                        'it.name as item_name',
                        'un.name as unit_name',
                        'si.qty as qty',
                        DB::raw("({$priceSql}) as price"),
                        DB::raw("(si.qty * ({$priceSql})) as subtotal"),
                    ])
                    ->groupBy('header_id');
            }

            $rows = $rows->concat($gsr->map(function ($row) use ($gsrTotals, $gsrItemsByHeader) {
                $row->amount = round((float) ($gsrTotals[$row->id] ?? 0), 2);
                $row->type = 'gsr_ro';
                $row->source = 'GSR';
                $row->ro_number = null;
                $row->ro_creator = null;
                $row->received_by = $row->creator_name;
                $row->items = $this->mapDetailItems($gsrItemsByHeader->get($row->id));

                return $row;
            }));
        }

        return $rows->sortByDesc('date')->values();
    }

    private function listRws(int $outletId, string $dateFrom, string $dateTo)
    {
        $rows = DB::table('retail_warehouse_sales as rws')
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
            ]);

        $ids = $rows->pluck('id')->all();
        $itemsByHeader = collect();
        if ($ids !== []) {
            if (Schema::hasTable('retail_warehouse_sale_items')) {
                $itemsByHeader = DB::table('retail_warehouse_sale_items as rwsi')
                    ->join('items as i', 'rwsi.item_id', '=', 'i.id')
                    ->whereIn('rwsi.retail_warehouse_sale_id', $ids)
                    ->orderBy('i.name')
                    ->get([
                        'rwsi.retail_warehouse_sale_id as header_id',
                        'i.name as item_name',
                        'rwsi.unit as unit_name',
                        'rwsi.qty',
                        'rwsi.price',
                        'rwsi.subtotal',
                    ])
                    ->groupBy('header_id');
            }

            if (Schema::hasTable('retail_warehouse_sale_serial_items')) {
                $serialItems = DB::table('retail_warehouse_sale_serial_items as rwss')
                    ->join('items as i', 'rwss.item_id', '=', 'i.id')
                    ->whereIn('rwss.retail_warehouse_sale_id', $ids)
                    ->orderBy('i.name')
                    ->get([
                        'rwss.retail_warehouse_sale_id as header_id',
                        'i.name as item_name',
                        'rwss.unit_name as unit_name',
                        'rwss.qty',
                        'rwss.price',
                        'rwss.subtotal',
                    ])
                    ->groupBy('header_id');

                foreach ($serialItems as $headerId => $items) {
                    $itemsByHeader[$headerId] = ($itemsByHeader->get($headerId) ?? collect())->concat($items);
                }
            }
        }

        return $rows->map(function ($row) use ($itemsByHeader) {
            $row->type = 'rws';
            $row->source = 'RWS';
            $row->items = $this->mapDetailItems($itemsByHeader->get($row->id));

            return $row;
        });
    }

    private function listRetailFood(int $outletId, string $dateFrom, string $dateTo)
    {
        $rows = DB::table('retail_food as rf')
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
            ]);

        $ids = $rows->pluck('id')->all();
        $itemsByHeader = collect();
        if ($ids !== [] && Schema::hasTable('retail_food_items')) {
            $itemsByHeader = DB::table('retail_food_items')
                ->whereIn('retail_food_id', $ids)
                ->orderBy('item_name')
                ->get(['retail_food_id as header_id', 'item_name', 'unit as unit_name', 'qty', 'price', 'subtotal'])
                ->groupBy('header_id');
        }

        return $rows->map(function ($row) use ($itemsByHeader) {
            $method = $row->payment_method === 'contra_bon' ? 'Contra Bon' : 'Cash';
            $row->type = 'retail_food';
            $row->source = 'Retail Food · '.$method;
            $row->items = $this->mapDetailItems($itemsByHeader->get($row->id));

            return $row;
        });
    }

    private function listRetailNonFood(int $outletId, string $dateFrom, string $dateTo)
    {
        $rows = DB::table('retail_non_food as rnf')
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
            ]);

        $ids = $rows->pluck('id')->filter()->values()->all();
        $itemsByHeader = collect();
        if ($ids !== []) {
            $itemsByHeader = DB::table('retail_non_food_items')
                ->whereIn('retail_non_food_id', $ids)
                ->orderBy('id')
                ->get([
                    'retail_non_food_id as header_id',
                    'item_name',
                    'unit as unit_name',
                    'qty',
                    'price',
                    'subtotal',
                ])
                ->groupBy('header_id');
        }

        return $rows->map(function ($row) use ($itemsByHeader) {
            $method = $row->payment_method === 'contra_bon' ? 'Contra Bon' : 'Cash';
            $row->type = 'retail_non_food';
            $row->source = 'Retail Non Food · '.$method;
            $row->items = $this->mapDetailItems($itemsByHeader->get($row->id));

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
            ]);

        $rfIds = $rf->pluck('id')->all();
        $rfItemsByHeader = collect();
        if ($rfIds !== [] && Schema::hasTable('retail_food_items')) {
            $rfItemsByHeader = DB::table('retail_food_items')
                ->whereIn('retail_food_id', $rfIds)
                ->orderBy('item_name')
                ->get(['retail_food_id as header_id', 'item_name', 'unit as unit_name', 'qty', 'price', 'subtotal'])
                ->groupBy('header_id');
        }

        $rf = $rf->map(function ($row) use ($rfItemsByHeader) {
            $row->type = 'petty_cash';
            $row->source = 'RF Cash';
            $row->category_name = null;
            $row->party_label = $row->supplier_name;
            $row->items = $this->mapDetailItems($rfItemsByHeader->get($row->id));

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
                'cat.name as category_name',
                'u.nama_lengkap as creator_name',
            ]);

        $rnfIds = $rnf->pluck('id')->all();
        $rnfItemsByHeader = collect();
        if ($rnfIds !== []) {
            $rnfItemsByHeader = DB::table('retail_non_food_items')
                ->whereIn('retail_non_food_id', $rnfIds)
                ->orderBy('id')
                ->get([
                    'retail_non_food_id as header_id',
                    'item_name',
                    'unit as unit_name',
                    'qty',
                    'price',
                    'subtotal',
                ])
                ->groupBy('header_id');
        }

        $rnf = $rnf->map(function ($row) use ($rnfItemsByHeader) {
            $row->type = 'petty_cash';
            $row->source = 'RNF Cash';
            $row->supplier_name = null;
            $row->party_label = $row->category_name;
            $row->items = $this->mapDetailItems($rnfItemsByHeader->get($row->id));

            return $row;
        });

        return $rf->concat($rnf)->sortByDesc(fn ($r) => $r->date ?? '')->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|null  $rows
     * @return list<array{name: string, qty: float, unit: string, price: float, subtotal: float}>
     */
    private function mapDetailItems($rows): array
    {
        if (! $rows || $rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn ($i) => [
            'name' => (string) ($i->item_name ?? $i->name ?? '-'),
            'qty' => (float) ($i->qty ?? 0),
            'unit' => (string) ($i->unit_name ?? $i->unit ?? '-'),
            'price' => (float) ($i->price ?? 0),
            'subtotal' => (float) ($i->subtotal ?? 0),
        ])->values()->all();
    }

    /**
     * Filter bulan/tahun menghasilkan 2 rentang:
     * - Revenue/Spend: kalender 1 s/d akhir bulan
     * - Absensi (OT/Telat/Leave): payroll 26 s/d 25
     *
     * @return array{
     *   bulan: int,
     *   tahun: int,
     *   date_from: string,
     *   date_to: string,
     *   period_label: string,
     *   attendance_date_from: string,
     *   attendance_date_to: string,
     *   attendance_period_label: string
     * }
     */
    private function resolvePeriodFilters(Request $request): array
    {
        $bulan = $request->filled('bulan') ? (int) $request->get('bulan') : null;
        $tahun = $request->filled('tahun') ? (int) $request->get('tahun') : null;

        if ($bulan === null || $tahun === null) {
            if ($request->filled('date_to')) {
                $to = Carbon::parse((string) $request->get('date_to'));
                $bulan = $bulan ?: (int) $to->format('n');
                $tahun = $tahun ?: (int) $to->format('Y');
            }
        }

        $bulan = $bulan ?: (int) date('n');
        $tahun = $tahun ?: (int) date('Y');

        $calendarFrom = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $calendarTo = $calendarFrom->copy()->endOfMonth()->startOfDay();
        // Bulan berjalan: sampai hari ini (MTD), bukan ke depan.
        $today = Carbon::today();
        if ($calendarFrom->isSameMonth($today) && $calendarTo->gt($today)) {
            $calendarTo = $today->copy();
        }

        $attendance = AttendancePayrollPeriod::forMonth($bulan, $tahun);

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'date_from' => $calendarFrom->format('Y-m-d'),
            'date_to' => $calendarTo->format('Y-m-d'),
            'period_label' => $calendarFrom->locale('id')->translatedFormat('d M Y')
                .' - '.$calendarTo->locale('id')->translatedFormat('d M Y'),
            'attendance_date_from' => $attendance['start'],
            'attendance_date_to' => $attendance['end'],
            'attendance_period_label' => $attendance['label'],
        ];
    }
}
