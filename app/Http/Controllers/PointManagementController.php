<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class PointManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $type = $request->get('type', '');
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Get transactions with pagination
        $query = DB::connection('mysql_second')
            ->table('point as p')
            ->select([
                'p.id',
                'p.no_bill',
                'p.no_bill_2',
                'p.point',
                'p.jml_trans',
                'p.type',
                'p.created_at',
                'p.costumer_id',
                'cb.name as cabang_name',
                'c.name as customer_name',
                'c.costumers_id',
                'c.email as customer_email',
                'c.telepon as customer_phone',
            ])
            ->join('cabangs as cb', 'p.cabang_id', '=', 'cb.id')
            ->join('costumers as c', 'p.costumer_id', '=', 'c.id');

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('c.name', 'like', "%{$search}%")
                  ->orWhere('c.costumers_id', 'like', "%{$search}%")
                  ->orWhere('c.telepon', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('p.no_bill', 'like', "%{$search}%")
                  ->orWhere('p.no_bill_2', 'like', "%{$search}%")
                  ->orWhere('cb.name', 'like', "%{$search}%");
            });
        }

        // Apply type filter
        if ($type) {
            $query->where('p.type', $type);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $transactions = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedTransactions = $transactions->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'bill_number' => $item->type == '1' ? $item->no_bill : $item->no_bill_2,
                'point' => $item->point,
                'point_formatted' => number_format($item->point, 0, ',', '.'),
                'jml_trans' => $item->jml_trans,
                'jml_trans_formatted' => 'Rp ' . number_format($item->jml_trans, 0, ',', '.'),
                'type' => $item->type,
                'type_text' => $item->type == '1' ? 'Top Up' : 'Redeem',
                'cabang_name' => $item->cabang_name,
                'customer_id' => $item->costumer_id,
                'customer_name' => $item->customer_name,
                'costumers_id' => $item->costumers_id,
                'customer_email' => $item->customer_email,
                'customer_phone' => $item->customer_phone,
                'created_at' => Carbon::parse($item->created_at)->format('d/m/Y H:i:s'),
                'created_at_raw' => $item->created_at,
            ];
        });

        // Get summary statistics
        $summary = $this->getSummaryData();

        return Inertia::render('Crm/PointManagement', [
            'transactions' => $formattedTransactions,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:mysql_second.costumers,id',
            'cabang_id' => 'required|exists:mysql_second.cabangs,id',
            'type' => 'required|in:1,2',
            'jml_trans' => 'required|numeric|min:1',
            'no_bill' => 'required|string|max:255',
            'keterangan' => 'nullable|string|max:500',
            'point' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::connection('mysql_second')->beginTransaction();

            $jmlTrans = $request->jml_trans;
            $type = $request->type;
            
            // Use point from request if provided, otherwise calculate
            if ($request->has('point') && $request->point !== null) {
                $point = (int) $request->point;
            } else {
                // Calculate points for top up if not provided
                $point = 0;
                if ($type == '1') {
                    $point = floor($jmlTrans / 50000) * 1250;
                }
            }

            // Insert into point table
            $pointId = DB::connection('mysql_second')
                ->table('point')
                ->insertGetId([
                    'costumer_id' => $request->customer_id,
                    'cabang_id' => $request->cabang_id,
                    'no_bill' => $type == '1' ? $request->no_bill : null,
                    'no_bill_2' => $type == '2' ? $request->no_bill : null,
                    'point' => $point,
                    'jml_trans' => $jmlTrans,
                    'type' => $type,
                    'keterangan' => $request->keterangan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::connection('mysql_second')->commit();

            return response()->json([
                'success' => true,
                'message' => $type == '1' ? 'Top up berhasil ditambahkan' : 'Redeem berhasil ditambahkan',
                'data' => [
                    'id' => $pointId,
                    'point' => $point,
                    'jml_trans' => $jmlTrans,
                    'type' => $type,
                ],
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $point = DB::connection('mysql_second')
                ->table('point')
                ->where('id', $id)
                ->first();

            if (!$point) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            DB::connection('mysql_second')
                ->table('point')
                ->where('id', $id)
                ->delete();

            DB::connection('mysql_second')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function searchCustomers(Request $request)
    {
        $search = $request->get('search', '');

        $customers = DB::connection('mysql_second')
            ->table('costumers')
            ->select('id', 'name', 'costumers_id', 'telepon', 'email')
            ->where('status_aktif', '1')
            ->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('costumers_id', 'like', "%{$search}%")
                      ->orWhere('telepon', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    public function getCabangList()
    {
        $cabangs = DB::connection('mysql_second')
            ->table('cabangs')
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($cabangs);
    }

    private function getSummaryData()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // Today's summary
        $todayData = DB::connection('mysql_second')
            ->table('point')
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN type = "1" THEN 1 ELSE 0 END) as top_up_count,
                SUM(CASE WHEN type = "2" THEN 1 ELSE 0 END) as redeem_count,
                SUM(CASE WHEN type = "1" THEN point ELSE 0 END) as top_up_points,
                SUM(CASE WHEN type = "2" THEN point ELSE 0 END) as redeem_points,
                SUM(CASE WHEN type = "1" THEN jml_trans ELSE 0 END) as top_up_value,
                SUM(CASE WHEN type = "2" THEN jml_trans ELSE 0 END) as redeem_value
            ')
            ->whereDate('created_at', $today)
            ->first();

        // This month's summary
        $monthData = DB::connection('mysql_second')
            ->table('point')
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN type = "1" THEN 1 ELSE 0 END) as top_up_count,
                SUM(CASE WHEN type = "2" THEN 1 ELSE 0 END) as redeem_count,
                SUM(CASE WHEN type = "1" THEN point ELSE 0 END) as top_up_points,
                SUM(CASE WHEN type = "2" THEN point ELSE 0 END) as redeem_points,
                SUM(CASE WHEN type = "1" THEN jml_trans ELSE 0 END) as top_up_value,
                SUM(CASE WHEN type = "2" THEN jml_trans ELSE 0 END) as redeem_value
            ')
            ->whereMonth('created_at', $thisMonth->month)
            ->whereYear('created_at', $thisMonth->year)
            ->first();

        return [
            'today' => [
                'total_transactions' => $todayData->total_transactions ?? 0,
                'top_up_count' => $todayData->top_up_count ?? 0,
                'redeem_count' => $todayData->redeem_count ?? 0,
                'top_up_points' => $todayData->top_up_points ?? 0,
                'redeem_points' => $todayData->redeem_points ?? 0,
                'top_up_value' => $todayData->top_up_value ?? 0,
                'redeem_value' => $todayData->redeem_value ?? 0,
            ],
            'this_month' => [
                'total_transactions' => $monthData->total_transactions ?? 0,
                'top_up_count' => $monthData->top_up_count ?? 0,
                'redeem_count' => $monthData->redeem_count ?? 0,
                'top_up_points' => $monthData->top_up_points ?? 0,
                'redeem_points' => $monthData->redeem_points ?? 0,
                'top_up_value' => $monthData->top_up_value ?? 0,
                'redeem_value' => $monthData->redeem_value ?? 0,
            ],
        ];
    }
} 