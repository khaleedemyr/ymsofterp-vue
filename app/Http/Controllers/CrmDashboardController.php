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
     * Display the CRM dashboard with new member structure
     */
    public function index(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $stats = $this->getStats();
        $memberGrowth = $this->getMemberGrowth();
        $tierDistribution = $this->getTierDistribution();
        $genderDistribution = $this->getGenderDistribution();
        $ageDistribution = $this->getAgeDistribution();
        $purchasingPowerByAge = $this->getPurchasingPowerByAge($startDate, $endDate);
        $spendingTrend = $this->getSpendingTrend($startDate, $endDate);
        $pointActivityTrend = $this->getPointActivityTrend($startDate, $endDate);
        $latestMembers = $this->getLatestMembers();
        $latestPointTransactions = $this->getLatestPointTransactions();
        $latestActivities = $this->getLatestActivities();
        $topSpenders = $this->getTopSpenders();
        $mostActiveMembers = $this->getMostActiveMembers();
        $pointStats = $this->getPointStats($startDate, $endDate);
        $engagementMetrics = $this->getEngagementMetrics();
        
        // Priority 3 features
        $memberSegmentation = $this->getMemberSegmentation();
        $memberLifetimeValue = $this->getMemberLifetimeValue();
        $churnAnalysis = $this->getChurnAnalysis();
        $conversionFunnel = $this->getConversionFunnel();
        $regionalBreakdown = $this->getRegionalBreakdown();
        $comparisonData = $this->getComparisonData($startDate, $endDate);

        return Inertia::render('Crm/Dashboard', [
            'stats' => $stats,
            'memberGrowth' => $memberGrowth,
            'tierDistribution' => $tierDistribution,
            'genderDistribution' => $genderDistribution,
            'ageDistribution' => $ageDistribution,
            'purchasingPowerByAge' => $purchasingPowerByAge,
            'spendingTrend' => $spendingTrend,
            'pointActivityTrend' => $pointActivityTrend,
            'latestMembers' => $latestMembers,
            'latestPointTransactions' => $latestPointTransactions,
            'latestActivities' => $latestActivities,
            'topSpenders' => $topSpenders,
            'mostActiveMembers' => $mostActiveMembers,
            'pointStats' => $pointStats,
            'engagementMetrics' => $engagementMetrics,
            'memberSegmentation' => $memberSegmentation,
            'memberLifetimeValue' => $memberLifetimeValue,
            'churnAnalysis' => $churnAnalysis,
            'conversionFunnel' => $conversionFunnel,
            'regionalBreakdown' => $regionalBreakdown,
            'comparisonData' => $comparisonData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Get comprehensive dashboard statistics
     */
    private function getStats()
    {
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

        // Tier breakdown (new structure: silver, gold, platinum)
        $tierBreakdown = [
            'silver' => MemberAppsMember::where(function($query) {
                $query->where('member_level', 'silver')
                      ->orWhereNull('member_level')
                      ->orWhere('member_level', '');
            })->count(),
            'gold' => MemberAppsMember::where('member_level', 'gold')->count(),
            'platinum' => MemberAppsMember::where('member_level', 'platinum')->count(),
        ];

        // Point statistics
        $totalPointBalance = MemberAppsMember::sum('just_points');
        $membersWithPoints = MemberAppsMember::where('just_points', '>', 0)->count();
        $membersWithoutPoints = MemberAppsMember::where('just_points', '<=', 0)->count();

        // Spending statistics
        $totalSpending = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->sum('grand_total');

        $spendingLastYear = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->where('created_at', '>=', Carbon::now()->subYear())
            ->sum('grand_total');

        $spendingThisMonth = DB::connection('db_justus')
            ->table('orders')
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->whereBetween('created_at', [$thisMonth, Carbon::now()])
            ->sum('grand_total');

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
            'totalSpending' => $totalSpending,
            'totalSpendingFormatted' => 'Rp ' . number_format($totalSpending, 0, ',', '.'),
            'spendingLastYear' => $spendingLastYear,
            'spendingLastYearFormatted' => 'Rp ' . number_format($spendingLastYear, 0, ',', '.'),
            'spendingThisMonth' => $spendingThisMonth,
            'spendingThisMonthFormatted' => 'Rp ' . number_format($spendingThisMonth, 0, ',', '.'),
            'growthRate' => round($growthRate, 2),
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
                'monthShort' => $date->format('M'),
                'newMembers' => $newMembers,
                'totalMembers' => $totalMembers,
            ];
        }

        return $data;
    }

    /**
     * Get tier distribution
     */
    private function getTierDistribution()
    {
        $silver = MemberAppsMember::where(function($query) {
            $query->where('member_level', 'silver')
                  ->orWhereNull('member_level')
                  ->orWhere('member_level', '');
        })->count();
        
        $gold = MemberAppsMember::where('member_level', 'gold')->count();
        $platinum = MemberAppsMember::where('member_level', 'platinum')->count();
        
        $total = $silver + $gold + $platinum;
        
        return [
            ['tier' => 'Silver', 'count' => $silver, 'percentage' => $total > 0 ? round(($silver / $total) * 100, 1) : 0, 'color' => '#94a3b8'],
            ['tier' => 'Gold', 'count' => $gold, 'percentage' => $total > 0 ? round(($gold / $total) * 100, 1) : 0, 'color' => '#fbbf24'],
            ['tier' => 'Platinum', 'count' => $platinum, 'percentage' => $total > 0 ? round(($platinum / $total) * 100, 1) : 0, 'color' => '#a78bfa'],
        ];
    }

    /**
     * Get gender distribution
     */
    private function getGenderDistribution()
    {
        $distribution = MemberAppsMember::selectRaw('jenis_kelamin, COUNT(*) as count')
            ->groupBy('jenis_kelamin')
            ->get()
            ->map(function ($item) {
                $label = $item->jenis_kelamin === 'L' ? 'Laki-laki' : ($item->jenis_kelamin === 'P' ? 'Perempuan' : 'Tidak Diketahui');
                return [
                    'gender' => $label,
                    'count' => $item->count,
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
     * Get age distribution
     */
    private function getAgeDistribution()
    {
        $distribution = MemberAppsMember::selectRaw('
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
            
            return [
                'age_group' => $item->age_group,
                'count' => $item->count,
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
     * Get purchasing power by age group
     */
    private function getPurchasingPowerByAge($startDate = null, $endDate = null)
    {
        $dateFilter = '';
        if ($startDate && $endDate) {
            $startDateEscaped = DB::connection()->getPdo()->quote($startDate);
            $endDateEscaped = DB::connection()->getPdo()->quote($endDate);
            $dateFilter = "AND o.created_at BETWEEN {$startDateEscaped} AND {$endDateEscaped}";
        } else {
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
                AND o.status = 'paid'
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

        return collect(DB::select($sql))
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
    }

    /**
     * Get spending trend (last 12 months)
     */
    private function getSpendingTrend($startDate = null, $endDate = null)
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $query = DB::connection('db_justus')
                ->table('orders')
                ->where('status', 'paid')
                ->whereNotNull('member_id')
                ->where('member_id', '!=', '')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [max($startDate, $startOfMonth), min($endDate, $endOfMonth)]);
            }

            $totalSpending = $query->sum('grand_total');
            $transactionCount = $query->count();
            $avgSpending = $transactionCount > 0 ? $totalSpending / $transactionCount : 0;

            $data[] = [
                'month' => $date->format('M Y'),
                'monthShort' => $date->format('M'),
                'totalSpending' => (float) $totalSpending,
                'transactionCount' => $transactionCount,
                'avgSpending' => (float) $avgSpending,
            ];
        }

        return $data;
    }

    /**
     * Get point activity trend (last 12 months)
     */
    private function getPointActivityTrend($startDate = null, $endDate = null)
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $query = MemberAppsPointTransaction::whereBetween('created_at', [$startOfMonth, $endOfMonth]);
            
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [max($startDate, $startOfMonth), min($endDate, $endOfMonth)]);
            }

            $pointEarned = (clone $query)->where('point_amount', '>', 0)->sum('point_amount');
            $pointRedeemed = abs((clone $query)->where('point_amount', '<', 0)->sum('point_amount'));
            $netPoint = $pointEarned - $pointRedeemed;

            $data[] = [
                'month' => $date->format('M Y'),
                'monthShort' => $date->format('M'),
                'pointEarned' => (float) $pointEarned,
                'pointRedeemed' => (float) $pointRedeemed,
                'netPoint' => (float) $netPoint,
            ];
        }

        return $data;
    }

    /**
     * Get latest members (10)
     */
    private function getLatestMembers()
    {
        return MemberAppsMember::select([
            'id', 'member_id', 'nama_lengkap', 'email', 'mobile_phone',
            'member_level', 'just_points', 'is_active', 'email_verified_at', 'created_at'
        ])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get()
        ->map(function ($member) {
            // Get total spending
            $totalSpending = DB::connection('db_justus')
                ->table('orders')
                ->where('member_id', $member->member_id)
                ->where('status', 'paid')
                ->sum('grand_total');

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
                'totalSpending' => (float) $totalSpending,
                'totalSpendingFormatted' => 'Rp ' . number_format($totalSpending, 0, ',', '.'),
                'isActive' => $member->is_active,
                'emailVerified' => !is_null($member->email_verified_at),
                'createdAt' => $member->created_at->format('d M Y'),
                'createdAtFull' => $member->created_at->format('d M Y, H:i'),
            ];
        });
    }

    /**
     * Get latest point transactions (10)
     */
    private function getLatestPointTransactions()
    {
        $transactions = MemberAppsPointTransaction::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        $memberIds = $transactions->pluck('member_id')->unique()->filter()->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        
        return $transactions->map(function ($pt) use ($members) {
            $member = $members->get($pt->member_id);
            $isEarned = $pt->point_amount > 0;
            
            return [
                'id' => $pt->id,
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'memberId' => $member->member_id ?? '-',
                'type' => $isEarned ? 'earned' : 'redeemed',
                'typeText' => $isEarned ? 'EARNED' : 'REDEEMED',
                'pointAmount' => abs($pt->point_amount),
                'pointAmountFormatted' => number_format(abs($pt->point_amount), 0, ',', '.'),
                'transactionValue' => $pt->transaction_amount ?? 0,
                'transactionValueFormatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                'createdAt' => $pt->created_at->format('d M Y, H:i'),
            ];
        });
    }

    /**
     * Get latest activities (combining all activity types)
     */
    private function getLatestActivities()
    {
        $activities = collect();

        // Recent registrations
        $recentRegistrations = MemberAppsMember::where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'type' => 'registration',
                    'memberName' => $member->nama_lengkap,
                    'memberId' => $member->member_id,
                    'description' => 'Member baru mendaftar',
                    'icon' => 'fa-user-plus',
                    'color' => 'text-emerald-500',
                    'bgColor' => 'bg-emerald-50',
                    'createdAt' => $member->created_at,
                ];
            });

        $activities = $activities->concat($recentRegistrations);

        // Recent point transactions
        $recentPointTransactions = MemberAppsPointTransaction::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        $memberIds = $recentPointTransactions->pluck('member_id')->unique()->filter()->toArray();
        $members = MemberAppsMember::whereIn('id', $memberIds)->get()->keyBy('id');
        
        $recentPointTransactions = $recentPointTransactions->map(function ($pt) use ($members) {
            $member = $members->get($pt->member_id);
            $isEarned = $pt->point_amount > 0;
            
            return [
                'id' => $pt->id,
                'type' => $isEarned ? 'point_earned' : 'point_redeemed',
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'memberId' => $member->member_id ?? '-',
                'description' => ($isEarned ? 'Memperoleh' : 'Menukar') . ' ' . number_format(abs($pt->point_amount), 0, ',', '.') . ' point',
                'icon' => $isEarned ? 'fa-plus-circle' : 'fa-minus-circle',
                'color' => $isEarned ? 'text-blue-500' : 'text-orange-500',
                'bgColor' => $isEarned ? 'bg-blue-50' : 'bg-orange-50',
                'pointAmount' => abs($pt->point_amount),
                'createdAt' => $pt->created_at,
            ];
        });

        $activities = $activities->concat($recentPointTransactions);

        // Sort and take latest 10
        return $activities->sortByDesc('createdAt')
            ->take(10)
            ->map(function ($activity) {
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
                    'createdAt' => $activity['createdAt']->format('d M Y, H:i'),
                    'createdAtFull' => $activity['createdAt']->format('Y-m-d H:i:s'),
                ];
            })
            ->values();
    }

    /**
     * Get top spenders (10)
     */
    private function getTopSpenders()
    {
        $topSpenders = DB::connection('db_justus')
            ->table('orders')
            ->select('member_id', DB::raw('SUM(grand_total) as total_spending'), DB::raw('COUNT(*) as order_count'))
            ->where('status', 'paid')
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->groupBy('member_id')
            ->orderBy('total_spending', 'desc')
            ->limit(10)
            ->get();

        $memberIds = $topSpenders->pluck('member_id')->toArray();
        $members = MemberAppsMember::whereIn('member_id', $memberIds)->get()->keyBy('member_id');

        return $topSpenders->map(function ($spender) use ($members) {
            $member = $members->get($spender->member_id);
            
            // Get last spending
            $lastOrder = DB::connection('db_justus')
                ->table('orders')
                ->where('member_id', $spender->member_id)
                ->where('status', 'paid')
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'memberId' => $spender->member_id,
                'memberName' => $member->nama_lengkap ?? 'Member Tidak Diketahui',
                'totalSpending' => (float) $spender->total_spending,
                'totalSpendingFormatted' => 'Rp ' . number_format($spender->total_spending, 0, ',', '.'),
                'orderCount' => $spender->order_count,
                'avgOrderValue' => $spender->order_count > 0 ? (float) ($spender->total_spending / $spender->order_count) : 0,
                'avgOrderValueFormatted' => $spender->order_count > 0 ? 'Rp ' . number_format($spender->total_spending / $spender->order_count, 0, ',', '.') : 'Rp 0',
                'lastSpending' => $lastOrder ? (float) $lastOrder->grand_total : 0,
                'lastSpendingFormatted' => $lastOrder ? 'Rp ' . number_format($lastOrder->grand_total, 0, ',', '.') : 'Rp 0',
                'lastSpendingDate' => $lastOrder ? Carbon::parse($lastOrder->created_at)->format('d M Y') : '-',
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

        return $activeMembers->map(function ($active) use ($members) {
            $member = $members->get($active->member_id);
            
            // Get order count
            $orderCount = DB::connection('db_justus')
                ->table('orders')
                ->where('member_id', $member->member_id ?? '')
                ->where('status', 'paid')
                ->count();

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
     * Get member segmentation (Priority 3)
     */
    private function getMemberSegmentation()
    {
        $last30Days = Carbon::now()->subDays(30);
        $last90Days = Carbon::now()->subDays(90);
        
        // VIP Members: High spending, active, high tier
        $vipMembers = MemberAppsMember::where('is_active', true)
            ->where(function($query) {
                $query->where('member_level', 'platinum')
                      ->orWhere('member_level', 'gold');
            })
            ->where('just_points', '>', 1000)
            ->count();
        
        // Active Members: Regular transactions in last 30 days
        $activeMemberIds = MemberAppsPointTransaction::where('created_at', '>=', $last30Days)
            ->distinct('member_id')
            ->pluck('member_id')
            ->toArray();
        $activeMembers = MemberAppsMember::whereIn('id', $activeMemberIds)
            ->where('is_active', true)
            ->count();
        
        // Dormant Members: No activity in last 90 days
        $dormantMembers = MemberAppsMember::where(function($query) use ($last90Days) {
            $query->whereNull('last_login_at')
                  ->orWhere('last_login_at', '<', $last90Days);
        })->where('is_active', true)->count();
        
        // New Members: Registered in last 30 days
        $newMembers = MemberAppsMember::where('created_at', '>=', $last30Days)->count();
        
        // At Risk: Active but low engagement
        $atRiskMembers = MemberAppsMember::where('is_active', true)
            ->where('just_points', '<=', 100)
            ->where(function($query) use ($last30Days) {
                $query->whereNull('last_login_at')
                      ->orWhere('last_login_at', '<', $last30Days);
            })
            ->count();
        
        return [
            'vip' => $vipMembers,
            'active' => $activeMembers,
            'dormant' => $dormantMembers,
            'new' => $newMembers,
            'atRisk' => $atRiskMembers,
        ];
    }
    
    /**
     * Get member lifetime value (Priority 3)
     */
    private function getMemberLifetimeValue()
    {
        $members = MemberAppsMember::where('is_active', true)->get();
        
        $totalLTV = 0;
        $memberCount = 0;
        $ltvByTier = [
            'silver' => ['total' => 0, 'count' => 0],
            'gold' => ['total' => 0, 'count' => 0],
            'platinum' => ['total' => 0, 'count' => 0],
        ];
        
        foreach ($members as $member) {
            $spending = DB::connection('db_justus')
                ->table('orders')
                ->where('member_id', $member->member_id)
                ->where('status', 'paid')
                ->sum('grand_total');
            
            if ($spending > 0) {
                $totalLTV += $spending;
                $memberCount++;
                
                $tier = $member->member_level ?? 'silver';
                if (isset($ltvByTier[$tier])) {
                    $ltvByTier[$tier]['total'] += $spending;
                    $ltvByTier[$tier]['count']++;
                }
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
     * Get regional breakdown (Priority 3)
     */
    private function getRegionalBreakdown()
    {
        // Get members with region data (assuming there's a region field or join with outlets)
        $members = MemberAppsMember::select('id', 'member_id', 'is_active', 'member_level', 'just_points')
            ->where('is_active', true)
            ->get();
        
        // Get orders grouped by outlet/region
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
}
