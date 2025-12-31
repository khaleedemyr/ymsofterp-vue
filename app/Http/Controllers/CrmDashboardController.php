<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
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
        $totalMembers = MemberAppsMember::count();

        // New members today
        $newMembersToday = MemberAppsMember::whereDate('created_at', $today)->count();

        // New members this month
        $newMembersThisMonth = MemberAppsMember::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();

        // Active members
        $activeMembers = MemberAppsMember::where('is_active', true)->count();

        // Inactive members
        $inactiveMembers = MemberAppsMember::where('is_active', false)->count();

        // Tier breakdown
        $tierBreakdown = [
            'elite' => MemberAppsMember::where('member_level', 'elite')->count(),
            'loyal' => MemberAppsMember::where('member_level', 'loyal')->count(),
            'silver' => MemberAppsMember::where(function($query) {
                $query->where('member_level', 'silver')
                      ->orWhereNull('member_level')
                      ->orWhere('member_level', '');
            })->count(),
        ];

        // Growth rate (compared to last month)
        $lastMonthMembers = MemberAppsMember::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $growthRate = $lastMonthMembers > 0 ? (($newMembersThisMonth - $lastMonthMembers) / $lastMonthMembers) * 100 : 0;

        // Get member demographics by region (based on first transaction from orders)
        // Simplified query - limit to recent data and top 20 outlets for performance
        $memberDemographics = DB::connection('db_justus')
            ->table(DB::raw('orders as o'))
            ->select(
                DB::raw('COALESCE(outlet.nama_outlet, o.kode_outlet, "Tidak Diketahui") as region'),
                DB::raw('COUNT(DISTINCT o.member_id) as member_count')
            )
            ->join(DB::raw('(
                SELECT member_id, MIN(created_at) as first_transaction
                FROM orders
                WHERE member_id IS NOT NULL 
                AND member_id != ""
                AND created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
                GROUP BY member_id
            ) as ft'), function($join) {
                $join->on('o.member_id', '=', 'ft.member_id')
                     ->whereRaw('o.created_at = ft.first_transaction');
            })
            ->leftJoin(DB::raw('tbl_data_outlet as outlet'), 'o.kode_outlet', '=', 'outlet.qr_code')
            ->where('o.member_id', '!=', '')
            ->whereNotNull('o.member_id')
            ->groupBy(DB::raw('COALESCE(outlet.nama_outlet, o.kode_outlet, "Tidak Diketahui")'))
            ->orderBy('member_count', 'desc')
            ->limit(20)
            ->get();

        return [
            'totalMembers' => $totalMembers,
            'newMembersToday' => $newMembersToday,
            'newMembersThisMonth' => $newMembersThisMonth,
            'activeMembers' => $activeMembers,
            'inactiveMembers' => $inactiveMembers,
            'tierBreakdown' => $tierBreakdown,
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

            $newMembers = MemberAppsMember::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $totalMembers = MemberAppsMember::where('created_at', '<=', $endOfMonth)->count();

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
        $genderDistribution = MemberAppsMember::selectRaw('jenis_kelamin, COUNT(*) as count')
            ->groupBy('jenis_kelamin')
            ->get()
            ->mapWithKeys(function ($item) {
                $label = $item->jenis_kelamin === 'L' ? 'Laki-laki' : ($item->jenis_kelamin === 'P' ? 'Perempuan' : 'Tidak Diketahui');
                return [$label => $item->count];
            });

        // Age distribution with new demographic categories
        $ageDistribution = MemberAppsMember::selectRaw('
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

        // Occupation distribution
        $occupationDistribution = MemberAppsMember::selectRaw('
            COALESCE(o.name, "Tidak Diketahui") as occupation_name,
            COUNT(*) as count
        ')
        ->leftJoin('member_apps_occupations as o', 'member_apps_members.pekerjaan_id', '=', 'o.id')
        ->groupBy('o.name')
        ->orderBy('count', 'desc')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->occupation_name => $item->count];
        });

        return [
            'gender' => $genderDistribution,
            'age' => $ageDistribution,
            'occupation' => $occupationDistribution,
        ];
    }

    /**
     * Get purchasing power by age group
     */
    private function getPurchasingPowerByAge($startDate = null, $endDate = null)
    {
        // Optimize: Use simpler SQL query with date limit to improve performance
        $dateFilter = '';
        if ($startDate && $endDate) {
            $startDateEscaped = DB::connection()->getPdo()->quote($startDate);
            $endDateEscaped = DB::connection()->getPdo()->quote($endDate);
            $dateFilter = "AND o.created_at BETWEEN {$startDateEscaped} AND {$endDateEscaped}";
        } else {
            // Default to last 2 years if no date filter for performance
            $twoYearsAgo = DB::connection()->getPdo()->quote(Carbon::now()->subYears(2)->format('Y-m-d'));
            $dateFilter = "AND o.created_at >= {$twoYearsAgo}";
        }
        
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        
        $sql = "
            SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 13 AND 18 THEN 'Remaja'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 19 AND 30 THEN 'Dewasa Muda'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 31 AND 45 THEN 'Dewasa Produktif'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 46 AND 59 THEN 'Dewasa Matang'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) >= 60 THEN 'Usia Tua'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) < 13 THEN 'Anak-anak'
                    ELSE 'Tidak Diketahui'
                END as age_group,
                COUNT(DISTINCT m.id) as total_customers,
                COALESCE(SUM(o.grand_total), 0) as total_spending,
                COALESCE(AVG(o.grand_total), 0) as avg_transaction_value,
                COALESCE(COUNT(o.id), 0) as total_transactions
            FROM member_apps_members m
            LEFT JOIN {$dbJustusName}.orders o ON m.member_id COLLATE utf8mb4_unicode_ci = o.member_id COLLATE utf8mb4_unicode_ci
                AND o.member_id != '' 
                AND o.member_id IS NOT NULL
                {$dateFilter}
            WHERE m.tanggal_lahir IS NOT NULL 
            AND m.is_active = 1
            GROUP BY age_group
            ORDER BY 
                CASE age_group
                    WHEN 'Anak-anak' THEN 1
                    WHEN 'Remaja' THEN 2
                    WHEN 'Dewasa Muda' THEN 3
                    WHEN 'Dewasa Produktif' THEN 4
                    WHEN 'Dewasa Matang' THEN 5
                    WHEN 'Usia Tua' THEN 6
                    ELSE 7
                END
        ";

        $purchasingPower = collect(DB::select($sql))
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
        return MemberAppsMember::select([
            'id',
            'member_id',
            'nama_lengkap',
            'email',
            'mobile_phone',
            'jenis_kelamin',
            'is_active',
            'is_exclusive_member',
            'created_at',
        ])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get()
        ->map(function ($member) {
            return [
                'id' => $member->id,
                'costumers_id' => $member->member_id,
                'name' => $member->nama_lengkap,
                'email' => $member->email,
                'telepon' => $member->mobile_phone,
                'jenis_kelamin_text' => $member->jenis_kelamin === 'L' ? 'Laki-laki' : ($member->jenis_kelamin === 'P' ? 'Perempuan' : 'Tidak Diketahui'),
                'status_aktif_text' => $member->is_active ? 'Aktif' : 'Tidak Aktif',
                'exclusive_member_text' => $member->is_exclusive_member ? 'Ya' : 'Tidak',
                'tanggal_register_text' => $member->created_at ? $member->created_at->format('d/m/Y') : '-',
                'valid_until_text' => '-', // Not available in new structure
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
        $recentRegistrations = MemberAppsMember::where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->nama_lengkap,
                    'activity' => 'Member baru mendaftar',
                    'icon' => 'fa-solid fa-user-plus',
                    'color' => 'text-green-600',
                    'bg_color' => 'bg-green-100',
                    'created_at' => $member->created_at,
                    'type' => 'registration',
                    'member_id' => $member->member_id,
                ];
            });

        $activities = $activities->concat($recentRegistrations);

        // Get recent top up transactions (point_amount > 0) - optimize with batch loading
        $recentTopUps = MemberAppsPointTransaction::where('point_amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // Batch load members and outlets
        $memberIds = $recentTopUps->pluck('member_id')->unique()->filter()->toArray();
        $outletIds = $recentTopUps->pluck('outlet_id')->unique()->filter()->toArray();
        
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        $outlets = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIds)
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');
        
        $recentTopUps = $recentTopUps->map(function ($pt) use ($members, $outlets) {
            $member = $members->get($pt->member_id);
            $outlet = $outlets->get($pt->outlet_id);
            $outletName = $outlet->nama_outlet ?? 'Outlet Tidak Diketahui';
            
            return [
                'id' => $pt->id,
                'name' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'activity' => 'Top up ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point',
                'sub_activity' => $outletName,
                'icon' => 'fa-solid fa-plus-circle',
                'color' => 'text-blue-600',
                'bg_color' => 'bg-blue-100',
                'created_at' => $pt->created_at,
                'type' => 'topup',
                'point' => abs($pt->point_amount),
                'point_formatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                'jml_trans' => $pt->transaction_amount ?? 0,
                'jml_trans_formatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                'cabang_name' => $outletName,
                'member_id' => $member->member_id ?? '-',
            ];
        });

        $activities = $activities->concat($recentTopUps);

        // Get recent redeem transactions (point_amount < 0) - optimize with batch loading
        $recentRedeems = MemberAppsPointTransaction::where('point_amount', '<', 0)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // Batch load members and outlets for redeems
        $memberIdsRedeem = $recentRedeems->pluck('member_id')->unique()->filter()->toArray();
        $outletIdsRedeem = $recentRedeems->pluck('outlet_id')->unique()->filter()->toArray();
        
        $membersRedeem = MemberAppsMember::whereIn('id', $memberIdsRedeem)->get()->keyBy('id');
        $outletsRedeem = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIdsRedeem)
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');
        
        $recentRedeems = $recentRedeems->map(function ($pt) use ($membersRedeem, $outletsRedeem) {
            $member = $membersRedeem->get($pt->member_id);
            $outlet = $outletsRedeem->get($pt->outlet_id);
            $outletName = $outlet->nama_outlet ?? 'Outlet Tidak Diketahui';
            
            return [
                'id' => $pt->id,
                'name' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'activity' => 'Redeem ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point',
                'sub_activity' => $outletName,
                'icon' => 'fa-solid fa-minus-circle',
                'color' => 'text-orange-600',
                'bg_color' => 'bg-orange-100',
                'created_at' => $pt->created_at,
                'type' => 'redeem',
                'point' => abs($pt->point_amount),
                'point_formatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                'jml_trans' => $pt->transaction_amount ?? 0,
                'jml_trans_formatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                'cabang_name' => $outletName,
                'member_id' => $member->member_id ?? '-',
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
        $baseQuery = MemberAppsPointTransaction::query();
        $todayQuery = MemberAppsPointTransaction::query();
        $thisMonthQuery = MemberAppsPointTransaction::query();
        $lastMonthQuery = MemberAppsPointTransaction::query();

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
            MemberAppsPointTransaction::whereDate('created_at', $today)->count();

        // Point transactions this month (if no date filter, use this month)
        $transactionsThisMonth = $startDate && $endDate ? 
            $thisMonthQuery->whereBetween('created_at', [$thisMonth, Carbon::now()])->count() : 
            MemberAppsPointTransaction::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();

        // Total point earned (point_amount > 0)
        $totalPointEarned = (clone $baseQuery)->where('point_amount', '>', 0)->sum('point_amount');

        // Total point redeemed (point_amount < 0, use absolute value)
        $totalPointRedeemed = abs((clone $baseQuery)->where('point_amount', '<', 0)->sum('point_amount'));

        // Total transaction value
        $totalTransactionValue = (clone $baseQuery)->sum('transaction_amount');

        // Growth rate (compared to last month)
        $lastMonthTransactions = $startDate && $endDate ? 
            $lastMonthQuery->whereBetween('created_at', [$lastMonth, $thisMonth])->count() : 
            MemberAppsPointTransaction::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $growthRate = $lastMonthTransactions > 0 ? (($transactionsThisMonth - $lastMonthTransactions) / $lastMonthTransactions) * 100 : 0;

        return [
            'totalTransactions' => $totalTransactions,
            'transactionsToday' => $transactionsToday,
            'transactionsThisMonth' => $transactionsThisMonth,
            'totalPointEarned' => $totalPointEarned,
            'totalPointEarnedFormatted' => number_format($totalPointEarned, 0, ',', '.'),
            'totalPointRedeemed' => $totalPointRedeemed,
            'totalPointRedeemedFormatted' => number_format($totalPointRedeemed, 0, ',', '.'),
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
        $transactions = MemberAppsPointTransaction::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Batch load members and outlets
        $memberIds = $transactions->pluck('member_id')->unique()->filter()->toArray();
        $outletIds = $transactions->pluck('outlet_id')->unique()->filter()->toArray();
        
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        $outlets = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIds)
            ->select('id_outlet', 'nama_outlet')
            ->get()
            ->keyBy('id_outlet');
        
        return $transactions->map(function ($pt) use ($members, $outlets) {
            $member = $members->get($pt->member_id);
            $outlet = $outlets->get($pt->outlet_id);
            $outletName = $outlet->nama_outlet ?? 'Outlet Tidak Diketahui';
            
            $isEarned = $pt->point_amount > 0;
            $type = $isEarned ? '1' : '2';
            $typeText = $isEarned ? 'EARNED' : 'REDEEMED';
            
            return [
                'id' => $pt->id,
                'bill_number' => $pt->serial_code ?? $pt->reference_number ?? '-',
                'customer_name' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'customer_id' => $member->member_id ?? '-',
                'cabang_name' => $outletName,
                'point' => abs($pt->point_amount),
                'point_formatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                'jml_trans' => $pt->transaction_amount ?? 0,
                'jml_trans_formatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                'type' => $type,
                'type_text' => $typeText,
                'created_at' => $pt->created_at ? $pt->created_at->format('d/m/Y H:i') : '-',
                'status' => 'completed',
                'icon' => $isEarned ? 'fa-solid fa-plus-circle text-green-600' : 'fa-solid fa-minus-circle text-orange-600',
                'color' => $isEarned ? 'text-green-600' : 'text-orange-600',
            ];
        });
    }

    /**
     * Get point distribution by cabang
     */
    private function getPointByCabang($startDate = null, $endDate = null)
    {
        $query = MemberAppsPointTransaction::selectRaw('outlet_id, COUNT(*) as total_transactions, SUM(point_amount) as total_points, SUM(transaction_amount) as total_value')
            ->whereNotNull('outlet_id')
            ->groupBy('outlet_id');

        // Filter by date range if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $results = $query->orderBy('total_transactions', 'desc')
            ->limit(10)
            ->get();

        return $results->map(function ($item) use ($startDate, $endDate) {
            // Get outlet name
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $item->outlet_id)->first();
            $outletName = $outlet->nama_outlet ?? 'Outlet Tidak Diketahui';
            
            // Get redeem data for this outlet (point_amount < 0)
            $redeemQuery = MemberAppsPointTransaction::where('outlet_id', $item->outlet_id)
                ->where('point_amount', '<', 0);

            // Apply same date filter if provided
            if ($startDate && $endDate) {
                $redeemQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $redeemData = $redeemQuery->selectRaw('COUNT(*) as total_redeem, SUM(ABS(point_amount)) as total_redeem_points, SUM(transaction_amount) as total_redeem_value')
                ->first();

            return [
                'cabang_id' => $item->outlet_id,
                'cabang_name' => $outletName,
                'total_transactions' => $item->total_transactions,
                'total_points' => abs($item->total_points ?? 0),
                'total_points_formatted' => number_format(abs($item->total_points ?? 0), 0, ',', '.'),
                'total_value' => $item->total_value ?? 0,
                'total_value_formatted' => 'Rp ' . number_format($item->total_value ?? 0, 0, ',', '.'),
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

        $query = MemberAppsPointTransaction::where('outlet_id', $cabangId)
            ->where('point_amount', '<', 0);

        // Filter by date range if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $redeemDetails = $query->orderBy('created_at', 'desc')
            ->limit(50)  // Limit to 50 records to prevent too much data
            ->get();

        // Get outlet name
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $cabangId)->first();
        $cabangName = $outlet->nama_outlet ?? 'Outlet Tidak Diketahui';

        $mappedDetails = $redeemDetails->map(function ($pt) {
            $member = MemberAppsMember::find($pt->member_id);
            return [
                'id' => $pt->id,
                'customer_name' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'customer_id' => $member->member_id ?? '-',
                'point' => abs($pt->point_amount),
                'point_formatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                'jml_trans' => $pt->transaction_amount ?? 0,
                'jml_trans_formatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                'bill_number' => $pt->serial_code ?? $pt->reference_number ?? '-',
                'created_at' => $pt->created_at ? $pt->created_at->format('d/m/Y H:i') : '-',
                'created_at_full' => $pt->created_at ? $pt->created_at->format('d/m/Y H:i:s') : '-',
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