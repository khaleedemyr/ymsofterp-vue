<?php

namespace App\Http\Controllers;

use App\Services\PettyCashLockBudgetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportPettyCashController extends Controller
{
    public function __construct(
        private PettyCashLockBudgetService $pettyCashLockBudget
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $isHo = $user && (int) $user->id_outlet === 1;

        $dateFrom = $request->input('date_from') ?: Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $request->input('date_to') ?: Carbon::now()->toDateString();

        $outletId = null;
        if ($isHo) {
            $outletId = $request->filled('outlet') ? (int) $request->input('outlet') : null;
        } else {
            $outletId = $user?->id_outlet ? (int) $user->id_outlet : null;
        }

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->when(! $isHo && $outletId, fn ($q) => $q->where('id_outlet', $outletId))
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet', 'qr_code']);

        $payload = [
            'outlets' => $outlets,
            'filters' => [
                'outlet' => $outletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'user' => [
                'id_outlet' => $user?->id_outlet,
            ],
            'can_select_outlet' => $isHo,
            'retail_non_food_by_category' => [],
            'retail_food_by_supplier' => [],
            'totals' => [
                'retail_non_food' => 0,
                'retail_food' => 0,
                'grand_total' => 0,
            ],
            'petty_cash_budget' => null,
            'outlet_name' => null,
        ];

        if ($outletId) {
            $payload['retail_non_food_by_category'] = $this->getNonFoodByCategory($outletId, $dateFrom, $dateTo);
            $payload['retail_food_by_supplier'] = $this->getFoodBySupplier($outletId, $dateFrom, $dateTo);

            $rnfTotal = collect($payload['retail_non_food_by_category'])->sum('total');
            $rfTotal = collect($payload['retail_food_by_supplier'])->sum('total');
            $payload['totals'] = [
                'retail_non_food' => round($rnfTotal, 2),
                'retail_food' => round($rfTotal, 2),
                'grand_total' => round($rnfTotal + $rfTotal, 2),
            ];

            $payload['outlet_name'] = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->value('nama_outlet');

            $monthStart = Carbon::parse($dateFrom)->startOfMonth()->format('Y-m-01');
            $payload['petty_cash_budget'] = $this->pettyCashLockBudget->resolveForOutlet($outletId, $monthStart);
        }

        return Inertia::render('Report/PettyCash', $payload);
    }

    public function detail(Request $request)
    {
        $request->validate([
            'type' => 'required|in:category,supplier',
            'key' => 'required',
            'outlet' => 'required|integer',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $user = auth()->user();
        $outletId = (int) $request->input('outlet');
        if ($user && (int) $user->id_outlet !== 1 && (int) $user->id_outlet !== $outletId) {
            return response()->json(['error' => 'Unauthorized outlet'], 403);
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $key = $request->input('key');

        if ($request->input('type') === 'category') {
            return response()->json($this->detailNonFoodCategory($outletId, $key, $dateFrom, $dateTo));
        }

        return response()->json($this->detailFoodSupplier($outletId, $key, $dateFrom, $dateTo));
    }

    /**
     * @return list<array{category_id: int|null, category_name: string, total: float, txn_count: int}>
     */
    private function getNonFoodByCategory(int $outletId, string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as cat', 'rnf.category_budget_id', '=', 'cat.id')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->where('rnf.payment_method', '!=', 'contra_bon')
            ->whereNull('rnf.deleted_at')
            ->whereDate('rnf.transaction_date', '>=', $dateFrom)
            ->whereDate('rnf.transaction_date', '<=', $dateTo)
            ->groupBy('rnf.category_budget_id', 'cat.name')
            ->orderByDesc(DB::raw('SUM(rnf.total_amount)'))
            ->selectRaw('
                rnf.category_budget_id as category_id,
                COALESCE(cat.name, "Tanpa Category") as category_name,
                SUM(rnf.total_amount) as total,
                COUNT(rnf.id) as txn_count
            ')
            ->get();

        return $rows->map(fn ($r) => [
            'category_id' => $r->category_id !== null ? (int) $r->category_id : null,
            'category_name' => (string) $r->category_name,
            'total' => round((float) $r->total, 2),
            'txn_count' => (int) $r->txn_count,
        ])->values()->all();
    }

    /**
     * @return list<array{supplier_id: int|null, supplier_name: string, total: float, txn_count: int}>
     */
    private function getFoodBySupplier(int $outletId, string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('retail_food as rf')
            ->leftJoin('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->where('rf.payment_method', '!=', 'contra_bon')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo)
            ->groupBy('rf.supplier_id', 's.name')
            ->orderByDesc(DB::raw('SUM(rf.total_amount)'))
            ->selectRaw('
                rf.supplier_id as supplier_id,
                COALESCE(s.name, "Tanpa Supplier") as supplier_name,
                SUM(rf.total_amount) as total,
                COUNT(rf.id) as txn_count
            ')
            ->get();

        return $rows->map(fn ($r) => [
            'supplier_id' => $r->supplier_id !== null ? (int) $r->supplier_id : null,
            'supplier_name' => (string) $r->supplier_name,
            'total' => round((float) $r->total, 2),
            'txn_count' => (int) $r->txn_count,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function detailNonFoodCategory(int $outletId, string $key, string $dateFrom, string $dateTo): array
    {
        $query = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as cat', 'rnf.category_budget_id', '=', 'cat.id')
            ->leftJoin('users as u', 'rnf.created_by', '=', 'u.id')
            ->where('rnf.outlet_id', $outletId)
            ->where('rnf.status', 'approved')
            ->where('rnf.payment_method', '!=', 'contra_bon')
            ->whereNull('rnf.deleted_at')
            ->whereDate('rnf.transaction_date', '>=', $dateFrom)
            ->whereDate('rnf.transaction_date', '<=', $dateTo);

        if ($key === 'null' || $key === '' || $key === '0') {
            $query->whereNull('rnf.category_budget_id');
            $title = 'Tanpa Category';
        } else {
            $query->where('rnf.category_budget_id', (int) $key);
            $title = (string) (DB::table('purchase_requisition_categories')->where('id', (int) $key)->value('name') ?: 'Category');
        }

        $headers = $query
            ->orderByDesc('rnf.transaction_date')
            ->orderByDesc('rnf.id')
            ->get([
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                'rnf.notes',
                'rnf.payment_method',
                'u.nama_lengkap as created_by_name',
                DB::raw('COALESCE(cat.name, "Tanpa Category") as category_name'),
            ]);

        $transactions = [];
        $grandTotal = 0.0;
        foreach ($headers as $h) {
            $items = DB::table('retail_non_food_items')
                ->where('retail_non_food_id', $h->id)
                ->get(['item_name', 'qty', 'unit', 'price', 'subtotal']);

            $total = (float) $h->total_amount;
            $grandTotal += $total;
            $transactions[] = [
                'id' => (int) $h->id,
                'number' => $h->retail_number,
                'date' => Carbon::parse($h->transaction_date)->toDateString(),
                'payment_method' => $h->payment_method,
                'notes' => $h->notes,
                'created_by' => $h->created_by_name,
                'total' => round($total, 2),
                'items' => $items->map(fn ($i) => [
                    'name' => $i->item_name,
                    'qty' => (float) $i->qty,
                    'unit' => $i->unit,
                    'price' => (float) $i->price,
                    'subtotal' => (float) $i->subtotal,
                ])->values()->all(),
            ];
        }

        return [
            'title' => 'Retail Non Food — '.$title,
            'type' => 'category',
            'transactions' => $transactions,
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailFoodSupplier(int $outletId, string $key, string $dateFrom, string $dateTo): array
    {
        $query = DB::table('retail_food as rf')
            ->leftJoin('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'rf.created_by', '=', 'u.id')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->where('rf.payment_method', '!=', 'contra_bon')
            ->whereNull('rf.deleted_at')
            ->whereDate('rf.transaction_date', '>=', $dateFrom)
            ->whereDate('rf.transaction_date', '<=', $dateTo);

        if ($key === 'null' || $key === '' || $key === '0') {
            $query->whereNull('rf.supplier_id');
            $title = 'Tanpa Supplier';
        } else {
            $query->where('rf.supplier_id', (int) $key);
            $title = (string) (DB::table('suppliers')->where('id', (int) $key)->value('name') ?: 'Supplier');
        }

        $headers = $query
            ->orderByDesc('rf.transaction_date')
            ->orderByDesc('rf.id')
            ->get([
                'rf.id',
                'rf.retail_number',
                'rf.transaction_date',
                'rf.total_amount',
                'rf.notes',
                'rf.payment_method',
                'u.nama_lengkap as created_by_name',
                DB::raw('COALESCE(s.name, "Tanpa Supplier") as supplier_name'),
            ]);

        $transactions = [];
        $grandTotal = 0.0;
        foreach ($headers as $h) {
            $items = DB::table('retail_food_items')
                ->where('retail_food_id', $h->id)
                ->get(['item_name', 'qty', 'unit', 'price', 'subtotal']);

            $total = (float) $h->total_amount;
            $grandTotal += $total;
            $transactions[] = [
                'id' => (int) $h->id,
                'number' => $h->retail_number,
                'date' => Carbon::parse($h->transaction_date)->toDateString(),
                'payment_method' => $h->payment_method,
                'notes' => $h->notes,
                'created_by' => $h->created_by_name,
                'total' => round($total, 2),
                'items' => $items->map(fn ($i) => [
                    'name' => $i->item_name,
                    'qty' => (float) $i->qty,
                    'unit' => $i->unit,
                    'price' => (float) $i->price,
                    'subtotal' => (float) $i->subtotal,
                ])->values()->all(),
            ];
        }

        return [
            'title' => 'Retail Food — '.$title,
            'type' => 'supplier',
            'transactions' => $transactions,
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
