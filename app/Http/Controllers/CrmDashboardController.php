<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Inertia\Inertia;

class CrmDashboardController extends Controller
{
    /**
     * Display the CRM dashboard with new member structure
     */
    public function index(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            
            // Load critical data first (fast queries)
            Log::info('CRM Dashboard: Loading critical data...');
            $stats = $this->getStats();
            Log::info('CRM Dashboard: Stats loaded');
            
            $memberGrowth = $this->getMemberGrowth();
            Log::info('CRM Dashboard: Member growth loaded');
            
            $tierDistribution = $this->getTierDistribution();
            Log::info('CRM Dashboard: Tier distribution loaded');
            
            $spendingTrend = $this->getSpendingTrend($startDate, $endDate);
            Log::info('CRM Dashboard: Spending trend loaded');
            
            $pointActivityTrend = $this->getPointActivityTrend($startDate, $endDate);
            Log::info('CRM Dashboard: Point activity trend loaded');
            
            $latestMembers = $this->getLatestMembers();
            Log::info('CRM Dashboard: Latest members loaded');
            
            $latestPointTransactions = $this->getLatestPointTransactions();
            Log::info('CRM Dashboard: Latest point transactions loaded');
            
            // Load with timeout protection
            $topSpenders = [];
            try {
                $topSpenders = $this->getTopSpenders();
                Log::info('CRM Dashboard: Top spenders loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load top spenders: ' . $e->getMessage());
            }
            
            $mostActiveMembers = [];
            try {
                $mostActiveMembers = $this->getMostActiveMembers();
                Log::info('CRM Dashboard: Most active members loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load most active members: ' . $e->getMessage());
            }
            
            $pointStats = [];
            try {
                $pointStats = $this->getPointStats($startDate, $endDate);
                Log::info('CRM Dashboard: Point stats loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load point stats: ' . $e->getMessage());
            }
            
            // Load lightweight queries with caching
            $genderDistribution = [];
            try {
                $genderDistribution = Cache::remember('crm_gender_distribution', 300, function () {
                    return $this->getGenderDistribution();
                });
                Log::info('CRM Dashboard: Gender distribution loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load gender distribution: ' . $e->getMessage());
            }
            
            $ageDistribution = [];
            try {
                $ageDistribution = Cache::remember('crm_age_distribution', 300, function () {
                    return $this->getAgeDistribution();
                });
                Log::info('CRM Dashboard: Age distribution loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load age distribution: ' . $e->getMessage());
            }
            
            $latestActivities = [];
            try {
                $latestActivities = $this->getLatestActivities();
                Log::info('CRM Dashboard: Latest activities loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load latest activities: ' . $e->getMessage());
            }
            
            // Load purchasing power by age with caching
            $purchasingPowerByAge = [];
            try {
                $purchasingPowerByAge = Cache::remember('crm_purchasing_power_by_age', 300, function () use ($startDate, $endDate) {
                    return $this->getPurchasingPowerByAge($startDate, $endDate);
                });
                Log::info('CRM Dashboard: Purchasing power by age loaded');
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load purchasing power by age: ' . $e->getMessage());
            }
            
            // Skip heavy queries for now - load only critical data
            // These can be loaded via AJAX later if needed
            Log::info('CRM Dashboard: Skipping heavy queries, using empty defaults');
            $engagementMetrics = [];
            $memberSegmentation = [];
            $memberLifetimeValue = [];
            $churnAnalysis = [];
            $conversionFunnel = [];
            $regionalBreakdown = [];
            $comparisonData = [];
            
            Log::info('CRM Dashboard: All critical data loaded, preparing response...');
            
            // Convert all collections to arrays to reduce serialization overhead
            $responseData = [
                'stats' => is_array($stats) ? $stats : (is_object($stats) ? (array) $stats : []),
                'memberGrowth' => is_array($memberGrowth) ? $memberGrowth : $memberGrowth->toArray(),
                'tierDistribution' => is_array($tierDistribution) ? $tierDistribution : $tierDistribution->toArray(),
                'genderDistribution' => is_array($genderDistribution) ? $genderDistribution : (is_object($genderDistribution) ? $genderDistribution->toArray() : []),
                'ageDistribution' => is_array($ageDistribution) ? $ageDistribution : (is_object($ageDistribution) ? $ageDistribution->toArray() : []),
                'purchasingPowerByAge' => is_array($purchasingPowerByAge) ? $purchasingPowerByAge : (is_object($purchasingPowerByAge) ? $purchasingPowerByAge->toArray() : []),
                'spendingTrend' => is_array($spendingTrend) ? $spendingTrend : $spendingTrend->toArray(),
                'pointActivityTrend' => is_array($pointActivityTrend) ? $pointActivityTrend : $pointActivityTrend->toArray(),
                'latestMembers' => is_array($latestMembers) ? $latestMembers : $latestMembers->toArray(),
                'latestPointTransactions' => is_array($latestPointTransactions) ? $latestPointTransactions : $latestPointTransactions->toArray(),
                'latestActivities' => is_array($latestActivities) ? $latestActivities : (is_object($latestActivities) ? $latestActivities->toArray() : []),
                'topSpenders' => is_array($topSpenders) ? $topSpenders : (is_object($topSpenders) ? $topSpenders->toArray() : []),
                'mostActiveMembers' => is_array($mostActiveMembers) ? $mostActiveMembers : (is_object($mostActiveMembers) ? $mostActiveMembers->toArray() : []),
                'pointStats' => is_array($pointStats) ? $pointStats : (is_object($pointStats) ? (array) $pointStats : []),
                'engagementMetrics' => is_array($engagementMetrics) ? $engagementMetrics : (is_object($engagementMetrics) ? (array) $engagementMetrics : []),
                'memberSegmentation' => is_array($memberSegmentation) ? (object)$memberSegmentation : (is_object($memberSegmentation) ? $memberSegmentation : (object)['vip' => 0, 'active' => 0, 'new' => 0, 'atRisk' => 0, 'dormant' => 0]),
                'memberLifetimeValue' => is_array($memberLifetimeValue) ? (object)$memberLifetimeValue : (is_object($memberLifetimeValue) ? $memberLifetimeValue : (object)[]),
                'churnAnalysis' => is_array($churnAnalysis) ? (object)$churnAnalysis : (is_object($churnAnalysis) ? $churnAnalysis : (object)[]),
                'conversionFunnel' => is_array($conversionFunnel) ? (object)$conversionFunnel : (is_object($conversionFunnel) ? $conversionFunnel : (object)[]),
                'regionalBreakdown' => is_array($regionalBreakdown) ? $regionalBreakdown : (is_object($regionalBreakdown) ? $regionalBreakdown->toArray() : []),
                'comparisonData' => is_array($comparisonData) ? $comparisonData : (is_object($comparisonData) ? (array) $comparisonData : []),
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];
            
            Log::info('CRM Dashboard: Response data prepared, rendering page...');

            return Inertia::render('Crm/Dashboard', $responseData);
        } catch (\Exception $e) {
            Log::error('CRM Dashboard Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return Inertia::render('Crm/Dashboard', [
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan saat memuat dashboard. Silakan coba lagi atau hubungi administrator.',
                'stats' => [],
                'memberGrowth' => [],
                'tierDistribution' => [],
                'genderDistribution' => [],
                'ageDistribution' => [],
                'purchasingPowerByAge' => [],
                'spendingTrend' => [],
                'pointActivityTrend' => [],
                'latestMembers' => [],
                'latestPointTransactions' => [],
                'latestActivities' => [],
                'topSpenders' => [],
                'mostActiveMembers' => [],
                'pointStats' => [],
                'engagementMetrics' => [],
                'memberSegmentation' => [],
                'memberLifetimeValue' => [],
                'churnAnalysis' => [],
                'conversionFunnel' => [],
                'regionalBreakdown' => [],
                'comparisonData' => [],
                'filters' => [
                    'start_date' => $startDate ?? '',
                    'end_date' => $endDate ?? '',
                ],
            ]);
        }
    }

    /**
     * Get comprehensive dashboard statistics - Cached for performance
     */
    private function getStats()
    {
        // Cache stats for 5 minutes to reduce database load
        return Cache::remember('crm_dashboard_stats', 300, function () {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Member statistics
        $totalMembers = MemberAppsMember::count();
        $newMembersToday = MemberAppsMember::whereDate('created_at', $today)->count();
        $newMembersThisMonth = MemberAppsMember::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();
        $activeMembers = MemberAppsMember::where('is_active', true)->count();
        $inactiveMembers = MemberAppsMember::where('is_active', false)->count();
        $exclusiveMembers = MemberAppsMember::where('is_exclusive_member', true)->count();
        $emailVerified = MemberAppsMember::whereNotNull('email_verified_at')->count();
        $emailUnverified = MemberAppsMember::whereNull('email_verified_at')->count();

        // Tier breakdown (silver, loyal, elite)
        $tierBreakdown = [
            'silver' => MemberAppsMember::where(function($query) {
                $query->where('member_level', 'silver')
                      ->orWhereNull('member_level')
                      ->orWhere('member_level', '');
            })->count(),
            'loyal' => MemberAppsMember::where('member_level', 'loyal')->count(),
            'elite' => MemberAppsMember::where('member_level', 'elite')->count(),
        ];

        // Point statistics
        $totalPointBalance = MemberAppsMember::sum('just_points');
        $membersWithPoints = MemberAppsMember::where('just_points', '>', 0)->count();
        $membersWithoutPoints = MemberAppsMember::where('just_points', '<=', 0)->count();
        
        // Total point redeemed (all negative point_amount)
        $totalPointRedeemed = abs(MemberAppsPointTransaction::where('point_amount', '<', 0)->sum('point_amount'));
        
        // Total point used for voucher purchase
        $totalPointVoucherPurchase = abs(MemberAppsPointTransaction::where('transaction_type', 'voucher_purchase')
            ->where('point_amount', '<', 0)
            ->sum('point_amount'));

        // Spending statistics
        $totalSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->sum('grand_total');

        $spendingLastYearQuery = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', Carbon::now()->subYear());
        
        $spendingLastYear = $spendingLastYearQuery->sum('grand_total');
        
        // Get last transaction date in last year (the actual last date of data, not today)
        $lastTransactionDate = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', Carbon::now()->subYear())
            ->orderBy('created_at', 'desc')
            ->value('created_at');
        $lastTransactionDateFormatted = $lastTransactionDate ? Carbon::parse($lastTransactionDate)->format('d M Y, H:i') : '-';

        $spendingThisMonth = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$thisMonth, Carbon::now()])
            ->sum('grand_total');

        $spendingToday = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereDate('created_at', $today)
            ->sum('grand_total');

        $spendingThisYear = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('grand_total');

        // Member contribution to revenue (hari ini, bulan ini, tahun ini)
        // Use explicit date format to ensure consistency
        $todayStart = $today->format('Y-m-d 00:00:00');
        $todayEnd = $today->format('Y-m-d 23:59:59');
        $thisMonthStart = $thisMonth->format('Y-m-d 00:00:00');
        $thisMonthEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $thisYearStart = Carbon::now()->startOfYear()->format('Y-m-d 00:00:00');
        $thisYearEnd = Carbon::now()->format('Y-m-d 23:59:59');
        
        // Total revenue (all orders, including non-member)
        $totalRevenueToday = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->sum('grand_total');
        
        $totalRevenueThisMonth = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->where('created_at', '>=', $thisMonthStart)
            ->where('created_at', '<=', $thisMonthEnd)
            ->sum('grand_total');
        
        $totalRevenueThisYear = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->where('created_at', '>=', $thisYearStart)
            ->where('created_at', '<=', $thisYearEnd)
            ->sum('grand_total');
        
        // Member revenue (orders with member_id) - use same date filters
        $memberRevenueToday = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->sum('grand_total');
        
        $memberRevenueThisMonth = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', $thisMonthStart)
            ->where('created_at', '<=', $thisMonthEnd)
            ->sum('grand_total');
        
        $memberRevenueThisYear = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', $thisYearStart)
            ->where('created_at', '<=', $thisYearEnd)
            ->sum('grand_total');
        
        // Calculate contribution percentage
        $memberContributionToday = $totalRevenueToday > 0 ? round(($memberRevenueToday / $totalRevenueToday) * 100, 2) : 0;
        $memberContributionThisMonth = $totalRevenueThisMonth > 0 ? round(($memberRevenueThisMonth / $totalRevenueThisMonth) * 100, 2) : 0;
        $memberContributionThisYear = $totalRevenueThisYear > 0 ? round(($memberRevenueThisYear / $totalRevenueThisYear) * 100, 2) : 0;

        // Growth rate
        $lastMonthMembers = MemberAppsMember::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $growthRate = $lastMonthMembers > 0 ? (($newMembersThisMonth - $lastMonthMembers) / $lastMonthMembers) * 100 : 0;

        // Email verification rate
        $emailVerificationRate = $totalMembers > 0 ? ($emailVerified / $totalMembers) * 100 : 0;

        return [
            'totalMembers' => $totalMembers,
            'newMembersToday' => $newMembersToday,
            'newMembersThisMonth' => $newMembersThisMonth,
            'activeMembers' => $activeMembers,
            'inactiveMembers' => $inactiveMembers,
            'exclusiveMembers' => $exclusiveMembers,
            'emailVerified' => $emailVerified,
            'emailUnverified' => $emailUnverified,
            'emailVerificationRate' => round($emailVerificationRate, 2),
            'tierBreakdown' => $tierBreakdown,
            'totalPointBalance' => $totalPointBalance,
            'totalPointBalanceFormatted' => number_format($totalPointBalance, 0, ',', '.'),
            'membersWithPoints' => $membersWithPoints,
            'membersWithoutPoints' => $membersWithoutPoints,
            'totalPointRedeemed' => $totalPointRedeemed,
            'totalPointRedeemedFormatted' => number_format($totalPointRedeemed, 0, ',', '.'),
            'totalPointVoucherPurchase' => $totalPointVoucherPurchase,
            'totalPointVoucherPurchaseFormatted' => number_format($totalPointVoucherPurchase, 0, ',', '.'),
            'totalSpending' => $totalSpending,
            'totalSpendingFormatted' => 'Rp ' . number_format($totalSpending, 0, ',', '.'),
            'spendingLastYear' => $spendingLastYear,
            'spendingLastYearFormatted' => 'Rp ' . number_format($spendingLastYear, 0, ',', '.'),
            'spendingLastYearLastDate' => $lastTransactionDateFormatted,
            'spendingThisMonth' => $spendingThisMonth,
            'spendingThisMonthFormatted' => 'Rp ' . number_format($spendingThisMonth, 0, ',', '.'),
            'spendingToday' => $spendingToday,
            'spendingTodayFormatted' => 'Rp ' . number_format($spendingToday, 0, ',', '.'),
            'spendingThisYear' => $spendingThisYear,
            'spendingThisYearFormatted' => 'Rp ' . number_format($spendingThisYear, 0, ',', '.'),
            'growthRate' => round($growthRate, 2),
        ];
        });
    }

    /**
     * Get member growth trend (last 12 months) - Optimized with single query
     */
    private function getMemberGrowth()
    {
        // Use single query with date grouping for better performance
        $firstMonth = Carbon::now()->subMonths(11)->startOfMonth();
        
        // Get all new members in one query grouped by month
        $newMembersData = DB::table('member_apps_members')
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $firstMonth)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();
        
        // Get initial total before first month
        $initialTotal = MemberAppsMember::where('created_at', '<', $firstMonth)->count();
        
        $data = [];
        $cumulativeTotal = $initialTotal;
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $newMembers = $newMembersData[$monthKey] ?? 0;
            $cumulativeTotal += $newMembers;

            $data[] = [
                'month' => $date->format('F Y'),
                'monthShort' => $date->format('M Y'),
                'newMembers' => $newMembers,
                'totalMembers' => $cumulativeTotal,
            ];
        }

        return $data;
    }

    /**
     * Get tier distribution - Optimized with single query
     */
    private function getTierDistribution()
    {
        // Use single query with CASE WHEN for better performance
        $tierData = DB::select("
            SELECT 
                CASE 
                    WHEN member_level = 'elite' THEN 'Elite'
                    WHEN member_level = 'loyal' THEN 'Loyal'
                    ELSE 'Silver'
                END as tier,
                COUNT(*) as count
            FROM member_apps_members
            GROUP BY tier
        ");
        
        $tierCounts = [];
        $total = 0;
        foreach ($tierData as $row) {
            $tierCounts[$row->tier] = (int) $row->count;
            $total += $row->count;
        }
        
        $silver = $tierCounts['Silver'] ?? 0;
        $loyal = $tierCounts['Loyal'] ?? 0;
        $elite = $tierCounts['Elite'] ?? 0;
        
        return [
            ['tier' => 'Silver', 'count' => $silver, 'percentage' => $total > 0 ? round(($silver / $total) * 100, 1) : 0, 'color' => '#94a3b8'],
            ['tier' => 'Loyal', 'count' => $loyal, 'percentage' => $total > 0 ? round(($loyal / $total) * 100, 1) : 0, 'color' => '#fbbf24'],
            ['tier' => 'Elite', 'count' => $elite, 'percentage' => $total > 0 ? round(($elite / $total) * 100, 1) : 0, 'color' => '#a78bfa'],
        ];
    }

    /**
     * Get gender distribution
     */
    private function getGenderDistribution()
    {
        // Optimized: Simple COUNT query with GROUP BY - very fast with index on jenis_kelamin
        $distribution = MemberAppsMember::selectRaw('COALESCE(jenis_kelamin, "Tidak Diketahui") as jenis_kelamin, COUNT(*) as count')
            ->groupBy('jenis_kelamin')
            ->get()
            ->map(function ($item) {
                $label = $item->jenis_kelamin === 'L' ? 'Laki-laki' : ($item->jenis_kelamin === 'P' ? 'Perempuan' : 'Tidak Diketahui');
                return [
                    'gender' => $label,
                    'count' => (int) $item->count,
                    'color' => $item->jenis_kelamin === 'L' ? '#3b82f6' : ($item->jenis_kelamin === 'P' ? '#ec4899' : '#6b7280'),
                ];
            });

        $total = $distribution->sum('count');
        return $distribution->map(function ($item) use ($total) {
            $item['percentage'] = $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0;
            return $item;
        })->values();
    }

    /**
     * Get age distribution - Optimized for performance
     */
    private function getAgeDistribution()
    {
        // Optimized: Simple CASE WHEN with COUNT and GROUP BY - fast with index on tanggal_lahir
        $distribution = MemberAppsMember::selectRaw('
            CASE 
                WHEN tanggal_lahir IS NULL THEN "Tidak Diketahui"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) < 13 THEN "Anak-anak"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 13 AND 18 THEN "Remaja"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 19 AND 30 THEN "Dewasa Muda"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 31 AND 45 THEN "Dewasa Produktif"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 46 AND 59 THEN "Dewasa Matang"
                WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= 60 THEN "Usia Tua"
                ELSE "Tidak Diketahui"
            END as age_group,
            COUNT(*) as count
        ')
        ->groupBy('age_group')
        ->get()
        ->map(function ($item) {
            $colors = [
                'Anak-anak' => '#fbbf24',
                'Remaja' => '#3b82f6',
                'Dewasa Muda' => '#10b981',
                'Dewasa Produktif' => '#8b5cf6',
                'Dewasa Matang' => '#ec4899',
                'Usia Tua' => '#6b7280',
                'Tidak Diketahui' => '#9ca3af',
            ];
            
            // Add age range to label
            $ageRanges = [
                'Anak-anak' => '< 13 tahun',
                'Remaja' => '13-18 tahun',
                'Dewasa Muda' => '19-30 tahun',
                'Dewasa Produktif' => '31-45 tahun',
                'Dewasa Matang' => '46-59 tahun',
                'Usia Tua' => '≥ 60 tahun',
                'Tidak Diketahui' => '-',
            ];
            
            $ageRange = $ageRanges[$item->age_group] ?? '-';
            $label = $item->age_group . ($ageRange !== '-' ? ' (' . $ageRange . ')' : '');
            
            return [
                'age_group' => $item->age_group,
                'age_group_label' => $label,
                'age_range' => $ageRange,
                'count' => (int) $item->count,
                'color' => $colors[$item->age_group] ?? '#9ca3af',
            ];
        });

        $total = $distribution->sum('count');
        return $distribution->map(function ($item) use ($total) {
            $item['percentage'] = $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0;
            return $item;
        })->values();
    }

    /**
     * Get purchasing power by age group - Optimized for large dataset
     */
    private function getPurchasingPowerByAge($startDate = null, $endDate = null)
    {
        // Optimized: Use subquery to limit orders first, then join
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        
        // Build date filter for orders subquery
        $orderDateFilter = '';
        if ($startDate && $endDate) {
            $orderDateFilter = "AND o.created_at BETWEEN '{$startDate}' AND '{$endDate}'";
        } else {
            $twoYearsAgo = Carbon::now()->subYears(2)->format('Y-m-d');
            $orderDateFilter = "AND o.created_at >= '{$twoYearsAgo}'";
        }
        
        // Optimized query: First aggregate orders by member_id, then join with members
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
                COALESCE(SUM(order_summary.total_spending), 0) as total_spending,
                COALESCE(AVG(order_summary.avg_order_value), 0) as avg_transaction_value,
                COALESCE(SUM(order_summary.order_count), 0) as total_transactions
            FROM member_apps_members m
            LEFT JOIN (
                SELECT 
                    member_id,
                    SUM(grand_total) as total_spending,
                    AVG(grand_total) as avg_order_value,
                    COUNT(*) as order_count
                FROM {$dbJustusName}.orders
                WHERE status = 'paid'
                    AND member_id != ''
                    AND member_id IS NOT NULL
                    {$orderDateFilter}
                GROUP BY member_id
            ) as order_summary ON m.member_id COLLATE utf8mb4_unicode_ci = order_summary.member_id COLLATE utf8mb4_unicode_ci
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

        // Age ranges mapping
        $ageRanges = [
            'Anak-anak' => '< 13 tahun',
            'Remaja' => '13-18 tahun',
            'Dewasa Muda' => '19-30 tahun',
            'Dewasa Produktif' => '31-45 tahun',
            'Dewasa Matang' => '46-59 tahun',
            'Usia Tua' => '≥ 60 tahun',
            'Tidak Diketahui' => '-',
        ];
        
        return collect(DB::select($sql))
            ->map(function ($item) use ($ageRanges) {
                $ageRange = $ageRanges[$item->age_group] ?? '-';
                $label = $item->age_group . ($ageRange !== '-' ? ' (' . $ageRange . ')' : '');
                
                return [
                    'age_group' => $item->age_group,
                    'age_group_label' => $label,
                    'age_range' => $ageRange,
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
    }

    /**
     * Get spending trend (last 12 months) - Optimized with single query
     */
    private function getSpendingTrend($startDate = null, $endDate = null)
    {
        $firstMonth = Carbon::now()->subMonths(11)->startOfMonth();
        $lastMonth = Carbon::now()->endOfMonth();
        
        $query = DB::connection('db_justus')
            ->table('orders')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(grand_total) as total_spending'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$firstMonth, $lastMonth])
            ->groupBy('month')
            ->orderBy('month');
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $spendingData = $query->get()->keyBy('month');
        
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $spending = $spendingData->get($monthKey);
            
            $totalSpending = $spending ? (float) $spending->total_spending : 0;
            $transactionCount = $spending ? (int) $spending->transaction_count : 0;
            $avgSpending = $transactionCount > 0 ? $totalSpending / $transactionCount : 0;

            $data[] = [
                'month' => $date->format('M Y'),
                'monthShort' => $date->format('M'),
                'totalSpending' => $totalSpending,
                'transactionCount' => $transactionCount,
                'avgSpending' => (float) $avgSpending,
            ];
        }

        return $data;
    }

    /**
     * Get point activity trend (last 12 months) - Optimized with single query
     */
    private function getPointActivityTrend($startDate = null, $endDate = null)
    {
        $firstMonth = Carbon::now()->subMonths(11)->startOfMonth();
        $lastMonth = Carbon::now()->endOfMonth();
        
        $query = DB::table('member_apps_point_transactions')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(CASE WHEN point_amount > 0 THEN point_amount ELSE 0 END) as point_earned'),
                DB::raw('ABS(SUM(CASE WHEN point_amount < 0 THEN point_amount ELSE 0 END)) as point_redeemed')
            )
            ->whereBetween('created_at', [$firstMonth, $lastMonth])
            ->groupBy('month')
            ->orderBy('month');
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $pointData = $query->get()->keyBy('month');
        
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $point = $pointData->get($monthKey);
            
            $pointEarned = $point ? (float) $point->point_earned : 0;
            $pointRedeemed = $point ? (float) $point->point_redeemed : 0;
            $netPoint = $pointEarned - $pointRedeemed;

            $data[] = [
                'month' => $date->format('M Y'),
                'monthShort' => $date->format('M'),
                'pointEarned' => $pointEarned,
                'pointRedeemed' => $pointRedeemed,
                'netPoint' => (float) $netPoint,
            ];
        }

        return $data;
    }

    /**
     * Get latest members (10) - Optimized for large dataset
     */
    private function getLatestMembers()
    {
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        
        // Get latest 10 members with spending in single query
        $members = MemberAppsMember::select([
            'id', 'member_id', 'nama_lengkap', 'email', 'mobile_phone',
            'member_level', 'just_points', 'is_active', 'email_verified_at', 'created_at'
        ])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
        
        if ($members->isEmpty()) {
            return collect([]);
        }
        
        // Get spending for all members in one query
        $memberIds = $members->pluck('member_id')->filter()->toArray();
        $spendingData = DB::connection('db_justus')
            ->table('orders')
            ->select('member_id', DB::raw('SUM(grand_total) as total_spending'))
            ->whereIn('member_id', $memberIds)
            ->where('status', 'paid')
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');
        
        return $members->map(function ($member) use ($spendingData) {
            $spending = $spendingData->get($member->member_id);
            $totalSpending = $spending ? (float) $spending->total_spending : 0;

            return [
                'id' => $member->id,
                'member_id' => $member->member_id,
                'name' => $member->nama_lengkap,
                'email' => $member->email,
                'phone' => $member->mobile_phone,
                'tier' => $member->member_level ?? 'silver',
                'tierFormatted' => ucfirst($member->member_level ?? 'silver'),
                'pointBalance' => $member->just_points ?? 0,
                'pointBalanceFormatted' => number_format($member->just_points ?? 0, 0, ',', '.'),
                'totalSpending' => $totalSpending,
                'totalSpendingFormatted' => 'Rp ' . number_format($totalSpending, 0, ',', '.'),
                'isActive' => $member->is_active,
                'emailVerified' => !is_null($member->email_verified_at),
                'createdAt' => $member->created_at->format('d M Y'),
                'createdAtFull' => $member->created_at->format('d M Y, H:i'),
            ];
        });
    }

    /**
     * Get latest point transactions (10) - With outlet and transaction amount
     */
    private function getLatestPointTransactions()
    {
        $transactions = MemberAppsPointTransaction::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        $memberIds = $transactions->pluck('member_id')->unique()->filter()->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        
        // Get outlet names from orders if available
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        $memberIdStrings = $members->pluck('member_id')->filter()->toArray();
        
        return $transactions->map(function ($pt) use ($members, $dbJustusName, $memberIdStrings) {
            $member = $members->get($pt->member_id);
            $isEarned = $pt->point_amount > 0;
            
            // Try to get outlet name from order
            $outletName = 'Outlet Tidak Diketahui';
            $transactionAmount = $pt->transaction_amount ?? 0;
            
            // Try to find order_id from metadata, reference_id, or transaction_id
            $orderId = null;
            if (isset($pt->metadata) && $pt->metadata) {
                $metadata = json_decode($pt->metadata ?? '{}', true);
                $orderId = $metadata['order_id'] ?? null;
            }
            
            if (!$orderId && isset($pt->reference_id)) {
                $orderId = $pt->reference_id;
            }
            
            if (!$orderId && isset($pt->transaction_id)) {
                $orderId = $pt->transaction_id;
            }
            
            // Get outlet name from order
            if ($orderId && $member->member_id) {
                $orderWithOutlet = DB::connection('db_justus')
                    ->table('orders')
                    ->leftJoin('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                    ->where('orders.id', $orderId)
                    ->where('orders.member_id', $member->member_id)
                    ->select(['orders.grand_total', 'o.nama_outlet'])
                    ->first();
                
                if ($orderWithOutlet) {
                    if (isset($orderWithOutlet->nama_outlet) && $orderWithOutlet->nama_outlet) {
                        $outletName = $orderWithOutlet->nama_outlet;
                    }
                    if (isset($orderWithOutlet->grand_total) && $orderWithOutlet->grand_total && !$transactionAmount) {
                        $transactionAmount = (float) $orderWithOutlet->grand_total;
                    }
                }
            }
            
            // Determine transaction type and description
            $transactionType = isset($pt->transaction_type) ? $pt->transaction_type : null;
            $description = '';
            
            if ($isEarned) {
                $description = 'Top Up Point dari Transaksi';
            } else {
                // For redeemed transactions, check transaction_type
                if ($transactionType === 'voucher_purchase') {
                    $description = 'Beli Voucher';
                } elseif ($transactionType === 'reward_redemption' || $transactionType === 'redeem') {
                    $description = 'Redeem Reward';
                } else {
                    $description = 'Redeem Point';
                }
            }
            
            return [
                'id' => $pt->id,
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'memberId' => $member->member_id ?? '-',
                'type' => $isEarned ? 'earned' : 'redeemed',
                'typeText' => $isEarned ? 'EARNED' : 'REDEEMED',
                'transactionType' => $transactionType,
                'description' => $description,
                'pointAmount' => abs($pt->point_amount),
                'pointAmountFormatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                'transactionValue' => $pt->transaction_amount ?? 0,
                'transactionValueFormatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                'transactionAmount' => $transactionAmount,
                'transactionAmountFormatted' => $transactionAmount > 0 ? 'Rp ' . number_format($transactionAmount, 0, ',', '.') : '-',
                'outletName' => $outletName,
                'createdAt' => $pt->created_at->format('d M Y, H:i'),
            ];
        });
    }

    /**
     * Get latest activities (combining all activity types) - Optimized for performance
     */
    private function getLatestActivities()
    {
        // Optimized: Use UNION query to get both registrations and transactions in one query, then sort
        // But for simplicity and to avoid complex UNION, we'll use a simpler approach with limits
        
        $activities = collect();

        // Recent registrations - limit to last 7 days and only 3 records
        try {
            $recentRegistrations = MemberAppsMember::where('created_at', '>=', Carbon::now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->select(['id', 'nama_lengkap', 'member_id', 'created_at'])
                ->get()
                ->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'type' => 'registration',
                        'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                        'memberId' => $member->member_id ?? '-',
                        'description' => 'Member baru mendaftar',
                        'icon' => 'fa-user-plus',
                        'color' => 'text-emerald-500',
                        'bgColor' => 'bg-emerald-50',
                        'createdAt' => $member->created_at,
                    ];
                });

            $activities = $activities->concat($recentRegistrations);
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Failed to load recent registrations: ' . $e->getMessage());
        }

        // Recent point transactions - limit to 7 records
        try {
            $recentPointTransactions = MemberAppsPointTransaction::orderBy('created_at', 'desc')
                ->limit(7)
                ->select(['id', 'member_id', 'point_amount', 'transaction_type', 'created_at'])
                ->get();
            
            if ($recentPointTransactions->isNotEmpty()) {
                $memberIds = $recentPointTransactions->pluck('member_id')->unique()->filter()->toArray();
                $members = MemberAppsMember::whereIn('id', $memberIds)
                    ->select(['id', 'nama_lengkap', 'member_id'])
                    ->get()
                    ->keyBy('id');
                
                $recentPointTransactions = $recentPointTransactions->map(function ($pt) use ($members) {
                    $member = $members->get($pt->member_id);
                    $isEarned = $pt->point_amount > 0;
                    
                    // Determine description based on transaction_type
                    $transactionType = $pt->transaction_type ?? null;
                    $description = '';
                    if ($isEarned) {
                        $description = 'Memperoleh ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point';
                    } else {
                        if ($transactionType === 'voucher_purchase') {
                            $description = 'Beli voucher dengan ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point';
                        } elseif ($transactionType === 'reward_redemption' || $transactionType === 'redeem') {
                            $description = 'Redeem reward dengan ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point';
                        } else {
                            $description = 'Menukar ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point';
                        }
                    }
                    
                    return [
                        'id' => $pt->id,
                        'type' => $isEarned ? 'point_earned' : 'point_redeemed',
                        'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                        'memberId' => $member->member_id ?? '-',
                        'description' => $description,
                        'icon' => $isEarned ? 'fa-plus-circle' : ($transactionType === 'voucher_purchase' ? 'fa-ticket' : ($transactionType === 'reward_redemption' || $transactionType === 'redeem' ? 'fa-gift' : 'fa-minus-circle')),
                        'color' => $isEarned ? 'text-blue-500' : 'text-orange-500',
                        'bgColor' => $isEarned ? 'bg-blue-50' : 'bg-orange-50',
                        'pointAmount' => abs($pt->point_amount),
                        'createdAt' => $pt->created_at,
                    ];
                });

                $activities = $activities->concat($recentPointTransactions);
            }
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Failed to load recent point transactions: ' . $e->getMessage());
        }

        // Sort and take latest 10
        return $activities->sortByDesc(function ($activity) {
            return $activity['createdAt'] instanceof \Carbon\Carbon 
                ? $activity['createdAt']->timestamp 
                : strtotime($activity['createdAt']);
        })
            ->take(10)
            ->map(function ($activity) {
                $createdAt = $activity['createdAt'] instanceof \Carbon\Carbon 
                    ? $activity['createdAt'] 
                    : \Carbon\Carbon::parse($activity['createdAt']);
                    
                return [
                    'id' => $activity['id'],
                    'type' => $activity['type'],
                    'memberName' => $activity['memberName'],
                    'memberId' => $activity['memberId'],
                    'description' => $activity['description'],
                    'icon' => $activity['icon'],
                    'color' => $activity['color'],
                    'bgColor' => $activity['bgColor'],
                    'pointAmount' => $activity['pointAmount'] ?? null,
                    'createdAt' => $createdAt->format('d M Y, H:i'),
                    'createdAtFull' => $createdAt->format('Y-m-d H:i:s'),
                ];
            })
            ->values();
    }

    /**
     * Get top spenders (10) - Optimized: Remove subquery, get last spending separately
     */
    private function getTopSpenders()
    {
        // First, get top spenders without subquery (much faster)
        $topSpenders = DB::connection('db_justus')
            ->table('orders')
            ->select(
                'member_id',
                DB::raw('SUM(grand_total) as total_spending'),
                DB::raw('COUNT(*) as order_count')
            )
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->groupBy('member_id')
            ->orderBy('total_spending', 'desc')
            ->limit(10)
            ->get();

        if ($topSpenders->isEmpty()) {
            return collect([]);
        }

        // Get member info
        $memberIds = $topSpenders->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('member_id', $memberIds)->get()->keyBy('member_id');

        // Get last spending for each member in one query (batch)
        $lastOrders = DB::connection('db_justus')
            ->table('orders')
            ->select('member_id', 'grand_total', 'created_at')
            ->whereIn('member_id', $memberIds)
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('member_id')
            ->map(function ($orders) {
                return $orders->first();
            });

        return $topSpenders->map(function ($spender) use ($members, $lastOrders) {
            $member = $members->get($spender->member_id);
            $lastOrder = $lastOrders->get($spender->member_id);
            
            $lastSpending = $lastOrder ? (float) $lastOrder->grand_total : 0;
            $lastSpendingDate = $lastOrder ? Carbon::parse($lastOrder->created_at)->format('d M Y') : '-';

            return [
                'memberId' => $spender->member_id,
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'totalSpending' => (float) $spender->total_spending,
                'totalSpendingFormatted' => 'Rp ' . number_format($spender->total_spending, 0, ',', '.'),
                'orderCount' => $spender->order_count,
                'avgOrderValue' => $spender->order_count > 0 ? (float) ($spender->total_spending / $spender->order_count) : 0,
                'avgOrderValueFormatted' => $spender->order_count > 0 ? 'Rp ' . number_format($spender->total_spending / $spender->order_count, 0, ',', '.') : 'Rp 0',
                'lastSpending' => $lastSpending,
                'lastSpendingFormatted' => $lastSpending > 0 ? 'Rp ' . number_format($lastSpending, 0, ',', '.') : 'Rp 0',
                'lastSpendingDate' => $lastSpendingDate,
            ];
        });
    }

    /**
     * Get most active members (10)
     */
    private function getMostActiveMembers()
    {
        $activeMembers = MemberAppsPointTransaction::select('member_id', DB::raw('COUNT(*) as transaction_count'), DB::raw('MAX(created_at) as last_transaction'))
            ->groupBy('member_id')
            ->orderBy('transaction_count', 'desc')
            ->limit(10)
            ->get();

        $memberIds = $activeMembers->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        
        // Get order counts for all members in one query
        $memberIdStrings = $members->pluck('member_id')->filter()->toArray();
        $orderCounts = DB::connection('db_justus')
            ->table('orders')
            ->select('member_id', DB::raw('COUNT(*) as order_count'))
            ->whereIn('member_id', $memberIdStrings)
            ->where('status', 'paid')
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        return $activeMembers->map(function ($active) use ($members, $orderCounts) {
            $member = $members->get($active->member_id);
            $orderCountData = $orderCounts->get($member->member_id ?? '');
            $orderCount = $orderCountData ? (int) $orderCountData->order_count : 0;

            return [
                'memberId' => $member->member_id ?? '-',
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'transactionCount' => $active->transaction_count,
                'orderCount' => $orderCount,
                'pointBalance' => $member->just_points ?? 0,
                'pointBalanceFormatted' => number_format($member->just_points ?? 0, 0, ',', '.'),
                'lastTransactionDate' => Carbon::parse($active->last_transaction)->format('d M Y, H:i'),
            ];
        });
    }

    /**
     * Get point statistics
     */
    private function getPointStats($startDate = null, $endDate = null)
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $baseQuery = MemberAppsPointTransaction::query();
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $totalTransactions = (clone $baseQuery)->count();
        $transactionsToday = MemberAppsPointTransaction::whereDate('created_at', $today)->count();
        $transactionsThisMonth = MemberAppsPointTransaction::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();
        
        $totalPointEarned = (clone $baseQuery)->where('point_amount', '>', 0)->sum('point_amount');
        $totalPointRedeemed = abs((clone $baseQuery)->where('point_amount', '<', 0)->sum('point_amount'));
        $totalTransactionValue = (clone $baseQuery)->sum('transaction_amount');

        $lastMonthTransactions = MemberAppsPointTransaction::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
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
     * Get engagement metrics
     */
    private function getEngagementMetrics()
    {
        $last30Days = Carbon::now()->subDays(30);
        $last7Days = Carbon::now()->subDays(7);
        $last90Days = Carbon::now()->subDays(90);

        $activeLast30Days = MemberAppsMember::where('last_login_at', '>=', $last30Days)->count();
        $activeLast7Days = MemberAppsMember::where('last_login_at', '>=', $last7Days)->count();
        $dormantMembers = MemberAppsMember::where(function($query) use ($last90Days) {
            $query->whereNull('last_login_at')
                  ->orWhere('last_login_at', '<', $last90Days);
        })->count();

        // Members with points
        $membersWithPoints = MemberAppsMember::where('just_points', '>', 0)->count();
        
        // Active point users (transactions in last 30 days)
        $activePointUsers = MemberAppsPointTransaction::where('created_at', '>=', $last30Days)
            ->distinct('member_id')
            ->count('member_id');

        return [
            'activeLast30Days' => $activeLast30Days,
            'activeLast7Days' => $activeLast7Days,
            'dormantMembers' => $dormantMembers,
            'membersWithPoints' => $membersWithPoints,
            'activePointUsers' => $activePointUsers,
        ];
    }

    /**
     * Get member segmentation (Priority 3) - Optimized for large dataset
     */
    private function getMemberSegmentation()
    {
        $last30Days = Carbon::now()->subDays(30);
        $last90Days = Carbon::now()->subDays(90);
        
        // Use single query with CASE WHEN for better performance
        $segmentation = DB::select("
            SELECT 
                COUNT(CASE WHEN is_active = 1 AND (member_level = 'elite' OR member_level = 'loyal') AND just_points > 1000 THEN 1 END) as vip,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as new,
                COUNT(CASE WHEN is_active = 1 AND (last_login_at IS NULL OR last_login_at < ?) THEN 1 END) as dormant,
                COUNT(CASE WHEN is_active = 1 AND just_points <= 100 AND (last_login_at IS NULL OR last_login_at < ?) THEN 1 END) as at_risk
            FROM member_apps_members
        ", [$last30Days, $last90Days, $last30Days]);
        
        $result = $segmentation[0];
        
        // Active Members: Use subquery for better performance
        $activeMembers = DB::table('member_apps_members as m')
            ->whereExists(function($query) use ($last30Days) {
                $query->select(DB::raw(1))
                    ->from('member_apps_point_transactions')
                    ->whereColumn('member_apps_point_transactions.member_id', 'm.id')
                    ->where('member_apps_point_transactions.created_at', '>=', $last30Days);
            })
            ->where('m.is_active', true)
            ->count();
        
        return [
            'vip' => (int) $result->vip,
            'active' => $activeMembers,
            'dormant' => (int) $result->dormant,
            'new' => (int) $result->new,
            'atRisk' => (int) $result->at_risk,
        ];
    }
    
    /**
     * Get member lifetime value (Priority 3) - Optimized with subquery
     */
    private function getMemberLifetimeValue()
    {
        // Optimized: Use subquery to aggregate orders first, then join
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        
        $ltvData = DB::select("
            SELECT 
                COALESCE(m.member_level, 'silver') as tier,
                COUNT(DISTINCT m.id) as member_count,
                COALESCE(SUM(order_summary.total_spending), 0) as total_spending
            FROM member_apps_members m
            LEFT JOIN (
                SELECT 
                    member_id,
                    SUM(grand_total) as total_spending
                FROM {$dbJustusName}.orders
                WHERE status = 'paid'
                    AND member_id != ''
                    AND member_id IS NOT NULL
                GROUP BY member_id
            ) as order_summary ON m.member_id COLLATE utf8mb4_unicode_ci = order_summary.member_id COLLATE utf8mb4_unicode_ci
            WHERE m.is_active = 1
            GROUP BY tier
        ");
        
        $totalLTV = 0;
        $memberCount = 0;
        $ltvByTier = [
            'silver' => ['total' => 0, 'count' => 0],
            'gold' => ['total' => 0, 'count' => 0],
            'platinum' => ['total' => 0, 'count' => 0],
        ];
        
        foreach ($ltvData as $row) {
            $tier = $row->tier;
            $spending = (float) $row->total_spending;
            $count = (int) $row->member_count;
            
            if ($spending > 0 && isset($ltvByTier[$tier])) {
                $ltvByTier[$tier]['total'] = $spending;
                $ltvByTier[$tier]['count'] = $count;
                $totalLTV += $spending;
                $memberCount += $count;
            }
        }
        
        $avgLTV = $memberCount > 0 ? $totalLTV / $memberCount : 0;
        
        return [
            'average' => $avgLTV,
            'averageFormatted' => 'Rp ' . number_format($avgLTV, 0, ',', '.'),
            'total' => $totalLTV,
            'totalFormatted' => 'Rp ' . number_format($totalLTV, 0, ',', '.'),
            'byTier' => collect($ltvByTier)->map(function($data) {
                $avg = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
                return [
                    'total' => $data['total'],
                    'totalFormatted' => 'Rp ' . number_format($data['total'], 0, ',', '.'),
                    'count' => $data['count'],
                    'average' => $avg,
                    'averageFormatted' => 'Rp ' . number_format($avg, 0, ',', '.'),
                ];
            }),
        ];
    }
    
    /**
     * Get churn analysis (Priority 3)
     */
    private function getChurnAnalysis()
    {
        $last30Days = Carbon::now()->subDays(30);
        $last60Days = Carbon::now()->subDays(60);
        $last90Days = Carbon::now()->subDays(90);
        
        // Churned: No activity in last 90 days but was active before
        $churnedMembers = MemberAppsMember::where('is_active', true)
            ->where(function($query) use ($last90Days) {
                $query->whereNull('last_login_at')
                      ->orWhere('last_login_at', '<', $last90Days);
            })
            ->count();
        
        // At Risk: No activity in last 30-60 days
        $atRiskChurn = MemberAppsMember::where('is_active', true)
            ->where('last_login_at', '>=', $last60Days)
            ->where('last_login_at', '<', $last30Days)
            ->count();
        
        // Retention Rate: Active in last 30 days / Total active
        $activeLast30Days = MemberAppsMember::where('last_login_at', '>=', $last30Days)
            ->where('is_active', true)
            ->count();
        $totalActive = MemberAppsMember::where('is_active', true)->count();
        $retentionRate = $totalActive > 0 ? ($activeLast30Days / $totalActive) * 100 : 0;
        
        return [
            'churned' => $churnedMembers,
            'atRiskChurn' => $atRiskChurn,
            'retentionRate' => round($retentionRate, 2),
            'activeLast30Days' => $activeLast30Days,
            'totalActive' => $totalActive,
        ];
    }
    
    /**
     * Get conversion funnel (Priority 3)
     */
    private function getConversionFunnel()
    {
        $last30Days = Carbon::now()->subDays(30);
        
        // Registered
        $registered = MemberAppsMember::where('created_at', '>=', $last30Days)->count();
        
        // Email Verified
        $emailVerified = MemberAppsMember::where('created_at', '>=', $last30Days)
            ->whereNotNull('email_verified_at')
            ->count();
        
        // First Login
        $firstLogin = MemberAppsMember::where('created_at', '>=', $last30Days)
            ->whereNotNull('last_login_at')
            ->count();
        
        // First Transaction
        $firstTransaction = DB::connection('db_justus')
            ->table('orders')
            ->where('created_at', '>=', $last30Days)
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->distinct('member_id')
            ->count('member_id');
        
        // Repeat Customers (2+ transactions)
        $repeatCustomers = DB::connection('db_justus')
            ->table('orders')
            ->where('created_at', '>=', $last30Days)
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('status', 'paid')
            ->groupBy('member_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();
        
        return [
            'registered' => $registered,
            'emailVerified' => $emailVerified,
            'firstLogin' => $firstLogin,
            'firstTransaction' => $firstTransaction,
            'repeatCustomers' => $repeatCustomers,
            'emailVerificationRate' => $registered > 0 ? round(($emailVerified / $registered) * 100, 2) : 0,
            'loginRate' => $registered > 0 ? round(($firstLogin / $registered) * 100, 2) : 0,
            'transactionRate' => $registered > 0 ? round(($firstTransaction / $registered) * 100, 2) : 0,
            'repeatRate' => $firstTransaction > 0 ? round(($repeatCustomers / $firstTransaction) * 100, 2) : 0,
        ];
    }
    
    /**
     * Get regional breakdown (Priority 3) - Optimized
     */
    private function getRegionalBreakdown()
    {
        // Get orders grouped by outlet/region - Optimized: removed unnecessary member query
        $ordersByOutlet = DB::connection('db_justus')
            ->table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->select(
                'outlet.nama_outlet',
                'outlet.region',
                DB::raw('COUNT(DISTINCT o.member_id) as total_members'),
                DB::raw('COUNT(o.id) as total_orders'),
                DB::raw('SUM(o.grand_total) as total_spending')
            )
            ->where('o.status', 'paid')
            ->whereNotNull('o.member_id')
            ->where('o.member_id', '!=', '')
            ->groupBy('outlet.nama_outlet', 'outlet.region')
            ->get();
        
        return $ordersByOutlet->map(function($item) {
            return [
                'outlet_name' => $item->nama_outlet ?? 'Unknown',
                'region' => $item->region ?? 'Unknown',
                'total_members' => (int) $item->total_members,
                'total_orders' => (int) $item->total_orders,
                'total_spending' => (float) $item->total_spending,
                'total_spending_formatted' => 'Rp ' . number_format($item->total_spending, 0, ',', '.'),
                'avg_order_value' => $item->total_orders > 0 ? (float) ($item->total_spending / $item->total_orders) : 0,
                'avg_order_value_formatted' => $item->total_orders > 0 ? 'Rp ' . number_format($item->total_spending / $item->total_orders, 0, ',', '.') : 'Rp 0',
            ];
        })->values();
    }
    
    /**
     * Get comparison data (Priority 3) - Month over Month, Year over Year
     */
    private function getComparisonData($startDate = null, $endDate = null)
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        $currentYear = Carbon::now()->startOfYear();
        $lastYear = Carbon::now()->subYear()->startOfYear();
        $lastYearEnd = Carbon::now()->subYear()->endOfYear();
        
        // Current Month
        $currentMonthMembers = MemberAppsMember::whereBetween('created_at', [$currentMonth, Carbon::now()])->count();
        $currentMonthSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$currentMonth, Carbon::now()])
            ->sum('grand_total');
        
        // Last Month
        $lastMonthMembers = MemberAppsMember::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $lastMonthSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->sum('grand_total');
        
        // Current Year
        $currentYearMembers = MemberAppsMember::whereBetween('created_at', [$currentYear, Carbon::now()])->count();
        $currentYearSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$currentYear, Carbon::now()])
            ->sum('grand_total');
        
        // Last Year
        $lastYearMembers = MemberAppsMember::whereBetween('created_at', [$lastYear, $lastYearEnd])->count();
        $lastYearSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$lastYear, $lastYearEnd])
            ->sum('grand_total');
        
        // Calculate growth rates
        $memberMoMGrowth = $lastMonthMembers > 0 ? (($currentMonthMembers - $lastMonthMembers) / $lastMonthMembers) * 100 : 0;
        $spendingMoMGrowth = $lastMonthSpending > 0 ? (($currentMonthSpending - $lastMonthSpending) / $lastMonthSpending) * 100 : 0;
        $memberYoYGrowth = $lastYearMembers > 0 ? (($currentYearMembers - $lastYearMembers) / $lastYearMembers) * 100 : 0;
        $spendingYoYGrowth = $lastYearSpending > 0 ? (($currentYearSpending - $lastYearSpending) / $lastYearSpending) * 100 : 0;
        
        return [
            'monthOverMonth' => [
                'members' => [
                    'current' => $currentMonthMembers,
                    'previous' => $lastMonthMembers,
                    'growth' => round($memberMoMGrowth, 2),
                ],
                'spending' => [
                    'current' => $currentMonthSpending,
                    'currentFormatted' => 'Rp ' . number_format($currentMonthSpending, 0, ',', '.'),
                    'previous' => $lastMonthSpending,
                    'previousFormatted' => 'Rp ' . number_format($lastMonthSpending, 0, ',', '.'),
                    'growth' => round($spendingMoMGrowth, 2),
                ],
            ],
            'yearOverYear' => [
                'members' => [
                    'current' => $currentYearMembers,
                    'previous' => $lastYearMembers,
                    'growth' => round($memberYoYGrowth, 2),
                ],
                'spending' => [
                    'current' => $currentYearSpending,
                    'currentFormatted' => 'Rp ' . number_format($currentYearSpending, 0, ',', '.'),
                    'previous' => $lastYearSpending,
                    'previousFormatted' => 'Rp ' . number_format($lastYearSpending, 0, ',', '.'),
                    'growth' => round($spendingYoYGrowth, 2),
                ],
            ],
        ];
    }
    
    /**
     * Export dashboard data (Priority 3)
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // This would typically use Laravel Excel or similar
        // For now, return JSON response
        return response()->json([
            'message' => 'Export functionality will be implemented with Laravel Excel',
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Get API data for charts
     */
    public function getChartData(Request $request)
    {
        $type = $request->get('type', 'growth');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        switch ($type) {
            case 'growth':
                return response()->json($this->getMemberGrowth());
            case 'tier':
                return response()->json($this->getTierDistribution());
            case 'gender':
                return response()->json($this->getGenderDistribution());
            case 'age':
                return response()->json($this->getAgeDistribution());
            case 'purchasingPower':
                return response()->json($this->getPurchasingPowerByAge($startDate, $endDate));
            case 'spendingTrend':
                return response()->json($this->getSpendingTrend($startDate, $endDate));
            case 'pointActivityTrend':
                return response()->json($this->getPointActivityTrend($startDate, $endDate));
            default:
                return response()->json([]);
        }
    }
    
    /**
     * Get redeem details (for modal)
     */
    public function getRedeemDetails(Request $request)
    {
        $cabangId = $request->get('cabang_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // This method can be implemented later if needed
        return response()->json([
            'message' => 'Redeem details functionality will be implemented',
            'cabang_id' => $cabangId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
