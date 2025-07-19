<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Point;
use App\Models\Cabang;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerAnalyticsController extends Controller
{
    /**
     * Display the Customer Analytics page
     */
    public function index(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);
        $period = $request->get('period', '1'); // 1 = Jan-Jun, 2 = Jul-Des
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        $search = $request->get('search', '');
        $suspicionLevel = $request->get('suspicion_level', '');
        $sortBy = $request->get('sort_by', 'transaction_count');
        $sortOrder = $request->get('sort_order', 'desc');

        // Get suspicious transactions data with pagination
        $suspiciousData = $this->getSuspiciousTransactions($year, $period, $perPage, $page, $search, $suspicionLevel, $sortBy, $sortOrder);
        
        // Get year options (last 5 years)
        $yearOptions = [];
        for ($i = 0; $i < 5; $i++) {
            $yearOptions[] = Carbon::now()->subYears($i)->year;
        }

        return Inertia::render('Crm/CustomerAnalytics', [
            'suspiciousData' => $suspiciousData,
            'yearOptions' => $yearOptions,
            'currentYear' => $year,
            'currentPeriod' => $period,
            'filters' => [
                'per_page' => $perPage,
                'page' => $page,
                'search' => $search,
                'suspicion_level' => $suspicionLevel,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Get suspicious transactions data
     */
    private function getSuspiciousTransactions($year, $period, $perPage = 10, $page = 1, $search = '', $suspicionLevel = '', $sortBy = 'transaction_count', $sortOrder = 'desc')
    {
        // Set date range based on period
        if ($period == '1') {
            // January - June
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 6, 30)->endOfDay();
            $periodName = 'Januari - Juni ' . $year;
        } else {
            // July - December
            $startDate = Carbon::create($year, 7, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();
            $periodName = 'Juli - Desember ' . $year;
        }

        // Build query for suspicious transactions
        $query = DB::connection('mysql_second')
            ->table('point as p')
            ->select([
                'c.id as customer_id',
                'c.costumers_id',
                'c.name as customer_name',
                'c.telepon',
                'c.email',
                'cb.name as cabang_name',
                'cb.region',
                DB::raw('DATE(p.created_at) as transaction_date'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(p.point) as total_points'),
                DB::raw('SUM(p.jml_trans) as total_value'),
                DB::raw('MIN(p.created_at) as first_transaction'),
                DB::raw('MAX(p.created_at) as last_transaction'),
            ])
            ->join('costumers as c', 'p.costumer_id', '=', 'c.id')
            ->join('cabangs as cb', 'p.cabang_id', '=', 'cb.id')
            ->whereBetween('p.created_at', [$startDate, $endDate])
            ->where('c.status_aktif', '1')
            ->whereNotNull('cb.region')
            ->where('cb.region', '!=', '')
            ->groupBy('c.id', 'c.costumers_id', 'c.name', 'c.telepon', 'c.email', 'cb.name', 'cb.region', DB::raw('DATE(p.created_at)'))
            ->having('transaction_count', '>', 1); // More than 1 transaction per day

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('c.name', 'like', "%{$search}%")
                  ->orWhere('c.costumers_id', 'like', "%{$search}%")
                  ->orWhere('c.telepon', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('cb.name', 'like', "%{$search}%")
                  ->orWhere('cb.region', 'like', "%{$search}%");
            });
        }

        // Apply suspicion level filter
        if ($suspicionLevel) {
            if ($suspicionLevel === 'Tinggi') {
                $query->having('transaction_count', '>=', 5)->orHaving('total_value', '>=', 1000000);
            } elseif ($suspicionLevel === 'Sedang') {
                $query->having('transaction_count', '>=', 3)->orHaving('total_value', '>=', 500000);
            } elseif ($suspicionLevel === 'Rendah') {
                $query->having('transaction_count', '>', 1)->having('transaction_count', '<', 3)->having('total_value', '<', 500000);
            }
        }

        // Apply sorting
        if ($sortBy === 'transaction_count') {
            $query->orderBy('transaction_count', $sortOrder);
        } elseif ($sortBy === 'total_value') {
            $query->orderBy('total_value', $sortOrder);
        } elseif ($sortBy === 'customer_name') {
            $query->orderBy('c.name', $sortOrder);
        } elseif ($sortBy === 'transaction_date') {
            $query->orderBy('transaction_date', $sortOrder);
        } else {
            $query->orderBy('transaction_count', 'desc');
        }

        // Get paginated results
        $suspiciousTransactions = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedTransactions = $suspiciousTransactions->getCollection()->map(function ($item) {
                return [
                    'customer_id' => $item->customer_id,
                    'costumers_id' => $item->costumers_id,
                    'customer_name' => $item->customer_name,
                    'telepon' => $item->telepon,
                    'email' => $item->email,
                    'cabang_name' => $item->cabang_name,
                    'region' => $item->region,
                    'transaction_date' => Carbon::parse($item->transaction_date)->format('d/m/Y'),
                    'transaction_count' => $item->transaction_count,
                    'total_points' => $item->total_points,
                    'total_points_formatted' => number_format($item->total_points, 0, ',', '.'),
                    'total_value' => $item->total_value,
                    'total_value_formatted' => 'Rp ' . number_format($item->total_value, 0, ',', '.'),
                    'first_transaction' => Carbon::parse($item->first_transaction)->format('d/m/Y H:i'),
                    'last_transaction' => Carbon::parse($item->last_transaction)->format('d/m/Y H:i'),
                    'suspicion_level' => $this->getSuspicionLevel($item->transaction_count, $item->total_value),
                ];
            });

        // Get summary statistics (from all data, not just current page)
        $allTransactions = $query->get()->map(function ($item) {
            return [
                'customer_id' => $item->customer_id,
                'costumers_id' => $item->costumers_id,
                'customer_name' => $item->customer_name,
                'telepon' => $item->telepon,
                'email' => $item->email,
                'cabang_name' => $item->cabang_name,
                'region' => $item->region,
                'transaction_date' => Carbon::parse($item->transaction_date)->format('d/m/Y'),
                'transaction_count' => $item->transaction_count,
                'total_points' => $item->total_points,
                'total_points_formatted' => number_format($item->total_points, 0, ',', '.'),
                'total_value' => $item->total_value,
                'total_value_formatted' => 'Rp ' . number_format($item->total_value, 0, ',', '.'),
                'first_transaction' => Carbon::parse($item->first_transaction)->format('d/m/Y H:i'),
                'last_transaction' => Carbon::parse($item->last_transaction)->format('d/m/Y H:i'),
                'suspicion_level' => $this->getSuspicionLevel($item->transaction_count, $item->total_value),
            ];
        });

        $summary = [
            'total_suspicious_customers' => $allTransactions->unique('customer_id')->count(),
            'total_suspicious_days' => $allTransactions->count(),
            'total_transactions' => $allTransactions->sum('transaction_count'),
            'total_points' => $allTransactions->sum('total_points'),
            'total_value' => $allTransactions->sum('total_value'),
            'total_value_formatted' => 'Rp ' . number_format($allTransactions->sum('total_value'), 0, ',', '.'),
            'average_transactions_per_day' => $allTransactions->avg('transaction_count'),
            'period_name' => $periodName,
        ];

        // Group by suspicion level
        $suspicionLevels = $allTransactions->groupBy('suspicion_level')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_value' => $group->sum('total_value'),
                'total_value_formatted' => 'Rp ' . number_format($group->sum('total_value'), 0, ',', '.'),
            ];
        });

        return [
            'transactions' => $formattedTransactions,
            'pagination' => [
                'current_page' => $suspiciousTransactions->currentPage(),
                'last_page' => $suspiciousTransactions->lastPage(),
                'per_page' => $suspiciousTransactions->perPage(),
                'total' => $suspiciousTransactions->total(),
                'from' => $suspiciousTransactions->firstItem(),
                'to' => $suspiciousTransactions->lastItem(),
            ],
            'summary' => $summary,
            'suspicionLevels' => $suspicionLevels,
        ];
    }

    /**
     * Get suspicion level based on transaction count and value
     */
    private function getSuspicionLevel($transactionCount, $totalValue)
    {
        if ($transactionCount >= 5 || $totalValue >= 1000000) {
            return 'Tinggi';
        } elseif ($transactionCount >= 3 || $totalValue >= 500000) {
            return 'Sedang';
        } else {
            return 'Rendah';
        }
    }

    /**
     * Get detailed transactions for a specific customer and date with pagination
     */
    public function getCustomerTransactions(Request $request)
    {
        try {
            $customerId = $request->get('customer_id');
            $date = $request->get('date');
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $type = $request->get('type', '');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'asc');

            // Convert date format from dd/mm/yyyy to yyyy-mm-dd
            $dateParts = explode('/', $date);
            if (count($dateParts) === 3) {
                $formattedDate = $dateParts[2] . '-' . str_pad($dateParts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
            } else {
                $formattedDate = $date; // fallback if format is different
            }

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
                    'cb.name as cabang_name',
                    'c.name as customer_name',
                    'c.email as customer_email',
                    'c.telepon as customer_phone',
                ])
                ->join('cabangs as cb', 'p.cabang_id', '=', 'cb.id')
                ->join('costumers as c', 'p.costumer_id', '=', 'c.id')
                ->where('p.costumer_id', $customerId)
                ->whereDate('p.created_at', $formattedDate);

            // Apply search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('p.no_bill', 'like', "%{$search}%")
                      ->orWhere('p.no_bill_2', 'like', "%{$search}%")
                      ->orWhere('cb.name', 'like', "%{$search}%")
                      ->orWhere('c.name', 'like', "%{$search}%")
                      ->orWhere('c.email', 'like', "%{$search}%")
                      ->orWhere('c.telepon', 'like', "%{$search}%");
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
                    'customer_name' => $item->customer_name,
                    'customer_email' => $item->customer_email,
                    'customer_phone' => $item->customer_phone,
                    'created_at' => Carbon::parse($item->created_at)->format('d/m/Y H:i:s'),
                    'created_at_raw' => $item->created_at,
                ];
            });

            return response()->json([
                'transactions' => $formattedTransactions,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                ],
                'customer_id' => $customerId,
                'date' => $date,
                'formatted_date' => $formattedDate,
                'filters' => [
                    'search' => $search,
                    'type' => $type,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat memuat data transaksi',
                'message' => $e->getMessage(),
                'transactions' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
            ], 500);
        }
    }
} 