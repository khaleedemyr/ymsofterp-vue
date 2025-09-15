<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Point;
use App\Models\Cabang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class CrmDashboardController extends Controller
{
    /**
     * Display the CRM dashboard
     */
    public function index(Request $request)
    {
        // Get date range from request first
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $stats = $this->getStats();
        $memberGrowth = $this->getMemberGrowth();
        $memberDemographics = $this->getMemberDemographics();
        $purchasingPowerByAge = $this->getPurchasingPowerByAge($startDate, $endDate);
        $latestMembers = $this->getLatestMembers();
        $memberActivity = $this->getMemberActivity();
        $pointStats = $this->getPointStats($startDate, $endDate);
        $pointTransactions = $this->getLatestPointTransactions();
        $pointByCabang = $this->getPointByCabang($startDate, $endDate);

        return Inertia::render('Crm/Dashboard', [
            'stats' => $stats,
            'memberGrowth' => $memberGrowth,
            'memberDemographics' => $memberDemographics,
            'purchasingPowerByAge' => $purchasingPowerByAge,
            'memberDemographicsByRegion' => $stats['memberDemographics'],
            'latestMembers' => $latestMembers,
            'memberActivity' => $memberActivity,
            'pointStats' => $pointStats,
            'pointTransactions' => $pointTransactions,
            'pointByCabang' => $pointByCabang,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Get dashboard statistics
     */
    private function getStats()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Total members
        $totalMembers = Customer::count();

        // New members today
        $newMembersToday = Customer::whereDate('tanggal_register', $today)->count();

        // New members this month
        $newMembersThisMonth = Customer::whereBetween('tanggal_register', [$thisMonth, Carbon::now()])->count();

        // Active members
        $activeMembers = Customer::where('status_aktif', '1')->count();

        // Inactive members
        $inactiveMembers = Customer::where('status_aktif', '0')->count();

        // Exclusive members
        $exclusiveMembers = Customer::where('exclusive_member', 'Y')->count();

        // Growth rate (compared to last month)
        $lastMonthMembers = Customer::whereBetween('tanggal_register', [$lastMonth, $thisMonth])->count();
        $growthRate = $lastMonthMembers > 0 ? (($newMembersThisMonth - $lastMonthMembers) / $lastMonthMembers) * 100 : 0;

        // Get member demographics by region (based on first transaction)
        $memberDemographics = DB::connection('mysql_second')
            ->table('costumers as c')
            ->select(
                'cb.region',
                DB::raw('COUNT(DISTINCT c.id) as member_count')
            )
            ->join(DB::raw('(SELECT costumer_id, MIN(created_at) as first_transaction FROM point GROUP BY costumer_id) as ft'), 'c.id', '=', 'ft.costumer_id')
            ->join('point as p', function($join) {
                $join->on('c.id', '=', 'p.costumer_id')
                     ->on('ft.first_transaction', '=', 'p.created_at');
            })
            ->join('cabangs as cb', 'p.cabang_id', '=', 'cb.id')
            ->where('c.status_aktif', '1')
            ->whereNotNull('cb.region')
            ->where('cb.region', '!=', '')
            ->groupBy('cb.region')
            ->orderBy('member_count', 'desc')
            ->get();

        return [
            'totalMembers' => $totalMembers,
            'newMembersToday' => $newMembersToday,
            'newMembersThisMonth' => $newMembersThisMonth,
            'activeMembers' => $activeMembers,
            'inactiveMembers' => $inactiveMembers,
            'exclusiveMembers' => $exclusiveMembers,
            'growthRate' => round($growthRate, 2),
            'memberDemographics' => $memberDemographics,
        ];
    }

    /**
     * Get member growth trend (last 12 months)
     */
    private function getMemberGrowth()
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $newMembers = Customer::whereBetween('tanggal_register', [$startOfMonth, $endOfMonth])->count();
            $totalMembers = Customer::where('tanggal_register', '<=', $endOfMonth)->count();

            $data[] = [
                'month' => $date->format('M Y'),
                'newMembers' => $newMembers,
                'totalMembers' => $totalMembers,
            ];
        }

        return $data;
    }

    /**
     * Get member demographics
     */
    private function getMemberDemographics()
    {
        // Gender distribution
        $genderDistribution = Customer::selectRaw('jenis_kelamin, COUNT(*) as count')
            ->groupBy('jenis_kelamin')
            ->get()
            ->mapWithKeys(function ($item) {
                $label = $item->jenis_kelamin === '1' ? 'Laki-laki' : ($item->jenis_kelamin === '2' ? 'Perempuan' : 'Tidak Diketahui');
                return [$label => $item->count];
            });

        // Age distribution with new demographic categories
        $ageDistribution = Customer::selectRaw('
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 13 AND 18 THEN "Remaja"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 19 AND 30 THEN "Dewasa Muda"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 31 AND 45 THEN "Dewasa Produktif"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 46 AND 59 THEN "Dewasa Matang"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= 60 THEN "Usia Tua"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) < 13 THEN "Anak-anak"
                ELSE "Tidak Diketahui"
            END as age_group,
            COUNT(*) as count
        ')
        ->whereNotNull('tanggal_lahir')
        ->groupBy('age_group')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->age_group => $item->count];
        });

        return [
            'gender' => $genderDistribution,
            'age' => $ageDistribution,
        ];
    }

    /**
     * Get purchasing power by age group
     */
    private function getPurchasingPowerByAge($startDate = null, $endDate = null)
    {
        // Build the query
        $query = Customer::selectRaw('
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 13 AND 18 THEN "Remaja"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 19 AND 30 THEN "Dewasa Muda"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 31 AND 45 THEN "Dewasa Produktif"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 46 AND 59 THEN "Dewasa Matang"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= 60 THEN "Usia Tua"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) < 13 THEN "Anak-anak"
                ELSE "Tidak Diketahui"
            END as age_group,
            COUNT(DISTINCT c.id) as total_customers,
            COALESCE(SUM(p.jml_trans), 0) as total_spending,
            COALESCE(AVG(p.jml_trans), 0) as avg_transaction_value,
            COALESCE(COUNT(p.id), 0) as total_transactions
        ')
        ->from('costumers as c')
        ->leftJoin('point as p', 'c.id', '=', 'p.costumer_id')
        ->whereNotNull('c.tanggal_lahir')
        ->where('c.status_aktif', '1');

        // Apply date filter if provided
        if ($startDate && $endDate) {
            $query->whereBetween('p.created_at', [$startDate, $endDate]);
        }

        $purchasingPower = $query->groupBy('age_group')
        ->orderByRaw('
            CASE age_group
                WHEN "Anak-anak" THEN 1
                WHEN "Remaja" THEN 2
                WHEN "Dewasa Muda" THEN 3
                WHEN "Dewasa Produktif" THEN 4
                WHEN "Dewasa Matang" THEN 5
                WHEN "Usia Tua" THEN 6
                ELSE 7
            END
        ')
        ->get()
        ->map(function ($item) {
            return [
                'age_group' => $item->age_group,
                'total_customers' => (int) $item->total_customers,
                'total_spending' => (float) $item->total_spending,
                'total_spending_formatted' => 'Rp ' . number_format($item->total_spending, 0, ',', '.'),
                'avg_transaction_value' => (float) $item->avg_transaction_value,
                'avg_transaction_value_formatted' => 'Rp ' . number_format($item->avg_transaction_value, 0, ',', '.'),
                'total_transactions' => (int) $item->total_transactions,
                'avg_spending_per_customer' => $item->total_customers > 0 ? (float) ($item->total_spending / $item->total_customers) : 0,
                'avg_spending_per_customer_formatted' => $item->total_customers > 0 ? 'Rp ' . number_format($item->total_spending / $item->total_customers, 0, ',', '.') : 'Rp 0',
            ];
        });

        return $purchasingPower;
    }



    /**
     * Get latest members (last 10)
     */
    private function getLatestMembers()
    {
        return Customer::select([
            'id',
            'costumers_id',
            'name',
            'email',
            'telepon',
            'jenis_kelamin',
            'status_aktif',
            'exclusive_member',
            'tanggal_register',
            'valid_until',
        ])
        ->orderBy('tanggal_register', 'desc')
        ->limit(10)
        ->get()
        ->map(function ($member) {
            return [
                'id' => $member->id,
                'costumers_id' => $member->costumers_id,
                'name' => $member->name,
                'email' => $member->email,
                'telepon' => $member->telepon,
                'jenis_kelamin_text' => $member->jenis_kelamin_text,
                'status_aktif_text' => $member->status_aktif_text,
                'exclusive_member_text' => $member->exclusive_member_text,
                'tanggal_register_text' => $member->tanggal_register_text,
                'valid_until_text' => $member->valid_until_text,
            ];
        });
    }

    /**
     * Get member activity (combining registrations, top up, and redeem)
     */
    private function getMemberActivity()
    {
        $activities = collect();

        // Get recent member registrations
        $recentRegistrations = Customer::where('tanggal_register', '>=', Carbon::now()->subDays(30))
            ->orderBy('tanggal_register', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'activity' => 'Member baru mendaftar',
                    'icon' => 'fa-solid fa-user-plus',
                    'color' => 'text-green-600',
                    'bg_color' => 'bg-green-100',
                    'created_at' => $member->tanggal_register,
                    'type' => 'registration',
                    'member_id' => $member->costumers_id,
                ];
            });

        $activities = $activities->concat($recentRegistrations);

        // Get recent top up transactions
        $recentTopUps = Point::with(['customer', 'cabang'])
            ->topUp()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($point) {
                return [
                    'id' => $point->id,
                    'name' => $point->customer->name ?? 'Member Tidak Diketahui',
                    'activity' => 'Top up ' . $point->point_formatted . ' point',
                    'sub_activity' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
                    'icon' => 'fa-solid fa-plus-circle',
                    'color' => 'text-blue-600',
                    'bg_color' => 'bg-blue-100',
                    'created_at' => $point->created_at,
                    'type' => 'topup',
                    'point' => $point->point,
                    'point_formatted' => $point->point_formatted,
                    'jml_trans' => $point->jml_trans,
                    'jml_trans_formatted' => $point->jml_trans_formatted,
                    'cabang_name' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
                    'member_id' => $point->customer->costumers_id ?? '-',
                ];
            });

        $activities = $activities->concat($recentTopUps);

        // Get recent redeem transactions
        $recentRedeems = Point::with(['customer', 'cabang'])
            ->redeem()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($point) {
                return [
                    'id' => $point->id,
                    'name' => $point->customer->name ?? 'Member Tidak Diketahui',
                    'activity' => 'Redeem ' . $point->point_formatted . ' point',
                    'sub_activity' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
                    'icon' => 'fa-solid fa-minus-circle',
                    'color' => 'text-orange-600',
                    'bg_color' => 'bg-orange-100',
                    'created_at' => $point->created_at,
                    'type' => 'redeem',
                    'point' => $point->point,
                    'point_formatted' => $point->point_formatted,
                    'jml_trans' => $point->jml_trans,
                    'jml_trans_formatted' => $point->jml_trans_formatted,
                    'cabang_name' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
                    'member_id' => $point->customer->costumers_id ?? '-',
                ];
            });

        $activities = $activities->concat($recentRedeems);

        // Sort all activities by created_at and take the latest 10
        $result = $activities->sortByDesc('created_at')
            ->take(10)
            ->map(function ($activity) {
                return [
                    'id' => $activity['id'],
                    'name' => $activity['name'],
                    'activity' => $activity['activity'],
                    'sub_activity' => $activity['sub_activity'] ?? null,
                    'icon' => $activity['icon'],
                    'color' => $activity['color'],
                    'bg_color' => $activity['bg_color'],
                    'created_at' => $activity['created_at']->format('d/m/Y H:i'),
                    'type' => $activity['type'],
                    'point' => $activity['point'] ?? null,
                    'point_formatted' => $activity['point_formatted'] ?? null,
                    'jml_trans' => $activity['jml_trans'] ?? null,
                    'jml_trans_formatted' => $activity['jml_trans_formatted'] ?? null,
                    'cabang_name' => $activity['cabang_name'] ?? null,
                    'member_id' => $activity['member_id'] ?? null,
                ];
            });
        
        // Convert to array to ensure frontend compatibility
        return $result->toArray();
    }

    /**
     * Get point statistics
     */
    private function getPointStats($startDate = null, $endDate = null)
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Base query
        $baseQuery = Point::query();
        $todayQuery = Point::query();
        $thisMonthQuery = Point::query();
        $lastMonthQuery = Point::query();

        // Apply date filter if provided
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('created_at', [$startDate, $endDate]);
            $todayQuery->whereBetween('created_at', [$startDate, $endDate]);
            $thisMonthQuery->whereBetween('created_at', [$startDate, $endDate]);
            $lastMonthQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Total point transactions
        $totalTransactions = $baseQuery->count();

        // Point transactions today (if no date filter, use today)
        $transactionsToday = $startDate && $endDate ? 
            $todayQuery->whereDate('created_at', $today)->count() : 
            Point::whereDate('created_at', $today)->count();

        // Point transactions this month (if no date filter, use this month)
        $transactionsThisMonth = $startDate && $endDate ? 
            $thisMonthQuery->whereBetween('created_at', [$thisMonth, Carbon::now()])->count() : 
            Point::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();

        // Total point earned (top up)
        $totalPointEarned = $baseQuery->clone()->topUp()->sum('point');

        // Total point redeemed
        $totalPointRedeemed = $baseQuery->clone()->redeem()->sum('point');

        // Total transaction value
        $totalTransactionValue = $baseQuery->clone()->sum('jml_trans');

        // Growth rate (compared to last month)
        $lastMonthTransactions = $startDate && $endDate ? 
            $lastMonthQuery->whereBetween('created_at', [$lastMonth, $thisMonth])->count() : 
            Point::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $growthRate = $lastMonthTransactions > 0 ? (($transactionsThisMonth - $lastMonthTransactions) / $lastMonthTransactions) * 100 : 0;

        return [
            'totalTransactions' => $totalTransactions,
            'transactionsToday' => $transactionsToday,
            'transactionsThisMonth' => $transactionsThisMonth,
            'totalPointEarned' => $totalPointEarned,
            'totalPointEarnedFormatted' => 'Rp ' . number_format($totalPointEarned, 0, ',', '.'),
            'totalPointRedeemed' => $totalPointRedeemed,
            'totalPointRedeemedFormatted' => 'Rp ' . number_format($totalPointRedeemed, 0, ',', '.'),
            'totalTransactionValue' => $totalTransactionValue,
            'totalTransactionValueFormatted' => 'Rp ' . number_format($totalTransactionValue, 0, ',', '.'),
            'growthRate' => round($growthRate, 2),
        ];
    }

    /**
     * Get latest point transactions
     */
    private function getLatestPointTransactions()
    {
        return Point::with(['customer', 'cabang'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($point) {
                return [
                    'id' => $point->id,
                    'bill_number' => $point->bill_number,
                    'customer_name' => $point->customer->name ?? 'Member Tidak Diketahui',
                    'customer_id' => $point->customer->costumers_id ?? '-',
                    'cabang_name' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
                    'point' => $point->point,
                    'point_formatted' => $point->point_formatted,
                    'jml_trans' => $point->jml_trans,
                    'jml_trans_formatted' => $point->jml_trans_formatted,
                    'type' => $point->type,
                    'type_text' => $point->type_text,
                    'created_at' => $point->created_at_text,
                    'status' => $point->status,
                    'icon' => $point->icon,
                    'color' => $point->color,
                ];
            });
    }

    /**
     * Get point distribution by cabang
     */
    private function getPointByCabang($startDate = null, $endDate = null)
    {
        $query = Point::with('cabang')
            ->excludeResetPoint()
            ->selectRaw('cabang_id, COUNT(*) as total_transactions, SUM(point) as total_points, SUM(jml_trans) as total_value')
            ->groupBy('cabang_id');

        // Filter by date range if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $results = $query->orderBy('total_transactions', 'desc')
            ->limit(10)
            ->get();

        return $results->map(function ($item) use ($startDate, $endDate) {
            // Get redeem data for this cabang
            $redeemQuery = Point::where('cabang_id', $item->cabang_id)
                ->redeem();

            // Apply same date filter if provided
            if ($startDate && $endDate) {
                $redeemQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $redeemData = $redeemQuery->selectRaw('COUNT(*) as total_redeem, SUM(point) as total_redeem_points, SUM(jml_trans) as total_redeem_value')
                ->first();

            return [
                'cabang_id' => $item->cabang_id,
                'cabang_name' => $item->cabang->name ?? 'Cabang Tidak Diketahui',
                'total_transactions' => $item->total_transactions,
                'total_points' => $item->total_points,
                'total_points_formatted' => number_format($item->total_points, 0, ',', '.'),
                'total_value' => $item->total_value,
                'total_value_formatted' => 'Rp ' . number_format($item->total_value, 0, ',', '.'),
                'total_redeem' => $redeemData->total_redeem ?? 0,
                'total_redeem_points' => $redeemData->total_redeem_points ?? 0,
                'total_redeem_points_formatted' => number_format($redeemData->total_redeem_points ?? 0, 0, ',', '.'),
                'total_redeem_value' => $redeemData->total_redeem_value ?? 0,
                'total_redeem_value_formatted' => 'Rp ' . number_format($redeemData->total_redeem_value ?? 0, 0, ',', '.'),
            ];
        });
    }

    /**
     * Get redeem details by cabang
     */
    public function getRedeemDetails(Request $request)
    {
        $cabangId = $request->get('cabang_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = Point::with(['customer', 'cabang'])
            ->where('cabang_id', $cabangId)
            ->redeem();

        // Filter by date range if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $redeemDetails = $query->orderBy('created_at', 'desc')
            ->limit(50)  // Limit to 50 records to prevent too much data
            ->get();

        // Get cabang name from first record
        $cabangName = 'Cabang Tidak Diketahui';
        if ($redeemDetails->count() > 0) {
            $firstRecord = $redeemDetails->first();
            $cabangName = $firstRecord->cabang->name ?? 'Cabang Tidak Diketahui';
        }

        $mappedDetails = $redeemDetails->map(function ($point) {
            return [
                'id' => $point->id,
                'customer_name' => $point->customer->name ?? 'Member Tidak Diketahui',
                'customer_id' => $point->customer->costumers_id ?? '-',
                'point' => $point->point,
                'point_formatted' => $point->point_formatted,
                'jml_trans' => $point->jml_trans,
                'jml_trans_formatted' => $point->jml_trans_formatted,
                'bill_number' => $point->bill_number,
                'created_at' => $point->created_at_text,
                'created_at_full' => $point->created_at ? $point->created_at->format('d/m/Y H:i:s') : '-',
            ];
        });

        return response()->json([
            'cabang_name' => $cabangName,
            'redeem_details' => $mappedDetails,
            'total_redeem' => $mappedDetails->count(),
            'total_points' => $mappedDetails->sum('point'),
            'total_value' => $mappedDetails->sum('jml_trans'),
        ]);
    }

    /**
     * Get API data for charts
     */
    public function getChartData(Request $request)
    {
        $type = $request->get('type', 'growth');

        switch ($type) {
            case 'growth':
                return response()->json($this->getMemberGrowth());
            case 'demographics':
                return response()->json($this->getMemberDemographics());
            case 'purchasingPower':
                $startDate = $request->get('start_date');
                $endDate = $request->get('end_date');
                return response()->json($this->getPurchasingPowerByAge($startDate, $endDate));
            case 'status':
                return response()->json([]);
            case 'point':
                return response()->json($this->getPointStats());
            case 'pointByCabang':
                $startDate = $request->get('start_date');
                $endDate = $request->get('end_date');
                return response()->json($this->getPointByCabang($startDate, $endDate));
            default:
                return response()->json([]);
        }
    }
} 