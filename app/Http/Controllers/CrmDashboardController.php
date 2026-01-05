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
        $stats = $this->getStats();
        $memberGrowth = $this->getMemberGrowth();
        $tierDistribution = $this->getTierDistribution();
        $spendingTrend = $this->getSpendingTrend($startDate, $endDate);
        $pointActivityTrend = $this->getPointActivityTrend($startDate, $endDate);
        $latestMembers = $this->getLatestMembers();
        $latestPointTransactions = $this->getLatestPointTransactions();
            
            // Load with timeout protection
            $topSpenders = [];
            $topSpendersDateRange = null;
            try {
                $topSpendersResult = $this->getTopSpenders();
                if (is_array($topSpendersResult) && isset($topSpendersResult['data'])) {
                    $topSpenders = $topSpendersResult['data'];
                    $topSpendersDateRange = $topSpendersResult['dateRange'] ?? null;
                } elseif (is_object($topSpendersResult) && isset($topSpendersResult->data)) {
                    $topSpenders = $topSpendersResult->data;
                    $topSpendersDateRange = $topSpendersResult->dateRange ?? null;
                } else {
                    $topSpenders = $topSpendersResult;
                }
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load top spenders: ' . $e->getMessage());
            }
            
            $mostActiveMembers = [];
            $mostActiveMembersDateRange = null;
            try {
                $mostActiveMembersResult = $this->getMostActiveMembers();
                if (is_array($mostActiveMembersResult) && isset($mostActiveMembersResult['data'])) {
                    $mostActiveMembers = $mostActiveMembersResult['data'];
                    $mostActiveMembersDateRange = $mostActiveMembersResult['dateRange'] ?? null;
                } elseif (is_object($mostActiveMembersResult) && isset($mostActiveMembersResult->data)) {
                    $mostActiveMembers = $mostActiveMembersResult->data;
                    $mostActiveMembersDateRange = $mostActiveMembersResult->dateRange ?? null;
                } else {
                    $mostActiveMembers = $mostActiveMembersResult;
                }
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load most active members: ' . $e->getMessage());
            }
            
            // Load top 10 points
            $top10Points = [];
            try {
                $top10Points = $this->getTop10Points();
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load top 10 points: ' . $e->getMessage());
            }
            
            // Load top 10 voucher owners
            $top10VoucherOwners = [];
            try {
                $top10VoucherOwners = $this->getTop10VoucherOwners();
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load top 10 voucher owners: ' . $e->getMessage());
            }
            
            // Load top 10 point redemptions
            $top10PointRedemptions = [];
            try {
                $top10PointRedemptions = $this->getTop10PointRedemptions();
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load top 10 point redemptions: ' . $e->getMessage());
            }
            
            $pointStats = [];
            try {
        $pointStats = $this->getPointStats($startDate, $endDate);
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load point stats: ' . $e->getMessage());
            }
            
            $memberFavouritePicks = [];
            try {
                $memberFavouritePicks = $this->getMemberFavouritePicks();
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load member favourite picks: ' . $e->getMessage());
            }
            
            $activeVouchers = [];
            try {
                $activeVouchers = $this->getActiveVouchers();
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load active vouchers: ' . $e->getMessage());
            }
            
            $activeChallenges = [];
            try {
                $activeChallenges = Cache::remember('crm_active_challenges', 300, function () {
                    return $this->getActiveChallenges();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load active challenges: ' . $e->getMessage());
            }
            
            $activeRewards = [];
            try {
                $activeRewards = Cache::remember('crm_active_rewards', 300, function () {
                    return $this->getActiveRewards();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load active rewards: ' . $e->getMessage());
            }
            
            // Load lightweight queries with caching
            $genderDistribution = [];
            try {
                $genderDistribution = Cache::remember('crm_gender_distribution', 300, function () {
                    return $this->getGenderDistribution();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load gender distribution: ' . $e->getMessage());
            }
            
            $occupationDistribution = [];
            try {
                $occupationDistribution = Cache::remember('crm_occupation_distribution', 300, function () {
                    return $this->getOccupationDistribution();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load occupation distribution: ' . $e->getMessage());
            }
            
            $ageDistribution = [];
            try {
                $ageDistribution = Cache::remember('crm_age_distribution', 300, function () {
                    return $this->getAgeDistribution();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load age distribution: ' . $e->getMessage());
            }
            
            $latestActivities = [];
            try {
                $latestActivities = $this->getLatestActivities();
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load latest activities: ' . $e->getMessage());
            }
            
            // Get purchasing power by age with caching (10 minutes) - Year to date
            try {
                $yearStart = Carbon::now()->startOfYear()->format('Y-m-d 00:00:00');
                $yearEnd = Carbon::now()->format('Y-m-d 23:59:59');
                $purchasingPowerByAge = Cache::remember('crm_purchasing_power_by_age_ytd', 600, function () use ($yearStart, $yearEnd) {
                    $data = $this->getPurchasingPowerByAge($yearStart, $yearEnd);
                    // Convert collection to array
                    return is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array) $data;
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Error loading purchasing power by age: ' . $e->getMessage());
                $purchasingPowerByAge = [];
            }
            
            // Get purchasing power by age for current month (daily breakdown) - for line chart
            try {
                $monthStart = Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00');
                $monthEnd = Carbon::now()->format('Y-m-d 23:59:59');
                $purchasingPowerByAgeThisMonth = Cache::remember('crm_purchasing_power_by_age_month_' . Carbon::now()->format('Y-m'), 300, function () use ($monthStart, $monthEnd) {
                    return $this->getPurchasingPowerByAgeThisMonth($monthStart, $monthEnd);
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Error loading purchasing power by age this month: ' . $e->getMessage());
                $purchasingPowerByAgeThisMonth = [];
            }
            
            // Load member segmentation
            $memberSegmentation = [];
            try {
                $memberSegmentation = Cache::remember('crm_member_segmentation', 300, function () {
                    return $this->getMemberSegmentation();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load member segmentation: ' . $e->getMessage());
            }
            
            // Skip member lifetime value - too heavy for large dataset
            $memberLifetimeValue = (object)[
                'average' => 0,
                'averageFormatted' => 'Rp 0',
                'total' => 0,
                'totalFormatted' => 'Rp 0',
                'byTier' => (object)[],
            ];
            
            // Load churn analysis
            $churnAnalysis = [];
            try {
                $churnAnalysis = Cache::remember('crm_churn_analysis', 300, function () {
                    return $this->getChurnAnalysis();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load churn analysis: ' . $e->getMessage());
            }
            
            // Skip conversion funnel - too heavy
            $conversionFunnel = (object)[
                'registered' => 0,
                'emailVerified' => 0,
                'emailVerificationRate' => 0,
                'firstLogin' => 0,
                'loginRate' => 0,
                'firstTransaction' => 0,
                'transactionRate' => 0,
                'repeatCustomers' => 0,
                'repeatRate' => 0,
            ];
            
            // Load comparison data (MoM & YoY)
            $comparisonData = [];
            try {
                $comparisonData = Cache::remember('crm_comparison_data', 300, function () {
                    return $this->getComparisonData();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load comparison data: ' . $e->getMessage());
            }
            
            // Load regional breakdown
            $regionalBreakdown = [];
            try {
                $regionalBreakdown = Cache::remember('crm_regional_breakdown', 300, function () {
                    return $this->getRegionalBreakdown();
                });
            } catch (\Exception $e) {
                Log::error('CRM Dashboard: Failed to load regional breakdown: ' . $e->getMessage());
            }
            
            // Skip other heavy queries for now - load only critical data
            // These can be loaded via AJAX later if needed
            $engagementMetrics = [];
            
            // Convert all collections to arrays to reduce serialization overhead
            $responseData = [
                'stats' => is_array($stats) ? $stats : (is_object($stats) ? (array) $stats : []),
                'memberGrowth' => is_array($memberGrowth) ? $memberGrowth : $memberGrowth->toArray(),
                'tierDistribution' => is_array($tierDistribution) ? $tierDistribution : $tierDistribution->toArray(),
                'genderDistribution' => is_array($genderDistribution) ? $genderDistribution : (is_object($genderDistribution) ? $genderDistribution->toArray() : []),
                'occupationDistribution' => is_array($occupationDistribution) ? $occupationDistribution : (is_object($occupationDistribution) ? $occupationDistribution->toArray() : []),
                'ageDistribution' => is_array($ageDistribution) ? $ageDistribution : (is_object($ageDistribution) ? $ageDistribution->toArray() : []),
                'purchasingPowerByAge' => is_array($purchasingPowerByAge) ? $purchasingPowerByAge : (is_object($purchasingPowerByAge) ? $purchasingPowerByAge->toArray() : []),
                'purchasingPowerByAgeThisMonth' => is_array($purchasingPowerByAgeThisMonth) ? $purchasingPowerByAgeThisMonth : (is_object($purchasingPowerByAgeThisMonth) ? $purchasingPowerByAgeThisMonth->toArray() : []),
                'spendingTrend' => is_array($spendingTrend) ? $spendingTrend : $spendingTrend->toArray(),
                'pointActivityTrend' => is_array($pointActivityTrend) ? $pointActivityTrend : $pointActivityTrend->toArray(),
                'latestMembers' => is_array($latestMembers) ? $latestMembers : $latestMembers->toArray(),
                'latestPointTransactions' => is_array($latestPointTransactions) ? $latestPointTransactions : $latestPointTransactions->toArray(),
                'latestActivities' => is_array($latestActivities) ? $latestActivities : (is_object($latestActivities) ? $latestActivities->toArray() : []),
                'topSpenders' => is_array($topSpenders) ? $topSpenders : (is_object($topSpenders) ? $topSpenders->toArray() : []),
                'topSpendersDateRange' => $topSpendersDateRange,
                'mostActiveMembers' => is_array($mostActiveMembers) ? $mostActiveMembers : (is_object($mostActiveMembers) ? $mostActiveMembers->toArray() : []),
                'mostActiveMembersDateRange' => $mostActiveMembersDateRange,
                'top10Points' => is_array($top10Points) ? $top10Points : (is_object($top10Points) && method_exists($top10Points, 'toArray') ? $top10Points->toArray() : []),
                'top10VoucherOwners' => is_array($top10VoucherOwners) ? $top10VoucherOwners : (is_object($top10VoucherOwners) && method_exists($top10VoucherOwners, 'toArray') ? $top10VoucherOwners->toArray() : []),
                'top10PointRedemptions' => is_array($top10PointRedemptions) ? $top10PointRedemptions : (is_object($top10PointRedemptions) && method_exists($top10PointRedemptions, 'toArray') ? $top10PointRedemptions->toArray() : []),
                'memberFavouritePicks' => is_array($memberFavouritePicks) ? (object)$memberFavouritePicks : (is_object($memberFavouritePicks) ? $memberFavouritePicks : (object)['food' => [], 'beverages' => []]),
                'activeVouchers' => is_array($activeVouchers) ? $activeVouchers : (is_object($activeVouchers) ? $activeVouchers->toArray() : []),
                'activeChallenges' => is_array($activeChallenges) ? $activeChallenges : (is_object($activeChallenges) ? $activeChallenges->toArray() : []),
                'activeRewards' => is_array($activeRewards) ? $activeRewards : (is_object($activeRewards) ? $activeRewards->toArray() : []),
                'pointStats' => is_array($pointStats) ? $pointStats : (is_object($pointStats) ? (array) $pointStats : []),
                'engagementMetrics' => is_array($engagementMetrics) ? $engagementMetrics : (is_object($engagementMetrics) ? (array) $engagementMetrics : []),
                'memberSegmentation' => is_array($memberSegmentation) ? (object)$memberSegmentation : (is_object($memberSegmentation) ? $memberSegmentation : (object)['vip' => 0, 'active' => 0, 'new' => 0, 'atRisk' => 0, 'dormant' => 0]),
                'memberLifetimeValue' => is_array($memberLifetimeValue) ? (object)$memberLifetimeValue : (is_object($memberLifetimeValue) ? $memberLifetimeValue : (object)['average' => 0, 'averageFormatted' => 'Rp 0', 'total' => 0, 'totalFormatted' => 'Rp 0', 'byTier' => (object)[]]),
                'churnAnalysis' => is_array($churnAnalysis) ? (object)$churnAnalysis : (is_object($churnAnalysis) ? $churnAnalysis : (object)[]),
                'conversionFunnel' => is_array($conversionFunnel) ? (object)$conversionFunnel : (is_object($conversionFunnel) ? $conversionFunnel : (object)[]),
                'regionalBreakdown' => is_array($regionalBreakdown) ? (object)$regionalBreakdown : (is_object($regionalBreakdown) ? $regionalBreakdown : (object)['currentMonth' => ['outlets' => [], 'regions' => [], 'period' => '', 'startDate' => '', 'endDate' => ''], 'last60Days' => ['outlets' => [], 'regions' => [], 'period' => '', 'startDate' => '', 'endDate' => ''], 'last90Days' => ['outlets' => [], 'regions' => [], 'period' => '', 'startDate' => '', 'endDate' => '']]),
                'comparisonData' => is_array($comparisonData) ? (object)$comparisonData : (is_object($comparisonData) ? $comparisonData : (object)['monthOverMonth' => ['members' => ['current' => 0, 'previous' => 0, 'growth' => 0], 'spending' => ['current' => 0, 'currentFormatted' => 'Rp 0', 'previous' => 0, 'previousFormatted' => 'Rp 0', 'growth' => 0]], 'yearOverYear' => ['members' => ['current' => 0, 'previous' => 0, 'growth' => 0], 'spending' => ['current' => 0, 'currentFormatted' => 'Rp 0', 'previous' => 0, 'previousFormatted' => 'Rp 0', 'growth' => 0]]]),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            ];
            
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
                'occupationDistribution' => [],
                'ageDistribution' => [],
                'purchasingPowerByAge' => [],
                'purchasingPowerByAgeThisMonth' => [],
                'spendingTrend' => [],
                'pointActivityTrend' => [],
                'latestMembers' => [],
                'latestPointTransactions' => [],
                'latestActivities' => [],
                'topSpenders' => [],
                'topSpendersDateRange' => null,
                'mostActiveMembers' => [],
                'mostActiveMembersDateRange' => null,
                'top10Points' => [],
                'top10VoucherOwners' => [],
                'top10PointRedemptions' => [],
                'memberFavouritePicks' => (object)['food' => [], 'beverages' => []],
                'activeVouchers' => [],
                'activeRewards' => [],
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
        // Use new cache key to force refresh with contribution data
        return Cache::remember('crm_dashboard_stats_v2', 300, function () {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Member statistics
        $totalMembers = MemberAppsMember::count();
        $newMembersToday = MemberAppsMember::whereDate('created_at', $today)->count();
        $newMembersThisMonth = MemberAppsMember::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();

        // Active Members: Members who have transactions in the last 90 days
        $last90Days = Carbon::now()->subDays(90);
        $activeMemberIds = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', $last90Days)
            ->distinct()
            ->pluck('member_id')
            ->toArray();
        $activeMembers = count($activeMemberIds);
        
        // Dormant Members: Members who have no activity (no login and no transaction) in the last 90 days
        // Get member IDs who have login in last 90 days
        $membersWithLogin = MemberAppsMember::whereNotNull('last_login_at')
            ->where('last_login_at', '>=', $last90Days)
            ->pluck('member_id')
            ->toArray();
        
        // Combine member IDs with activity (transaction or login)
        $membersWithActivity = array_unique(array_merge($activeMemberIds, $membersWithLogin));
        
        // Dormant = total members - members with activity
        $dormantMembers = $totalMembers - count($membersWithActivity);
        
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

        // Member contribution to revenue (hari ini, bulan ini, tahun ini)
        // Use explicit date format to ensure consistency - define dates first
        $todayStart = $today->format('Y-m-d 00:00:00');
        $todayEnd = $today->format('Y-m-d 23:59:59');
        $thisMonthStart = $thisMonth->format('Y-m-d 00:00:00');
        $thisMonthEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $thisYearStart = Carbon::now()->startOfYear()->format('Y-m-d 00:00:00');
        $thisYearEnd = Carbon::now()->format('Y-m-d 23:59:59');
        
        $spendingThisMonth = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', $thisMonthStart)
            ->where('created_at', '<=', $thisMonthEnd)
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
            ->where('created_at', '>=', $thisYearStart)
            ->where('created_at', '<=', $thisYearEnd)
            ->sum('grand_total');
        
        // Total revenue (all orders, including non-member)
        // Use whereDate for today to ensure it matches the date correctly
        $totalRevenueToday = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereDate('created_at', $today)
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
        
        // Member revenue (orders with member_id) - use same values as spending
        // Since spendingToday, spendingThisMonth, spendingThisYear already calculate member spending correctly
        $memberRevenueToday = $spendingToday;
        $memberRevenueThisMonth = $spendingThisMonth;
        $memberRevenueThisYear = $spendingThisYear;
        
        // Debug logging - check actual query results
        $testTotalToday = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereDate('created_at', $today)
            ->count();
        
        $testMemberToday = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereDate('created_at', $today)
            ->count();
        
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
            'activeMembersFormatted' => number_format($activeMembers, 0, ',', '.'),
            'dormantMembers' => $dormantMembers,
            'dormantMembersFormatted' => number_format($dormantMembers, 0, ',', '.'),
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
            
            // Member contribution to revenue
            'memberContributionToday' => $memberContributionToday,
            'memberContributionTodayFormatted' => number_format($memberContributionToday, 2, ',', '.') . '%',
            'memberRevenueToday' => $memberRevenueToday,
            'memberRevenueTodayFormatted' => 'Rp ' . number_format($memberRevenueToday, 0, ',', '.'),
            'totalRevenueToday' => $totalRevenueToday,
            'totalRevenueTodayFormatted' => 'Rp ' . number_format($totalRevenueToday, 0, ',', '.'),
            
            'memberContributionThisMonth' => $memberContributionThisMonth,
            'memberContributionThisMonthFormatted' => number_format($memberContributionThisMonth, 2, ',', '.') . '%',
            'memberRevenueThisMonth' => $memberRevenueThisMonth,
            'memberRevenueThisMonthFormatted' => 'Rp ' . number_format($memberRevenueThisMonth, 0, ',', '.'),
            'totalRevenueThisMonth' => $totalRevenueThisMonth,
            'totalRevenueThisMonthFormatted' => 'Rp ' . number_format($totalRevenueThisMonth, 0, ',', '.'),
            
            'memberContributionThisYear' => $memberContributionThisYear,
            'memberContributionThisYearFormatted' => number_format($memberContributionThisYear, 2, ',', '.') . '%',
            'memberRevenueThisYear' => $memberRevenueThisYear,
            'memberRevenueThisYearFormatted' => 'Rp ' . number_format($memberRevenueThisYear, 0, ',', '.'),
            'totalRevenueThisYear' => $totalRevenueThisYear,
            'totalRevenueThisYearFormatted' => 'Rp ' . number_format($totalRevenueThisYear, 0, ',', '.'),
            
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
     * Get occupation distribution - Optimized for performance
     */
    private function getOccupationDistribution()
    {
        // Optimized: JOIN with occupations table and COUNT with GROUP BY
        $distribution = MemberAppsMember::selectRaw('
                COALESCE(o.name, "Tidak Diketahui") as occupation_name,
                COUNT(*) as count
            ')
            ->leftJoin('member_apps_occupations as o', 'member_apps_members.pekerjaan_id', '=', 'o.id')
            ->groupBy('o.name', 'member_apps_members.pekerjaan_id')
            ->get()
            ->map(function ($item) {
                // Generate color based on occupation name hash
                $colors = [
                    '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', 
                    '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#14b8a6',
                    '#a855f7', '#ef4444', '#22c55e', '#eab308', '#3b82f6'
                ];
                $hash = crc32($item->occupation_name);
                $colorIndex = abs($hash) % count($colors);
                
                return [
                    'occupation' => $item->occupation_name,
                    'count' => (int) $item->count,
                    'color' => $colors[$colorIndex],
                ];
            })
            ->sortByDesc('count')
            ->values();

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
        
        // Build date filter for orders subquery - Default to current year (year to date)
        $orderDateFilter = '';
        if ($startDate && $endDate) {
            $orderDateFilter = "AND o.created_at BETWEEN '{$startDate}' AND '{$endDate}'";
        } else {
            // Default to current year (year to date)
            $yearStart = Carbon::now()->startOfYear()->format('Y-m-d 00:00:00');
            $yearEnd = Carbon::now()->format('Y-m-d 23:59:59');
            $orderDateFilter = "AND o.created_at BETWEEN '{$yearStart}' AND '{$yearEnd}'";
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
                    o.member_id,
                    SUM(o.grand_total) as total_spending,
                    AVG(o.grand_total) as avg_order_value,
                    COUNT(*) as order_count
                FROM {$dbJustusName}.orders as o
                WHERE o.status = 'paid'
                AND o.member_id != '' 
                AND o.member_id IS NOT NULL
                    {$orderDateFilter}
                GROUP BY o.member_id
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
     * Get purchasing power by age group for current month (daily breakdown) - for line chart
     */
    private function getPurchasingPowerByAgeThisMonth($startDate, $endDate)
    {
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        
        // Get all days in current month
        $daysInMonth = Carbon::now()->daysInMonth;
        $monthStart = Carbon::now()->startOfMonth();
        
        // Build date filter
        $orderDateFilter = "AND o.created_at BETWEEN '{$startDate}' AND '{$endDate}'";
        
        // Query to get daily spending by age group
        $sql = "
            SELECT 
                DATE(o.created_at) as transaction_date,
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 13 AND 18 THEN 'Remaja'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 19 AND 30 THEN 'Dewasa Muda'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 31 AND 45 THEN 'Dewasa Produktif'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) BETWEEN 46 AND 59 THEN 'Dewasa Matang'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) >= 60 THEN 'Usia Tua'
                    WHEN TIMESTAMPDIFF(YEAR, m.tanggal_lahir, CURDATE()) < 13 THEN 'Anak-anak'
                    ELSE 'Tidak Diketahui'
                END as age_group,
                SUM(o.grand_total) as total_spending,
                COUNT(*) as total_transactions
            FROM {$dbJustusName}.orders o
            INNER JOIN member_apps_members m ON o.member_id COLLATE utf8mb4_unicode_ci = m.member_id COLLATE utf8mb4_unicode_ci
            WHERE o.status = 'paid'
                AND o.member_id != ''
                AND o.member_id IS NOT NULL
                AND m.tanggal_lahir IS NOT NULL
                AND m.is_active = 1
                {$orderDateFilter}
            GROUP BY DATE(o.created_at), age_group
            ORDER BY DATE(o.created_at), 
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
        
        $results = DB::select($sql);
        
        // Create data structure for line chart
        $ageGroups = ['Anak-anak', 'Remaja', 'Dewasa Muda', 'Dewasa Produktif', 'Dewasa Matang', 'Usia Tua'];
        $chartData = [];
        
        // Initialize all days with zero values
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $monthStart->copy()->addDays($day - 1);
            $dateKey = $date->format('Y-m-d');
            $chartData[$dateKey] = [
                'date' => $dateKey,
                'date_label' => $date->format('d M'),
                'day' => $day,
            ];
            foreach ($ageGroups as $ageGroup) {
                $chartData[$dateKey][$ageGroup] = 0;
            }
        }
        
        // Fill with actual data
        foreach ($results as $row) {
            $dateKey = Carbon::parse($row->transaction_date)->format('Y-m-d');
            if (isset($chartData[$dateKey])) {
                $chartData[$dateKey][$row->age_group] = (float) $row->total_spending;
            }
        }
        
        return array_values($chartData);
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
        // Date range: 90 days back from today
        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        
        // First, get top spenders without subquery (much faster) - Last 90 days only
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
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('member_id')
            ->orderBy('total_spending', 'desc')
            ->limit(10)
            ->get();
        
        if ($topSpenders->isEmpty()) {
            return [
                'data' => collect([]),
                'dateRange' => [
                    'min_date' => $startDate->format('Y-m-d'),
                    'max_date' => $endDate->format('Y-m-d'),
                    'min_date_formatted' => $startDate->format('d M Y'),
                    'max_date_formatted' => $endDate->format('d M Y'),
                ],
            ];
        }

        // Get member info
        $memberIds = $topSpenders->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('member_id', $memberIds)->get()->keyBy('member_id');

        // Get last spending for each member in one query (batch) - Last 90 days only
        $lastOrders = DB::connection('db_justus')
            ->table('orders')
            ->select('member_id', 'grand_total', 'created_at')
            ->whereIn('member_id', $memberIds)
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('member_id')
            ->map(function ($orders) {
                return $orders->first();
            });

        $data = $topSpenders->map(function ($spender) use ($members, $lastOrders) {
            $member = $members->get($spender->member_id);
            $lastOrder = $lastOrders->get($spender->member_id);
            
            $lastSpending = $lastOrder ? (float) $lastOrder->grand_total : 0;
            $lastSpendingDate = $lastOrder ? Carbon::parse($lastOrder->created_at)->format('d M Y') : '-';
            
            return [
                'memberId' => $spender->member_id,
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'email' => $member->email ?? '-',
                'mobilePhone' => $member->mobile_phone ?? '-',
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

        // Format date range (90 days back from today)
        return [
            'data' => $data,
            'dateRange' => [
                'min_date' => $startDate->format('Y-m-d'),
                'max_date' => $endDate->format('Y-m-d'),
                'min_date_formatted' => $startDate->format('d M Y'),
                'max_date_formatted' => $endDate->format('d M Y'),
            ],
        ];
    }

    /**
     * Get most active members (10)
     */
    private function getMostActiveMembers()
    {
        // Date range: 90 days back from today
        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        
        $activeMembers = MemberAppsPointTransaction::select('member_id', DB::raw('COUNT(*) as transaction_count'), DB::raw('MAX(created_at) as last_transaction'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('member_id')
            ->orderBy('transaction_count', 'desc')
            ->limit(10)
            ->get();
        
        $memberIds = $activeMembers->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        
        // Get order counts for all members in one query - Last 90 days only
        $memberIdStrings = $members->pluck('member_id')->filter()->toArray();
        $orderCounts = DB::connection('db_justus')
            ->table('orders')
            ->select('member_id', DB::raw('COUNT(*) as order_count'))
            ->whereIn('member_id', $memberIdStrings)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        $data = $activeMembers->map(function ($active) use ($members, $orderCounts) {
            $member = $members->get($active->member_id);
            $orderCountData = $orderCounts->get($member->member_id ?? '');
            $orderCount = $orderCountData ? (int) $orderCountData->order_count : 0;
            
            return [
                'memberId' => $member->member_id ?? '-',
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'email' => $member->email ?? '-',
                'mobilePhone' => $member->mobile_phone ?? '-',
                'transactionCount' => $active->transaction_count,
                'orderCount' => $orderCount,
                'pointBalance' => $member->just_points ?? 0,
                'pointBalanceFormatted' => number_format($member->just_points ?? 0, 0, ',', '.'),
                'lastTransactionDate' => Carbon::parse($active->last_transaction)->format('d M Y, H:i'),
            ];
        });

        // Format date range (90 days back from today)
        return [
            'data' => $data,
            'dateRange' => [
                'min_date' => $startDate->format('Y-m-d'),
                'max_date' => $endDate->format('Y-m-d'),
                'min_date_formatted' => $startDate->format('d M Y'),
                'max_date_formatted' => $endDate->format('d M Y'),
            ],
        ];
    }

    /**
     * Get top 10 members with most points
     */
    private function getTop10Points()
    {
        $topMembers = MemberAppsMember::select('id', 'member_id', 'nama_lengkap', 'just_points', 'email', 'mobile_phone', 'member_level')
            ->where('just_points', '>', 0)
            ->orderBy('just_points', 'desc')
            ->limit(10)
            ->get();

        $data = $topMembers->map(function ($member) {
            return [
                'memberId' => $member->member_id ?? '-',
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'pointBalance' => (int) $member->just_points,
                'pointBalanceFormatted' => number_format($member->just_points, 0, ',', '.'),
                'email' => $member->email ?? '-',
                'mobilePhone' => $member->mobile_phone ?? '-',
                'memberLevel' => $member->member_level ?? '-',
            ];
        });

        return $data;
    }

    /**
     * Get top 10 members with most vouchers
     */
    private function getTop10VoucherOwners()
    {
        // Count vouchers per member (only active vouchers)
        $voucherCounts = DB::table('member_apps_member_vouchers')
            ->select('member_id', DB::raw('COUNT(*) as voucher_count'))
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->groupBy('member_id')
            ->orderBy('voucher_count', 'desc')
            ->limit(10)
            ->get();

        if ($voucherCounts->isEmpty()) {
            return collect([]);
        }

        $memberIds = $voucherCounts->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');

        $data = $voucherCounts->map(function ($voucherCount) use ($members) {
            $member = $members->get($voucherCount->member_id);
            
            return [
                'memberId' => $member->member_id ?? '-',
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'voucherCount' => (int) $voucherCount->voucher_count,
                'voucherCountFormatted' => number_format($voucherCount->voucher_count, 0, ',', '.'),
                'email' => $member->email ?? '-',
                'mobilePhone' => $member->mobile_phone ?? '-',
                'memberLevel' => $member->member_level ?? '-',
                'pointBalance' => (int) ($member->just_points ?? 0),
                'pointBalanceFormatted' => number_format($member->just_points ?? 0, 0, ',', '.'),
            ];
        });

        return $data;
    }

    /**
     * Get top 10 members with most point redemptions
     */
    private function getTop10PointRedemptions()
    {
        // Get members with most redeemed points (negative point_amount)
        $redemptions = MemberAppsPointTransaction::select('member_id', DB::raw('SUM(ABS(point_amount)) as total_redeemed'))
            ->where('point_amount', '<', 0)
            ->groupBy('member_id')
            ->orderBy('total_redeemed', 'desc')
            ->limit(10)
            ->get();

        if ($redemptions->isEmpty()) {
            return collect([]);
        }

        $memberIds = $redemptions->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');

        // Get redemption count per member
        $redemptionCounts = MemberAppsPointTransaction::select('member_id', DB::raw('COUNT(*) as redemption_count'))
            ->where('point_amount', '<', 0)
            ->whereIn('member_id', $memberIds)
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        $data = $redemptions->map(function ($redemption) use ($members, $redemptionCounts) {
            $member = $members->get($redemption->member_id);
            $redemptionCount = $redemptionCounts->get($redemption->member_id);
            
            return [
                'memberId' => $member->member_id ?? '-',
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'totalRedeemed' => (int) $redemption->total_redeemed,
                'totalRedeemedFormatted' => number_format($redemption->total_redeemed, 0, ',', '.'),
                'redemptionCount' => $redemptionCount ? (int) $redemptionCount->redemption_count : 0,
                'redemptionCountFormatted' => $redemptionCount ? number_format($redemptionCount->redemption_count, 0, ',', '.') : '0',
                'email' => $member->email ?? '-',
                'mobilePhone' => $member->mobile_phone ?? '-',
                'memberLevel' => $member->member_level ?? '-',
                'pointBalance' => (int) ($member->just_points ?? 0),
                'pointBalanceFormatted' => number_format($member->just_points ?? 0, 0, ',', '.'),
            ];
        });

        return $data;
    }

    /**
     * Get member favourite picks (top 10 most ordered items by members in last 90 days)
     * Separated by Food and Beverages
     */
    private function getMemberFavouritePicks()
    {
        // Date range: 90 days back from today
        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        
        $dbJustusName = config('database.connections.db_justus.database');
        
        // Get Food items (type: Food, Food Western, Food Asian)
        $foodPicks = DB::connection('db_justus')
            ->table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('items as i', 'oi.item_id', '=', 'i.id')
            ->where('o.status', 'paid')
            ->whereNotNull('o.member_id')
            ->where('o.member_id', '!=', '')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->whereIn('i.type', ['Food', 'Food Western', 'Food Asian'])
            ->select(
                'oi.item_name',
                'i.type as item_type',
                DB::raw('SUM(oi.qty) as total_quantity'),
                DB::raw('COUNT(DISTINCT o.id) as order_count'),
                DB::raw('COUNT(DISTINCT o.member_id) as member_count'),
                DB::raw('SUM(oi.subtotal) as total_revenue')
            )
            ->groupBy('oi.item_name', 'i.type')
            ->havingRaw('SUM(oi.subtotal) > 0') // Filter out items with 0 revenue
            ->orderBy('total_quantity', 'desc')
            ->limit(10)
            ->get();
        
        // Get Beverages items (type: Beverages)
        $beveragePicks = DB::connection('db_justus')
            ->table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('items as i', 'oi.item_id', '=', 'i.id')
            ->where('o.status', 'paid')
            ->whereNotNull('o.member_id')
            ->where('o.member_id', '!=', '')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->where('i.type', 'Beverages')
            ->select(
                'oi.item_name',
                'i.type as item_type',
                DB::raw('SUM(oi.qty) as total_quantity'),
                DB::raw('COUNT(DISTINCT o.id) as order_count'),
                DB::raw('COUNT(DISTINCT o.member_id) as member_count'),
                DB::raw('SUM(oi.subtotal) as total_revenue')
            )
            ->groupBy('oi.item_name', 'i.type')
            ->havingRaw('SUM(oi.subtotal) > 0') // Filter out items with 0 revenue
            ->orderBy('total_quantity', 'desc')
            ->limit(10)
            ->get();
        
        $formatItems = function ($items, $category) {
            return $items->map(function ($item) use ($category) {
                return [
                    'item_name' => $item->item_name ?? '-',
                    'item_type' => $item->item_type ?? $category,
                    'category' => $category,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_quantity_formatted' => number_format($item->total_quantity, 0, ',', '.'),
                    'order_count' => (int) $item->order_count,
                    'member_count' => (int) $item->member_count,
                    'total_revenue' => (float) $item->total_revenue,
                    'total_revenue_formatted' => 'Rp ' . number_format($item->total_revenue, 0, ',', '.'),
                ];
            });
        };
        
        return [
            'food' => $formatItems($foodPicks, 'Food'),
            'beverages' => $formatItems($beveragePicks, 'Beverages'),
        ];
    }

    /**
     * Get active vouchers with member statistics
     */
    private function getActiveVouchers()
    {
        $today = Carbon::today();
        
        // Get active vouchers (is_active = 1 and not expired)
        $vouchers = DB::table('member_apps_vouchers')
            ->where('is_active', 1)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $today->format('Y-m-d'));
            })
            ->select('id', 'name', 'description', 'points_required', 'discount_percentage', 'discount_amount', 'voucher_type', 'valid_until', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get statistics for each voucher
        $voucherIds = $vouchers->pluck('id')->toArray();
        
        // Get member count who have the voucher (not used/redeemed)
        // Voucher yang belum digunakan: used_at IS NULL atau used_in_transaction_id IS NULL
        $memberVoucherCounts = DB::table('member_apps_member_vouchers')
            ->whereIn('voucher_id', $voucherIds)
            ->whereNull('used_at')
            ->whereNull('used_in_transaction_id')
            ->select('voucher_id', DB::raw('COUNT(DISTINCT member_id) as member_count'))
            ->groupBy('voucher_id')
            ->get()
            ->keyBy('voucher_id');
        
        // Get member count who have redeemed/used the voucher
        // Voucher yang sudah digunakan: used_at IS NOT NULL atau used_in_transaction_id IS NOT NULL
        $redeemedCounts = DB::table('member_apps_member_vouchers')
            ->whereIn('voucher_id', $voucherIds)
            ->where(function ($query) {
                $query->whereNotNull('used_at')
                    ->orWhereNotNull('used_in_transaction_id');
            })
            ->select('voucher_id', DB::raw('COUNT(DISTINCT member_id) as redeemed_count'))
            ->groupBy('voucher_id')
            ->get()
            ->keyBy('voucher_id');
        
        return $vouchers->map(function ($voucher) use ($memberVoucherCounts, $redeemedCounts) {
            $memberCount = $memberVoucherCounts->get($voucher->id);
            $redeemedCount = $redeemedCounts->get($voucher->id);
            
            // Determine discount display based on voucher_type
            $discountDisplay = '-';
            if ($voucher->voucher_type === 'percentage' && $voucher->discount_percentage) {
                $discountDisplay = $voucher->discount_percentage . '%';
            } elseif ($voucher->voucher_type === 'fixed' && $voucher->discount_amount) {
                $discountDisplay = 'Rp ' . number_format($voucher->discount_amount, 0, ',', '.');
            }
            
            return [
                'id' => $voucher->id,
                'name' => $voucher->name ?? '-',
                'description' => $voucher->description ?? '-',
                'point_cost' => (int) ($voucher->points_required ?? 0),
                'point_cost_formatted' => number_format($voucher->points_required ?? 0, 0, ',', '.'),
                'discount_type' => $voucher->voucher_type ?? '-',
                'discount_value' => $voucher->voucher_type === 'percentage' 
                    ? (float) ($voucher->discount_percentage ?? 0)
                    : (float) ($voucher->discount_amount ?? 0),
                'discount_display' => $discountDisplay,
                'expired_at' => $voucher->valid_until ? Carbon::parse($voucher->valid_until)->format('d M Y') : 'Tidak ada batas waktu',
                'member_count' => $memberCount ? (int) $memberCount->member_count : 0,
                'member_count_formatted' => number_format($memberCount ? $memberCount->member_count : 0, 0, ',', '.'),
                'redeemed_count' => $redeemedCount ? (int) $redeemedCount->redeemed_count : 0,
                'redeemed_count_formatted' => number_format($redeemedCount ? $redeemedCount->redeemed_count : 0, 0, ',', '.'),
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
        // Limit to active members only and use indexed columns
        $dbJustusName = DB::connection('db_justus')->getDatabaseName();
        
        // Optimized: Use single query with JOIN to calculate LTV by tier
        $ltvData = DB::select("
            SELECT 
                COALESCE(m.member_level, 'silver') as tier,
                COUNT(DISTINCT CASE WHEN o.total_spending > 0 THEN m.id END) as member_count,
                COALESCE(SUM(CASE WHEN o.total_spending > 0 THEN o.total_spending ELSE 0 END), 0) as total_spending
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
            ) as o ON m.member_id COLLATE utf8mb4_unicode_ci = o.member_id COLLATE utf8mb4_unicode_ci
            WHERE m.is_active = 1
            GROUP BY tier
        ");
        
        $ltvByTier = [
            'silver' => ['total' => 0, 'count' => 0],
            'loyal' => ['total' => 0, 'count' => 0],
            'elite' => ['total' => 0, 'count' => 0],
        ];
        
        $totalLTV = 0;
        $memberCount = 0;
        
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
        
        // First Transaction: Members who registered in last 30 days and made at least one transaction
        // Optimize: Get member_ids first, then query orders (faster than whereExists on large tables)
        $newMemberIds = MemberAppsMember::where('created_at', '>=', $last30Days)
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->pluck('member_id')
            ->unique()
            ->values()
            ->toArray();
        
        $firstTransaction = 0;
        if (!empty($newMemberIds)) {
            // Use chunking if too many members to avoid "too many placeholders" error
            if (count($newMemberIds) > 1000) {
                // For large datasets, use a more efficient approach - limit to first 1000 for performance
                $firstTransaction = DB::connection('db_justus')
                    ->table('orders')
                    ->whereIn('member_id', array_slice($newMemberIds, 0, 1000))
                    ->where('status', 'paid')
                    ->distinct('member_id')
                    ->count('member_id');
            } else {
                $firstTransaction = DB::connection('db_justus')
                    ->table('orders')
                    ->whereIn('member_id', $newMemberIds)
                    ->where('status', 'paid')
                    ->distinct('member_id')
                    ->count('member_id');
            }
        }
        
        // Repeat Customers: Members who registered in last 30 days and made 2+ transactions
        $repeatCustomers = 0;
        if (!empty($newMemberIds) && count($newMemberIds) <= 1000) {
            $repeatCustomers = DB::connection('db_justus')
                ->table('orders')
                ->whereIn('member_id', $newMemberIds)
                ->where('status', 'paid')
                ->groupBy('member_id')
                ->havingRaw('COUNT(*) >= 2')
                ->get()
                ->count();
        }
        
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
        $now = Carbon::now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $last60Days = $now->copy()->subDays(60);
        $last90Days = $now->copy()->subDays(90);
        
        // Helper function to get data for a period
        $getDataForPeriod = function($startDate, $endDate) {
            $ordersByOutlet = DB::connection('db_justus')
                ->table('orders as o')
                ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                ->leftJoin('regions as r', 'outlet.region_id', '=', 'r.id')
                ->select(
                    'outlet.nama_outlet',
                    'r.name as region_name',
                    'r.id as region_id',
                    DB::raw('COUNT(DISTINCT o.member_id) as total_members'),
                    DB::raw('COUNT(o.id) as total_orders'),
                    DB::raw('SUM(o.grand_total) as total_spending')
                )
                ->where('o.status', 'paid')
                ->whereNotNull('o.member_id')
                ->where('o.member_id', '!=', '')
                ->whereBetween('o.created_at', [$startDate, $endDate])
                ->groupBy('outlet.nama_outlet', 'r.name', 'r.id')
            ->get();

            return $ordersByOutlet->map(function($item) {
                return [
                    'outlet_name' => $item->nama_outlet ?? 'Unknown',
                    'region' => $item->region_name ?? '-',
                    'region_id' => $item->region_id ?? null,
                    'total_members' => (int) $item->total_members,
                    'total_orders' => (int) $item->total_orders,
                    'total_spending' => (float) $item->total_spending,
                    'total_spending_formatted' => 'Rp ' . number_format($item->total_spending, 0, ',', '.'),
                    'avg_order_value' => $item->total_orders > 0 ? (float) ($item->total_spending / $item->total_orders) : 0,
                    'avg_order_value_formatted' => $item->total_orders > 0 ? 'Rp ' . number_format($item->total_spending / $item->total_orders, 0, ',', '.') : 'Rp 0',
                ];
            })->values()->toArray();
        };
        
        // Get data for each period
        $currentMonthData = $getDataForPeriod($currentMonthStart, $now);
        $last60DaysData = $getDataForPeriod($last60Days, $now);
        $last90DaysData = $getDataForPeriod($last90Days, $now);
        
        // Aggregate by region for pie chart
        $aggregateByRegion = function($data) {
            $regionData = [];
            foreach ($data as $item) {
                $region = $item['region'] ?? 'Unknown';
                if (!isset($regionData[$region])) {
                    $regionData[$region] = [
                        'region' => $region,
                        'total_spending' => 0,
                        'total_orders' => 0,
                        'total_members' => 0,
                    ];
                }
                $regionData[$region]['total_spending'] += $item['total_spending'];
                $regionData[$region]['total_orders'] += $item['total_orders'];
                $regionData[$region]['total_members'] += $item['total_members'];
            }
            return array_values($regionData);
        };
        
            return [
            'currentMonth' => [
                'outlets' => $currentMonthData,
                'regions' => $aggregateByRegion($currentMonthData),
                'period' => 'Bulan Berjalan',
                'startDate' => $currentMonthStart->format('Y-m-d'),
                'endDate' => $now->format('Y-m-d'),
            ],
            'last60Days' => [
                'outlets' => $last60DaysData,
                'regions' => $aggregateByRegion($last60DaysData),
                'period' => '60 Hari Terakhir',
                'startDate' => $last60Days->format('Y-m-d'),
                'endDate' => $now->format('Y-m-d'),
            ],
            'last90Days' => [
                'outlets' => $last90DaysData,
                'regions' => $aggregateByRegion($last90DaysData),
                'period' => '90 Hari Terakhir',
                'startDate' => $last90Days->format('Y-m-d'),
                'endDate' => $now->format('Y-m-d'),
            ],
        ];
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
        
        // Current Month - Only member orders
        $currentMonthMembers = MemberAppsMember::whereBetween('created_at', [$currentMonth, Carbon::now()])->count();
        $currentMonthSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$currentMonth, Carbon::now()])
            ->sum('grand_total');
        
        // Last Month - Only member orders
        $lastMonthMembers = MemberAppsMember::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $lastMonthSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->sum('grand_total');
        
        // Current Year - Only member orders
        $currentYearMembers = MemberAppsMember::whereBetween('created_at', [$currentYear, Carbon::now()])->count();
        $currentYearSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$currentYear, Carbon::now()])
            ->sum('grand_total');
        
        // Last Year - Only member orders
        $lastYearMembers = MemberAppsMember::whereBetween('created_at', [$lastYear, $lastYearEnd])->count();
        $lastYearSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
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
    
    /**
     * Get member transactions (for modal)
     */
    public function getMemberTransactions(Request $request)
    {
        try {
            $memberId = $request->get('member_id');
            $type = $request->get('type', 'orders'); // 'orders' or 'points'
            
            if (!$memberId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member ID is required',
                ], 400);
            }
            
            $dbJustusName = DB::connection('db_justus')->getDatabaseName();
            
            if ($type === 'orders') {
                // Get orders for this member
                $orders = DB::connection('db_justus')
                    ->table('orders as o')
                    ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                    ->where('o.member_id', $memberId)
                    ->where('o.status', 'paid')
                    ->select([
                        'o.id',
                        'o.paid_number',
                        'o.grand_total',
                        'o.created_at',
                        'outlet.nama_outlet',
                        'o.kode_outlet',
                    ])
                    ->orderBy('o.created_at', 'desc')
                    ->limit(100)
                    ->get();
                
                // Get order items for each order
                $orderIds = $orders->pluck('id')->toArray();
                $orderItems = DB::connection('db_justus')
                    ->table('order_items')
                    ->whereIn('order_id', $orderIds)
                    ->select('order_id', 'item_name', 'qty', 'price', 'subtotal', 'modifiers', 'notes')
                    ->get()
                    ->groupBy('order_id');
                
                $transactions = $orders->map(function ($order) use ($orderItems) {
                    $items = $orderItems->get($order->id) ?? collect([]);
                    
                    return [
                        'id' => $order->id,
                        'order_number' => $order->paid_number ?? $order->id ?? '-',
                        'outlet_name' => $order->nama_outlet ?? 'Outlet Tidak Diketahui',
                        'kode_outlet' => $order->kode_outlet ?? '-',
                        'grand_total' => (float) $order->grand_total,
                        'grand_total_formatted' => 'Rp ' . number_format($order->grand_total, 0, ',', '.'),
                        'created_at' => Carbon::parse($order->created_at)->format('d M Y, H:i'),
                        'created_at_full' => $order->created_at,
                        'items' => $items->map(function ($item) {
                            // Decode modifiers from JSON
                            // Format: {"Potato":{"Mashed Potato":1},"Saus":{"Mushroom":1,"Blackpepper":1}}
                            $modifiers = [];
                            if (isset($item->modifiers) && $item->modifiers) {
                                try {
                                    $modifiersData = json_decode($item->modifiers, true);
                                    if (is_array($modifiersData)) {
                                        // Check if it's the flat key-value format (object with modifier names as keys)
                                        // Format: {"Potato": {"Mashed Potato": 1}, "Saus": {"Mushroom": 1}}
                                        $isFlatFormat = false;
                                        foreach ($modifiersData as $key => $value) {
                                            // If key is not numeric and value is an array, it's likely the flat format
                                            if (!is_numeric($key) && is_array($value)) {
                                                $isFlatFormat = true;
                                                break;
                                            }
                                        }
                                        
                                        if ($isFlatFormat) {
                                            // Handle flat format: {"Potato": {"Mashed Potato": 1}, "Saus": {"Mushroom": 1}}
                                            foreach ($modifiersData as $modifierName => $options) {
                                                if (is_array($options)) {
                                                    $optionNames = [];
                                                    foreach ($options as $optionName => $quantity) {
                                                        // Option name is the key, quantity is the value
                                                        $optionNames[] = $optionName;
                                                    }
                                                    if (!empty($optionNames)) {
                                                        $modifiers[] = [
                                                            'name' => $modifierName,
                                                            'options' => implode(', ', $optionNames),
                                                        ];
                                                    }
                                                }
                                            }
                                        } else {
                                            // Handle array format: [{"name": "Potato", "options": [...]}]
                                            foreach ($modifiersData as $modifier) {
                                                if (is_array($modifier)) {
                                                    $modifierName = $modifier['name'] ?? $modifier['modifier_name'] ?? 'Modifier';
                                                    $modifierOptions = [];
                                                    
                                                    // Handle different modifier option formats
                                                    if (isset($modifier['options']) && is_array($modifier['options'])) {
                                                        foreach ($modifier['options'] as $option) {
                                                            if (is_array($option)) {
                                                                $modifierOptions[] = $option['name'] ?? $option['option_name'] ?? (string) $option;
                                                            } else {
                                                                $modifierOptions[] = (string) $option;
                                                            }
                                                        }
                                                    } elseif (isset($modifier['option'])) {
                                                        $modifierOptions[] = is_array($modifier['option']) 
                                                            ? ($modifier['option']['name'] ?? (string) $modifier['option'])
                                                            : (string) $modifier['option'];
                                                    }
                                                    
                                                    if (!empty($modifierOptions)) {
                                                        $modifiers[] = [
                                                            'name' => $modifierName,
                                                            'options' => implode(', ', $modifierOptions),
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    // If JSON decode fails, keep modifiers empty
                                    Log::error('CRM Modifier Parse Error: ' . $e->getMessage(), [
                                        'modifiers' => $item->modifiers,
                                    ]);
                                    $modifiers = [];
                                }
                            }
                            
                            return [
                                'item_name' => $item->item_name ?? '-',
                                'quantity' => (int) ($item->qty ?? 0),
                                'price' => (float) $item->price,
                                'price_formatted' => 'Rp ' . number_format($item->price, 0, ',', '.'),
                                'subtotal' => (float) $item->subtotal,
                                'subtotal_formatted' => 'Rp ' . number_format($item->subtotal, 0, ',', '.'),
                                'modifiers' => $modifiers,
                                'notes' => $item->notes ?? null,
                            ];
                        })->toArray(),
                    ];
                });
            } else {
                // Get point transactions for this member
                $member = MemberAppsMember::where('member_id', $memberId)->first();
                if (!$member) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Member not found',
                    ], 404);
                }
                
                $pointTransactions = MemberAppsPointTransaction::where('member_id', $member->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
                
                // Get outlet names from orders
                $memberIdStrings = [$member->member_id];
                $transactions = $pointTransactions->map(function ($pt) use ($dbJustusName, $memberIdStrings) {
                    $isEarned = $pt->point_amount > 0;
                    
                    // Try to get outlet name from order
                    $outletName = 'Outlet Tidak Diketahui';
                    $transactionAmount = $pt->transaction_amount ?? 0;
                    
                    // Try to find order_id
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
                    if ($orderId && $memberIdStrings[0]) {
                        $orderWithOutlet = DB::connection('db_justus')
                            ->table('orders')
                            ->leftJoin('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                            ->where('orders.id', $orderId)
                            ->where('orders.member_id', $memberIdStrings[0])
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
                        'type' => $isEarned ? 'earned' : 'redeemed',
                        'typeText' => $isEarned ? 'EARNED' : 'REDEEMED',
                        'transactionType' => $transactionType,
                        'description' => $description,
                        'pointAmount' => abs($pt->point_amount),
                        'pointAmountFormatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                        'transactionAmount' => $transactionAmount,
                        'transactionAmountFormatted' => $transactionAmount > 0 ? 'Rp ' . number_format($transactionAmount, 0, ',', '.') : '-',
                        'outletName' => $outletName,
                        'created_at' => Carbon::parse($pt->created_at)->format('d M Y, H:i'),
                        'created_at_full' => $pt->created_at,
                    ];
                });
            }
            
            return response()->json([
                'status' => 'success',
                'type' => $type,
                'member_id' => $memberId,
                'data' => $transactions->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Error getting member transactions: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get member transactions',
            ], 500);
        }
    }

    /**
     * Get member vouchers detail
     */
    public function getMemberVouchers(Request $request)
    {
        try {
            $memberId = $request->get('member_id');
            
            if (!$memberId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member ID is required',
                ], 400);
            }
            
            $member = MemberAppsMember::where('member_id', $memberId)->first();
            if (!$member) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member not found',
                ], 404);
            }
            
            // Get all vouchers for this member (active and used)
            $vouchers = DB::table('member_apps_member_vouchers as mav')
                ->join('member_apps_vouchers as av', 'mav.voucher_id', '=', 'av.id')
                ->where('mav.member_id', $member->id)
                ->select([
                    'mav.id',
                    'mav.serial_code',
                    'mav.status',
                    'mav.expires_at',
                    'mav.used_at',
                    'mav.created_at',
                    'av.name as voucher_name',
                    'av.description',
                    'av.voucher_type',
                    'av.discount_percentage',
                    'av.discount_amount',
                    'av.min_purchase',
                ])
                ->orderBy('mav.created_at', 'desc')
                ->get();
            
            $vouchersData = $vouchers->map(function ($voucher) {
                $isExpired = $voucher->expires_at && Carbon::parse($voucher->expires_at)->isPast();
                $status = $voucher->status;
                if ($isExpired && $status === 'active') {
                    $status = 'expired';
                }
                
                // Determine discount type and value based on voucher_type
                $discountType = 'percentage';
                $discountValue = 0;
                
                if ($voucher->voucher_type === 'discount_percentage' || $voucher->discount_percentage > 0) {
                    $discountType = 'percentage';
                    $discountValue = (float) ($voucher->discount_percentage ?? 0);
                    $discountFormatted = number_format($discountValue, 0, ',', '.') . '%';
                } elseif ($voucher->voucher_type === 'discount_amount' || $voucher->discount_amount > 0) {
                    $discountType = 'fixed';
                    $discountValue = (float) ($voucher->discount_amount ?? 0);
                    $discountFormatted = 'Rp ' . number_format($discountValue, 0, ',', '.');
                } else {
                    $discountFormatted = '-';
                }
                
                return [
                    'id' => $voucher->id,
                    'serialCode' => $voucher->serial_code ?? '-',
                    'voucherName' => $voucher->voucher_name ?? 'Voucher',
                    'description' => $voucher->description ?? '',
                    'status' => $status,
                    'statusText' => ucfirst($status),
                    'voucherType' => $voucher->voucher_type ?? 'discount',
                    'discountType' => $discountType,
                    'discountValue' => $discountValue,
                    'discountFormatted' => $discountFormatted,
                    'minimumPurchase' => (float) ($voucher->min_purchase ?? 0),
                    'minimumPurchaseFormatted' => $voucher->min_purchase > 0 
                        ? 'Rp ' . number_format($voucher->min_purchase, 0, ',', '.')
                        : '-',
                    'expiresAt' => $voucher->expires_at ? Carbon::parse($voucher->expires_at)->format('d M Y, H:i') : '-',
                    'usedAt' => $voucher->used_at ? Carbon::parse($voucher->used_at)->format('d M Y, H:i') : '-',
                    'createdAt' => Carbon::parse($voucher->created_at)->format('d M Y, H:i'),
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'member_id' => $memberId,
                'data' => $vouchersData->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Error getting member vouchers: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get member vouchers',
            ], 500);
        }
    }

    /**
     * Get member point redemptions detail with location
     */
    public function getMemberPointRedemptions(Request $request)
    {
        try {
            $memberId = $request->get('member_id');
            
            if (!$memberId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member ID is required',
                ], 400);
            }
            
            $member = MemberAppsMember::where('member_id', $memberId)->first();
            if (!$member) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member not found',
                ], 404);
            }
            
            // Get point redemptions (negative point_amount)
            $redemptions = MemberAppsPointTransaction::where('member_id', $member->id)
                ->where('point_amount', '<', 0)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
            
            $dbJustusName = DB::connection('db_justus')->getDatabaseName();
            $memberIdString = $member->member_id;
            
            $redemptionsData = $redemptions->map(function ($redemption) use ($dbJustusName, $memberIdString) {
                $outletName = 'Outlet Tidak Diketahui';
                $transactionAmount = $redemption->transaction_amount ?? 0;
                $redemptionType = 'Unknown';
                $redemptionDetail = '';
                
                // Try to get outlet name and details from order
                $orderId = null;
                if (isset($redemption->metadata) && $redemption->metadata) {
                    $metadata = json_decode($redemption->metadata ?? '{}', true);
                    $orderId = $metadata['order_id'] ?? null;
                }
                
                if (!$orderId && isset($redemption->reference_id)) {
                    $orderId = $redemption->reference_id;
                }
                
                if (!$orderId && isset($redemption->transaction_id)) {
                    $orderId = $redemption->transaction_id;
                }
                
                // Get outlet name from order
                if ($orderId && $memberIdString) {
                    $orderWithOutlet = DB::connection('db_justus')
                        ->table('orders')
                        ->leftJoin('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                        ->where('orders.id', $orderId)
                        ->where('orders.member_id', $memberIdString)
                        ->select(['orders.grand_total', 'o.nama_outlet', 'orders.kode_outlet'])
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
                
                // Determine redemption type
                $transactionType = $redemption->transaction_type ?? null;
                if ($transactionType === 'voucher_purchase') {
                    $redemptionType = 'Voucher Purchase';
                    $redemptionDetail = 'Purchase voucher using points';
                } elseif ($transactionType === 'reward_redemption' || $transactionType === 'redeem') {
                    $redemptionType = 'Reward Redemption';
                    $redemptionDetail = 'Redeem reward using points';
                } else {
                    $redemptionType = 'Point Redemption';
                    $redemptionDetail = 'Redeem points for transaction';
                }
                
                return [
                    'id' => $redemption->id,
                    'redemptionType' => $redemptionType,
                    'redemptionDetail' => $redemptionDetail,
                    'pointAmount' => abs($redemption->point_amount),
                    'pointAmountFormatted' => number_format(abs($redemption->point_amount), 0, ',', '.'),
                    'transactionAmount' => $transactionAmount,
                    'transactionAmountFormatted' => $transactionAmount > 0 ? 'Rp ' . number_format($transactionAmount, 0, ',', '.') : '-',
                    'outletName' => $outletName,
                    'outletCode' => $orderWithOutlet->kode_outlet ?? '-',
                    'createdAt' => Carbon::parse($redemption->created_at)->format('d M Y, H:i'),
                    'createdAtFull' => $redemption->created_at,
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'member_id' => $memberId,
                'data' => $redemptionsData->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Error getting member point redemptions: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get member point redemptions',
            ], 500);
        }
    }
    
    /**
     * Get contribution by outlet (for modal)
     */
    public function getContributionByOutlet(Request $request)
    {
        try {
            $period = $request->get('period', 'today'); // today, month, year
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $thisYear = Carbon::now()->startOfYear();
            
            // Determine date range based on period
            if ($period === 'today') {
                $startDate = $today->format('Y-m-d 00:00:00');
                $endDate = $today->format('Y-m-d 23:59:59');
            } elseif ($period === 'month') {
                $startDate = $thisMonth->format('Y-m-d 00:00:00');
                $endDate = Carbon::now()->format('Y-m-d 23:59:59');
            } else { // year
                $startDate = $thisYear->format('Y-m-d 00:00:00');
                $endDate = Carbon::now()->format('Y-m-d 23:59:59');
            }
            
            // Get total revenue per outlet (all orders)
            $totalRevenueByOutlet = DB::connection('db_justus')
                ->table('orders as o')
                ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                ->where('o.status', 'paid')
                ->where('o.created_at', '>=', $startDate)
                ->where('o.created_at', '<=', $endDate)
                ->select([
                    'outlet.nama_outlet',
                    'o.kode_outlet',
                    DB::raw('SUM(o.grand_total) as total_revenue'),
                    DB::raw('COUNT(*) as total_orders')
                ])
                ->groupBy('outlet.nama_outlet', 'o.kode_outlet')
                ->get();
            
            // Get member revenue per outlet (orders with member_id)
            $memberRevenueByOutlet = DB::connection('db_justus')
                ->table('orders as o')
                ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                ->where('o.status', 'paid')
                ->whereNotNull('o.member_id')
                ->where('o.member_id', '!=', '')
                ->where('o.created_at', '>=', $startDate)
                ->where('o.created_at', '<=', $endDate)
                ->select([
                    'outlet.nama_outlet',
                    'o.kode_outlet',
                    DB::raw('SUM(o.grand_total) as member_revenue'),
                    DB::raw('COUNT(*) as member_orders')
                ])
                ->groupBy('outlet.nama_outlet', 'o.kode_outlet')
                ->get()
                ->keyBy('kode_outlet');
            
            // Combine data
            $result = $totalRevenueByOutlet->map(function ($item) use ($memberRevenueByOutlet) {
                $kodeOutlet = $item->kode_outlet;
                $memberData = $memberRevenueByOutlet->get($kodeOutlet);
                
                $totalRevenue = (float) $item->total_revenue;
                $memberRevenue = $memberData ? (float) $memberData->member_revenue : 0;
                $contribution = $totalRevenue > 0 ? round(($memberRevenue / $totalRevenue) * 100, 2) : 0;
                
                return [
                    'outlet_name' => $item->nama_outlet ?? 'Outlet Tidak Diketahui',
                    'kode_outlet' => $kodeOutlet ?? '-',
                    'total_revenue' => $totalRevenue,
                    'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                    'member_revenue' => $memberRevenue,
                    'member_revenue_formatted' => 'Rp ' . number_format($memberRevenue, 0, ',', '.'),
                    'contribution' => $contribution,
                    'contribution_formatted' => number_format($contribution, 2, ',', '.') . '%',
                    'total_orders' => (int) $item->total_orders,
                    'member_orders' => $memberData ? (int) $memberData->member_orders : 0,
                ];
            })
            ->sortByDesc('member_revenue')
            ->values();
            
            return response()->json([
                'status' => 'success',
                'period' => $period,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('CRM Contribution by Outlet Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get active challenges with statistics
     */
    private function getActiveChallenges()
    {
        try {
            $today = Carbon::today();
            
            // Get active challenges (is_active = true and not expired)
            $challenges = DB::table('member_apps_challenges as c')
                ->where('c.is_active', true)
                ->where(function($query) use ($today) {
                    $query->whereNull('c.end_date')
                        ->orWhere('c.end_date', '>=', $today);
                })
                ->where(function($query) use ($today) {
                    $query->whereNull('c.start_date')
                        ->orWhere('c.start_date', '<=', $today);
                })
                ->select(
                    'c.id',
                    'c.title',
                    'c.description',
                    'c.image',
                    'c.points_reward',
                    'c.start_date',
                    'c.end_date',
                    'c.challenge_type_id',
                    'c.created_at'
                )
                ->orderBy('c.created_at', 'desc')
                ->get();
            
            // Get statistics for each challenge
            $challengesWithStats = $challenges->map(function($challenge) {
                // Count members in progress (started but not completed)
                $inProgressCount = DB::table('member_apps_challenge_progress')
                    ->where('challenge_id', $challenge->id)
                    ->whereNotNull('started_at')
                    ->where('is_completed', false)
                    ->count();
                
                // Count members who completed
                $completedCount = DB::table('member_apps_challenge_progress')
                    ->where('challenge_id', $challenge->id)
                    ->where('is_completed', true)
                    ->count();
                
                // Count members who redeemed reward
                $redeemedCount = DB::table('member_apps_challenge_progress')
                    ->where('challenge_id', $challenge->id)
                    ->whereNotNull('reward_redeemed_at')
                    ->count();
                
                // Generate full URL for challenge image
                $imageUrl = null;
                if ($challenge->image) {
                    if (str_starts_with($challenge->image, 'http://') || str_starts_with($challenge->image, 'https://')) {
                        $imageUrl = $challenge->image;
                    } else {
                        $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($challenge->image, '/');
                    }
                }
                
                return [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description ?? '',
                    'image' => $imageUrl,
                    'points_reward' => $challenge->points_reward ?? 0,
                    'start_date' => $challenge->start_date ? Carbon::parse($challenge->start_date)->format('Y-m-d') : null,
                    'end_date' => $challenge->end_date ? Carbon::parse($challenge->end_date)->format('Y-m-d') : null,
                    'challenge_type_id' => $challenge->challenge_type_id,
                    'created_at' => $challenge->created_at ? Carbon::parse($challenge->created_at)->format('Y-m-d H:i:s') : null,
                    'stats' => [
                        'in_progress' => $inProgressCount,
                        'completed' => $completedCount,
                        'redeemed' => $redeemedCount,
                    ],
                ];
            });
            
            return $challengesWithStats->toArray();
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Error loading active challenges: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
    
    /**
     * Get active rewards with redemption count
     */
    private function getActiveRewards()
    {
        try {
            // Get all active rewards with item information
            $rewards = DB::table('member_apps_rewards as r')
                ->join('items as i', 'r.item_id', '=', 'i.id')
                ->where('r.is_active', true)
                ->select(
                    'r.id as reward_id',
                    'r.item_id',
                    'r.points_required',
                    'r.serial_code',
                    'i.name as item_name',
                    'i.type as item_type'
                )
                ->orderBy('r.id', 'asc')
                ->get();
            
            // Get redemption count and image for each reward
            $rewardsWithRedemption = $rewards->map(function($reward) {
                // Count redemptions by matching description that contains item name
                $redemptionCount = MemberAppsPointTransaction::where('transaction_type', 'redeem')
                    ->where('point_amount', '<', 0) // Negative for redemption
                    ->where('description', 'LIKE', '%Redeem reward: ' . $reward->item_name . '%')
                    ->count();
                
                // Get first image from item_images table
                $firstImage = DB::table('item_images')
                    ->where('item_id', $reward->item_id)
                    ->orderBy('id', 'asc')
                    ->first();
                
                $itemImage = null;
                if ($firstImage && isset($firstImage->path)) {
                    $itemImage = 'https://ymsofterp.com/storage/' . $firstImage->path;
                }
                
                return [
                    'id' => $reward->reward_id,
                    'item_id' => $reward->item_id,
                    'item_name' => $reward->item_name,
                    'item_image' => $itemImage,
                    'item_type' => $reward->item_type,
                    'points_required' => (int) $reward->points_required,
                    'points_required_formatted' => number_format($reward->points_required, 0, ',', '.'),
                    'serial_code' => $reward->serial_code,
                    'redemption_count' => $redemptionCount,
                    'redemption_count_formatted' => number_format($redemptionCount, 0, ',', '.'),
                ];
            })
            ->sortByDesc('redemption_count')
            ->values();
            
            return $rewardsWithRedemption->toArray();
        } catch (\Exception $e) {
            Log::error('CRM Dashboard: Error loading active rewards: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
}
