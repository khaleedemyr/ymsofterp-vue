<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk analisis behavior customer (Member & Non-Member)
 */
class CustomerBehaviorAnalysisService
{
    protected $aiHelper;

    public function __construct(AIDatabaseHelper $aiHelper)
    {
        $this->aiHelper = $aiHelper;
    }

    /**
     * Get comprehensive behavior analysis
     */
    public function getBehaviorAnalysis($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            // 1. Member vs Non-Member Overview
            $customerBehavior = $this->aiHelper->getCustomerBehaviorData($dateFrom, $dateTo, 'all', $outletCode);
            
            // 2. RFM Analysis untuk Members
            $rfmAnalysis = $this->getRFMSegmentation($dateFrom, $dateTo, $outletCode);
            
            // 3. Purchase Frequency Distribution
            $frequencyDistribution = [
                'member' => $this->aiHelper->getPurchaseFrequencyDistribution($dateFrom, $dateTo, 'member', $outletCode),
                'non_member' => $this->aiHelper->getPurchaseFrequencyDistribution($dateFrom, $dateTo, 'non_member', $outletCode)
            ];
            
            // 4. Time Pattern Analysis
            $timePatterns = [
                'member' => $this->getTimePatternSummary($dateFrom, $dateTo, 'member', $outletCode),
                'non_member' => $this->getTimePatternSummary($dateFrom, $dateTo, 'non_member', $outletCode)
            ];
            
            // 5. Product Preference
            $productPreferences = [
                'member' => $this->aiHelper->getProductPreferenceAnalysis($dateFrom, $dateTo, 'member', $outletCode, 10),
                'non_member' => $this->aiHelper->getProductPreferenceAnalysis($dateFrom, $dateTo, 'non_member', $outletCode, 10)
            ];
            
            // 6. Churn Risk Analysis
            $churnRisk = $this->aiHelper->getChurnRiskAnalysis(30, $outletCode);
            
            // 7. Member Engagement
            $engagement = $this->aiHelper->getMemberEngagementMetrics($dateFrom, $dateTo, $outletCode);
            
            // 8. Promo Analytics
            $promoAnalytics = $this->aiHelper->getPromoAnalytics($dateFrom, $dateTo, $outletCode);
            
            return [
                'customer_behavior' => $customerBehavior,
                'rfm_segmentation' => $rfmAnalysis,
                'frequency_distribution' => $frequencyDistribution,
                'time_patterns' => $timePatterns,
                'product_preferences' => $productPreferences,
                'churn_risk' => [
                    'at_risk_count' => count($churnRisk),
                    'at_risk_customers' => array_slice($churnRisk, 0, 20) // Top 20
                ],
                'member_engagement' => $engagement,
                'promo_analytics' => $promoAnalytics,
                'summary' => $this->generateSummary($customerBehavior, $rfmAnalysis, $engagement)
            ];
        } catch (\Exception $e) {
            Log::error('Customer Behavior Analysis Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get RFM Segmentation
     */
    public function getRFMSegmentation($dateFrom, $dateTo, $outletCode = null)
    {
        $rfmData = $this->aiHelper->getRFMAnalysis($dateFrom, $dateTo, $outletCode);
        
        if (empty($rfmData)) {
            return [
                'champions' => [],
                'loyal_customers' => [],
                'at_risk' => [],
                'lost_customers' => [],
                'summary' => []
            ];
        }
        
        // Calculate quartiles
        $recencies = array_column($rfmData, 'recency_days');
        $frequencies = array_column($rfmData, 'frequency');
        $monetary = array_column($rfmData, 'monetary_value');
        
        sort($recencies);
        sort($frequencies);
        sort($monetary);
        
        $rQ1 = $recencies[floor(count($recencies) * 0.25)];
        $rQ2 = $recencies[floor(count($recencies) * 0.5)];
        $rQ3 = $recencies[floor(count($recencies) * 0.75)];
        
        $fQ1 = $frequencies[floor(count($frequencies) * 0.25)];
        $fQ2 = $frequencies[floor(count($frequencies) * 0.5)];
        $fQ3 = $frequencies[floor(count($frequencies) * 0.75)];
        
        $mQ1 = $monetary[floor(count($monetary) * 0.25)];
        $mQ2 = $monetary[floor(count($monetary) * 0.5)];
        $mQ3 = $monetary[floor(count($monetary) * 0.75)];
        
        $champions = [];
        $loyalCustomers = [];
        $atRisk = [];
        $lostCustomers = [];
        
        foreach ($rfmData as $customer) {
            $rScore = 0;
            $fScore = 0;
            $mScore = 0;
            
            // Recency Score (lower is better)
            if ($customer->recency_days <= $rQ1) $rScore = 4;
            elseif ($customer->recency_days <= $rQ2) $rScore = 3;
            elseif ($customer->recency_days <= $rQ3) $rScore = 2;
            else $rScore = 1;
            
            // Frequency Score (higher is better)
            if ($customer->frequency >= $fQ3) $fScore = 4;
            elseif ($customer->frequency >= $fQ2) $fScore = 3;
            elseif ($customer->frequency >= $fQ1) $fScore = 2;
            else $fScore = 1;
            
            // Monetary Score (higher is better)
            if ($customer->monetary_value >= $mQ3) $mScore = 4;
            elseif ($customer->monetary_value >= $mQ2) $mScore = 3;
            elseif ($customer->monetary_value >= $mQ1) $mScore = 2;
            else $mScore = 1;
            
            $customer->r_score = $rScore;
            $customer->f_score = $fScore;
            $customer->m_score = $mScore;
            $customer->rfm_score = $rScore + $fScore + $mScore;
            
            // Segmentation
            if ($rScore >= 3 && $fScore >= 3 && $mScore >= 3) {
                $champions[] = $customer;
            } elseif ($rScore >= 2 && $fScore >= 3) {
                $loyalCustomers[] = $customer;
            } elseif ($rScore <= 2 && $fScore >= 2) {
                $atRisk[] = $customer;
            } elseif ($rScore <= 2 && $fScore <= 2) {
                $lostCustomers[] = $customer;
            }
        }
        
        return [
            'champions' => array_slice($champions, 0, 20),
            'loyal_customers' => array_slice($loyalCustomers, 0, 20),
            'at_risk' => array_slice($atRisk, 0, 20),
            'lost_customers' => array_slice($lostCustomers, 0, 20),
            'summary' => [
                'champions_count' => count($champions),
                'loyal_count' => count($loyalCustomers),
                'at_risk_count' => count($atRisk),
                'lost_count' => count($lostCustomers),
                'total_members' => count($rfmData)
            ]
        ];
    }

    /**
     * Get time pattern summary
     */
    private function getTimePatternSummary($dateFrom, $dateTo, $customerType, $outletCode = null)
    {
        $timePatterns = $this->aiHelper->getTimePatternAnalysis($dateFrom, $dateTo, $customerType, $outletCode);
        
        if (empty($timePatterns)) {
            return [
                'peak_hours' => [],
                'peak_days' => [],
                'peak_months' => []
            ];
        }
        
        // Group by hour
        $hourlyData = [];
        foreach ($timePatterns as $pattern) {
            if (!isset($hourlyData[$pattern->hour])) {
                $hourlyData[$pattern->hour] = [
                    'hour' => $pattern->hour,
                    'order_count' => 0,
                    'revenue' => 0,
                    'customers' => 0
                ];
            }
            $hourlyData[$pattern->hour]['order_count'] += $pattern->order_count;
            $hourlyData[$pattern->hour]['revenue'] += $pattern->revenue;
            $hourlyData[$pattern->hour]['customers'] += $pattern->unique_customers;
        }
        
        // Group by day
        $dailyData = [];
        foreach ($timePatterns as $pattern) {
            if (!isset($dailyData[$pattern->day_of_week])) {
                $dailyData[$pattern->day_of_week] = [
                    'day_name' => $pattern->day_name,
                    'day_of_week' => $pattern->day_of_week,
                    'order_count' => 0,
                    'revenue' => 0,
                    'customers' => 0
                ];
            }
            $dailyData[$pattern->day_of_week]['order_count'] += $pattern->order_count;
            $dailyData[$pattern->day_of_week]['revenue'] += $pattern->revenue;
            $dailyData[$pattern->day_of_week]['customers'] += $pattern->unique_customers;
        }
        
        // Sort and get top
        usort($hourlyData, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        usort($dailyData, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        return [
            'peak_hours' => array_slice($hourlyData, 0, 5),
            'peak_days' => array_slice($dailyData, 0, 7),
            'all_hours' => $hourlyData
        ];
    }

    /**
     * Generate summary insights
     */
    private function generateSummary($customerBehavior, $rfmAnalysis, $engagement)
    {
        $memberData = collect($customerBehavior)->firstWhere('customer_type', 'member');
        $nonMemberData = collect($customerBehavior)->firstWhere('customer_type', 'non_member');
        
        $summary = [
            'member_insights' => [],
            'non_member_insights' => [],
            'comparison' => []
        ];
        
        if ($memberData) {
            $summary['member_insights'] = [
                'total_orders' => $memberData->total_orders,
                'unique_customers' => $memberData->unique_customers,
                'total_revenue' => $memberData->total_revenue,
                'avg_order_value' => round($memberData->avg_order_value, 2),
                'avg_pax' => round($memberData->avg_pax, 2),
                'avg_discount' => round($memberData->avg_discount, 2),
                'revenue_per_customer' => $memberData->unique_customers > 0 
                    ? round($memberData->total_revenue / $memberData->unique_customers, 2) 
                    : 0
            ];
        }
        
        if ($nonMemberData) {
            $summary['non_member_insights'] = [
                'total_orders' => $nonMemberData->total_orders,
                'unique_customers' => $nonMemberData->unique_customers,
                'total_revenue' => $nonMemberData->total_revenue,
                'avg_order_value' => round($nonMemberData->avg_order_value, 2),
                'avg_pax' => round($nonMemberData->avg_pax, 2),
                'avg_discount' => round($nonMemberData->avg_discount, 2),
                'revenue_per_customer' => $nonMemberData->unique_customers > 0 
                    ? round($nonMemberData->total_revenue / $nonMemberData->unique_customers, 2) 
                    : 0
            ];
        }
        
        if ($memberData && $nonMemberData) {
            // Calculate overall average per pax = revenue / total pax
            $totalRevenue = ($memberData->total_revenue ?? 0) + ($nonMemberData->total_revenue ?? 0);
            $totalPax = ($memberData->total_pax ?? 0) + ($nonMemberData->total_pax ?? 0);
            $overallAvgPax = $totalPax > 0 ? round($totalRevenue / $totalPax, 2) : 0;
            
            $summary['comparison'] = [
                'member_has_higher_aov' => $memberData->avg_order_value > $nonMemberData->avg_order_value,
                'aov_difference' => round($memberData->avg_order_value - $nonMemberData->avg_order_value, 2),
                'member_revenue_share' => round(($memberData->total_revenue / ($memberData->total_revenue + $nonMemberData->total_revenue)) * 100, 2),
                'non_member_revenue_share' => round(($nonMemberData->total_revenue / ($memberData->total_revenue + $nonMemberData->total_revenue)) * 100, 2),
                'overall_avg_pax' => $overallAvgPax,
                'avg_pax_difference' => round($memberData->avg_pax - $nonMemberData->avg_pax, 2)
            ];
        }
        
        if ($rfmAnalysis && isset($rfmAnalysis['summary'])) {
            $summary['rfm_summary'] = $rfmAnalysis['summary'];
        }
        
        if ($engagement) {
            $summary['engagement_metrics'] = [
                'promo_usage_rate' => $engagement->total_orders > 0 
                    ? round(($engagement->promo_usage_count / $engagement->total_orders) * 100, 2) 
                    : 0,
                'voucher_usage_rate' => $engagement->total_orders > 0 
                    ? round(($engagement->voucher_usage_count / $engagement->total_orders) * 100, 2) 
                    : 0,
                'point_redemption_rate' => $engagement->total_orders > 0 
                    ? round(($engagement->point_redemption_count / $engagement->total_orders) * 100, 2) 
                    : 0
            ];
        }
        
        return $summary;
    }

    /**
     * Get detail customers untuk RFM segment tertentu dengan pagination, filter, dan sort
     */
    public function getRFMSegmentDetail(
        $segment, 
        $dateFrom, 
        $dateTo, 
        $outletCode = null,
        $perPage = 10,
        $page = 1,
        $search = '',
        $minRevenue = null,
        $maxRevenue = null,
        $minOrders = null,
        $maxOrders = null,
        $sortBy = 'total_revenue',
        $sortOrder = 'desc'
    )
    {
        try {
            // Get RFM data
            $rfmData = $this->aiHelper->getRFMAnalysis($dateFrom, $dateTo, $outletCode);
            
            if (empty($rfmData)) {
                return [];
            }
            
            // Calculate quartiles
            $recencies = array_column($rfmData, 'recency_days');
            $frequencies = array_column($rfmData, 'frequency');
            $monetary = array_column($rfmData, 'monetary_value');
            
            sort($recencies);
            sort($frequencies);
            sort($monetary);
            
            $rQ1 = $recencies[floor(count($recencies) * 0.25)] ?? 0;
            $rQ2 = $recencies[floor(count($recencies) * 0.5)] ?? 0;
            $rQ3 = $recencies[floor(count($recencies) * 0.75)] ?? 0;
            
            $fQ1 = $frequencies[floor(count($frequencies) * 0.25)] ?? 0;
            $fQ2 = $frequencies[floor(count($frequencies) * 0.5)] ?? 0;
            $fQ3 = $frequencies[floor(count($frequencies) * 0.75)] ?? 0;
            
            $mQ1 = $monetary[floor(count($monetary) * 0.25)] ?? 0;
            $mQ2 = $monetary[floor(count($monetary) * 0.5)] ?? 0;
            $mQ3 = $monetary[floor(count($monetary) * 0.75)] ?? 0;
            
            $segmentCustomers = [];
            
            foreach ($rfmData as $customer) {
                $rScore = 0;
                $fScore = 0;
                $mScore = 0;
                
                // Recency Score
                if ($customer->recency_days <= $rQ1) $rScore = 4;
                elseif ($customer->recency_days <= $rQ2) $rScore = 3;
                elseif ($customer->recency_days <= $rQ3) $rScore = 2;
                else $rScore = 1;
                
                // Frequency Score
                if ($customer->frequency >= $fQ3) $fScore = 4;
                elseif ($customer->frequency >= $fQ2) $fScore = 3;
                elseif ($customer->frequency >= $fQ1) $fScore = 2;
                else $fScore = 1;
                
                // Monetary Score
                if ($customer->monetary_value >= $mQ3) $mScore = 4;
                elseif ($customer->monetary_value >= $mQ2) $mScore = 3;
                elseif ($customer->monetary_value >= $mQ1) $mScore = 2;
                else $mScore = 1;
                
                $customer->r_score = $rScore;
                $customer->f_score = $fScore;
                $customer->m_score = $mScore;
                $customer->rfm_score = "R{$rScore}F{$fScore}M{$mScore}";
                
                // Check if customer belongs to requested segment
                // Logic harus SAMA PERSIS dengan getRFMSegmentation
                $belongsToSegment = false;
                
                // Logic harus SAMA PERSIS dengan getRFMSegmentation (urutan if-elseif penting!)
                // Di getRFMSegmentation, urutannya:
                // 1. Champions: r>=3 && f>=3 && m>=3
                // 2. Loyal: r>=2 && f>=3 (tapi bukan champions)
                // 3. At Risk: r<=2 && f>=2 (tapi bukan lost)
                // 4. Lost: r<=2 && f<=2
                
                switch (strtolower($segment)) {
                    case 'champions':
                        // Champions: r>=3 && f>=3 && m>=3
                        $belongsToSegment = ($rScore >= 3 && $fScore >= 3 && $mScore >= 3);
                        break;
                    case 'loyal':
                        // Loyal: r>=2 && f>=3, tapi BUKAN champions (m<3 atau r<3)
                        // Jadi: r>=2 && f>=3 && (r<3 || m<3)
                        $belongsToSegment = ($rScore >= 2 && $fScore >= 3 && !($rScore >= 3 && $fScore >= 3 && $mScore >= 3));
                        break;
                    case 'at_risk':
                        // At Risk: r<=2 && f>=2
                        // Di getRFMSegmentation: elseif ($rScore <= 2 && $fScore >= 2)
                        // Karena urutan if-elseif, customer dengan f=2 akan masuk at_risk dulu, bukan lost
                        // Jadi kita gunakan logika yang sama: r<=2 && f>=2
                        $belongsToSegment = ($rScore <= 2 && $fScore >= 2);
                        break;
                    case 'lost':
                        // Lost: r<=2 && f<=2
                        // Tapi karena urutan if-elseif, customer dengan f=2 akan masuk at_risk dulu
                        // Jadi lost adalah: r<=2 && f<=2 && f<2 (exclude f=2 karena sudah masuk at_risk)
                        // Atau lebih sederhana: r<=2 && f<2
                        $belongsToSegment = ($rScore <= 2 && $fScore < 2);
                        break;
                }
                
                if ($belongsToSegment) {
                    // Get member details
                    // member_id di orders bisa berupa ID (integer) atau member_id (string)
                    $memberId = $customer->member_id ?? null;
                    $memberInfo = null;
                    
                    if ($memberId) {
                        // Coba cari berdasarkan ID dulu (jika member_id adalah integer)
                        if (is_numeric($memberId)) {
                            $memberInfo = DB::table('member_apps_members')
                                ->where('id', $memberId)
                                ->first();
                        }
                        
                        // Jika tidak ketemu, coba cari berdasarkan member_id (string)
                        if (!$memberInfo) {
                            $memberInfo = DB::table('member_apps_members')
                                ->where('member_id', $memberId)
                                ->first();
                        }
                    }
                    
                    $segmentCustomers[] = [
                        'id' => $memberId,
                        'name' => $memberInfo->nama_lengkap ?? $memberInfo->name ?? 'N/A',
                        'phone' => $memberInfo->mobile_phone ?? $memberInfo->phone ?? 'N/A',
                        'total_orders' => $customer->frequency ?? 0,
                        'total_revenue' => $customer->monetary_value ?? 0,
                        'avg_order_value' => $customer->frequency > 0 ? ($customer->monetary_value / $customer->frequency) : 0,
                        'last_order_date' => $customer->last_purchase_date ? Carbon::parse($customer->last_purchase_date)->format('d/m/Y') : 'N/A',
                        'recency_days' => $customer->recency_days ?? 0,
                        'rfm_score' => $customer->rfm_score
                    ];
                }
            }
            
            // Apply filters
            $filteredCustomers = array_filter($segmentCustomers, function($customer) use ($search, $minRevenue, $maxRevenue, $minOrders, $maxOrders) {
                // Search filter
                if (!empty($search)) {
                    $searchLower = strtolower($search);
                    $nameMatch = strpos(strtolower($customer['name'] ?? ''), $searchLower) !== false;
                    $phoneMatch = strpos(strtolower($customer['phone'] ?? ''), $searchLower) !== false;
                    if (!$nameMatch && !$phoneMatch) {
                        return false;
                    }
                }
                
                // Revenue filter
                if ($minRevenue !== null && $customer['total_revenue'] < $minRevenue) {
                    return false;
                }
                if ($maxRevenue !== null && $customer['total_revenue'] > $maxRevenue) {
                    return false;
                }
                
                // Orders filter
                if ($minOrders !== null && $customer['total_orders'] < $minOrders) {
                    return false;
                }
                if ($maxOrders !== null && $customer['total_orders'] > $maxOrders) {
                    return false;
                }
                
                return true;
            });
            
            // Sort
            usort($filteredCustomers, function($a, $b) use ($sortBy, $sortOrder) {
                $valueA = $a[$sortBy] ?? 0;
                $valueB = $b[$sortBy] ?? 0;
                
                // Handle date sorting
                if ($sortBy === 'last_order_date') {
                    $valueA = $valueA === 'N/A' ? 0 : strtotime(str_replace('/', '-', $valueA));
                    $valueB = $valueB === 'N/A' ? 0 : strtotime(str_replace('/', '-', $valueB));
                }
                
                if ($sortOrder === 'asc') {
                    return $valueA <=> $valueB;
                } else {
                    return $valueB <=> $valueA;
                }
            });
            
            // Pagination
            $total = count($filteredCustomers);
            $totalPages = ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;
            $paginatedData = array_slice($filteredCustomers, $offset, $perPage);
            
            return [
                'data' => array_values($paginatedData),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('RFM Segment Detail Error: ' . $e->getMessage());
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0,
                    'from' => 0,
                    'to' => 0
                ]
            ];
        }
    }
}
