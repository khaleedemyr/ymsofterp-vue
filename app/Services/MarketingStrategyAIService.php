<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Service untuk generate marketing strategy suggestions menggunakan AI
 * Menggunakan pattern yang sama dengan SalesOutletDashboard AI
 */
class MarketingStrategyAIService
{
    protected $behaviorService;
    protected $aiHelper;

    public function __construct(
        CustomerBehaviorAnalysisService $behaviorService,
        AIDatabaseHelper $aiHelper
    ) {
        $this->behaviorService = $behaviorService;
        $this->aiHelper = $aiHelper;
    }

    /**
     * Generate comprehensive marketing strategies berdasarkan behavior analysis
     */
    public function generateStrategies($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            // Get behavior analysis data
            $behaviorData = $this->behaviorService->getBehaviorAnalysis($dateFrom, $dateTo, $outletCode);
            
            // Prepare data untuk AI
            $aiPrompt = $this->prepareAIPrompt($behaviorData, $dateFrom, $dateTo);
            
            // Generate strategies menggunakan AI
            $strategies = $this->callAIForStrategies($aiPrompt);
            
            // Structure response
            return [
                'strategies' => $strategies,
                'data_summary' => $this->getDataSummary($behaviorData),
                'priority_actions' => $this->getPriorityActions($behaviorData, $strategies),
                'campaign_suggestions' => $this->generateCampaignSuggestions($behaviorData),
                'target_segments' => $this->getTargetSegments($behaviorData)
            ];
        } catch (\Exception $e) {
            Log::error('Marketing Strategy AI Service Error: ' . $e->getMessage());
            
            // Fallback: Generate basic strategies tanpa AI
            return $this->generateBasicStrategies($dateFrom, $dateTo, $outletCode);
        }
    }

    /**
     * Prepare AI prompt dari behavior data
     */
    private function prepareAIPrompt($behaviorData, $dateFrom, $dateTo)
    {
        $summary = $behaviorData['summary'] ?? [];
        $rfmSummary = $behaviorData['rfm_segmentation']['summary'] ?? [];
        $engagement = $behaviorData['member_engagement'] ?? null;
        
        $prompt = "Sebagai ahli marketing digital, analisis data customer behavior berikut dan berikan strategi marketing yang actionable:\n\n";
        
        $prompt .= "PERIODE ANALISIS: {$dateFrom} hingga {$dateTo}\n\n";
        
        // Member vs Non-Member
        if (isset($summary['member_insights']) && isset($summary['non_member_insights'])) {
            $member = $summary['member_insights'];
            $nonMember = $summary['non_member_insights'];
            
            $prompt .= "MEMBER:\n";
            $prompt .= "- Total Orders: {$member['total_orders']}\n";
            $prompt .= "- Unique Customers: {$member['unique_customers']}\n";
            $prompt .= "- Total Revenue: Rp " . number_format($member['total_revenue'], 0, ',', '.') . "\n";
            $prompt .= "- Avg Order Value: Rp " . number_format($member['avg_order_value'], 0, ',', '.') . "\n";
            $prompt .= "- Revenue per Customer: Rp " . number_format($member['revenue_per_customer'], 0, ',', '.') . "\n\n";
            
            $prompt .= "NON-MEMBER:\n";
            $prompt .= "- Total Orders: {$nonMember['total_orders']}\n";
            $prompt .= "- Unique Customers: {$nonMember['unique_customers']}\n";
            $prompt .= "- Total Revenue: Rp " . number_format($nonMember['total_revenue'], 0, ',', '.') . "\n";
            $prompt .= "- Avg Order Value: Rp " . number_format($nonMember['avg_order_value'], 0, ',', '.') . "\n";
            $prompt .= "- Revenue per Customer: Rp " . number_format($nonMember['revenue_per_customer'], 0, ',', '.') . "\n\n";
            
            if (isset($summary['comparison'])) {
                $comp = $summary['comparison'];
                $prompt .= "PERBANDINGAN:\n";
                $prompt .= "- Member Revenue Share: {$comp['member_revenue_share']}%\n";
                $prompt .= "- Non-Member Revenue Share: {$comp['non_member_revenue_share']}%\n";
                $prompt .= "- AOV Difference: Rp " . number_format($comp['aov_difference'], 0, ',', '.') . "\n\n";
            }
        }
        
        // RFM Segmentation
        if (!empty($rfmSummary)) {
            $prompt .= "RFM SEGMENTATION:\n";
            $prompt .= "- Champions: {$rfmSummary['champions_count']}\n";
            $prompt .= "- Loyal Customers: {$rfmSummary['loyal_count']}\n";
            $prompt .= "- At Risk: {$rfmSummary['at_risk_count']}\n";
            $prompt .= "- Lost Customers: {$rfmSummary['lost_count']}\n\n";
        }
        
        // Engagement
        if ($engagement && isset($summary['engagement_metrics'])) {
            $eng = $summary['engagement_metrics'];
            $prompt .= "ENGAGEMENT METRICS:\n";
            $prompt .= "- Promo Usage Rate: {$eng['promo_usage_rate']}%\n";
            $prompt .= "- Voucher Usage Rate: {$eng['voucher_usage_rate']}%\n";
            $prompt .= "- Point Redemption Rate: {$eng['point_redemption_rate']}%\n\n";
        }
        
        // Churn Risk
        if (isset($behaviorData['churn_risk']['at_risk_count'])) {
            $prompt .= "CHURN RISK:\n";
            $prompt .= "- At Risk Customers: {$behaviorData['churn_risk']['at_risk_count']}\n\n";
        }
        
        $prompt .= "BERIKAN STRATEGI MARKETING YANG:\n";
        $prompt .= "1. Spesifik untuk meningkatkan omzet\n";
        $prompt .= "2. Actionable dan bisa langsung diimplementasikan\n";
        $prompt .= "3. Terfokus pada konversi non-member menjadi member\n";
        $prompt .= "4. Retensi member yang at-risk\n";
        $prompt .= "5. Re-engagement untuk lost customers\n";
        $prompt .= "6. Maksimalkan champions dan loyal customers\n\n";
        
        $prompt .= "Format output dalam JSON dengan struktur:\n";
        $prompt .= "{\n";
        $prompt .= "  \"strategies\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"title\": \"Judul Strategi\",\n";
        $prompt .= "      \"description\": \"Deskripsi lengkap\",\n";
        $prompt .= "      \"target_segment\": \"member/non_member/champions/at_risk/lost\",\n";
        $prompt .= "      \"priority\": \"high/medium/low\",\n";
        $prompt .= "      \"expected_impact\": \"Estimasi peningkatan omzet\",\n";
        $prompt .= "      \"action_items\": [\"Action 1\", \"Action 2\"],\n";
        $prompt .= "      \"timeline\": \"Waktu implementasi\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}";
        
        return $prompt;
    }

    /**
     * Call AI service untuk generate strategies
     * Adapt sesuai dengan AI service yang digunakan di project
     */
    private function callAIForStrategies($prompt)
    {
        try {
            // TODO: Integrate dengan AI service yang sudah ada di project
            // Contoh: OpenAI API, atau custom AI service
            
            // Untuk sekarang, return basic strategies
            // Nanti bisa diintegrasikan dengan AI service yang ada
            return $this->generateBasicStrategiesFromData($prompt);
        } catch (\Exception $e) {
            Log::error('AI Strategy Generation Error: ' . $e->getMessage());
            return $this->generateBasicStrategiesFromData($prompt);
        }
    }

    /**
     * Generate basic strategies dari data (fallback atau tanpa AI)
     */
    private function generateBasicStrategiesFromData($prompt)
    {
        // Parse data dari prompt untuk generate strategies
        // Ini adalah fallback jika AI service tidak available
        
        $strategies = [];
        
        // Strategy 1: Convert Non-Member to Member
        $strategies[] = [
            'title' => 'Program Konversi Non-Member ke Member',
            'description' => 'Tingkatkan konversi non-member menjadi member dengan offer khusus registrasi',
            'target_segment' => 'non_member',
            'priority' => 'high',
            'expected_impact' => 'Meningkatkan customer lifetime value dan repeat purchase',
            'action_items' => [
                'Buat promo "Daftar Member, Dapat Diskon 20%"',
                'Push notification saat checkout untuk non-member',
                'Staff training untuk encourage member registration',
                'Track conversion rate dari non-member ke member'
            ],
            'timeline' => '2-4 minggu'
        ];
        
        // Strategy 2: Retain At-Risk Members
        $strategies[] = [
            'title' => 'Re-engagement Campaign untuk At-Risk Members',
            'description' => 'Target member yang jarang belanja dengan personalized offers',
            'target_segment' => 'at_risk',
            'priority' => 'high',
            'expected_impact' => 'Mengurangi churn rate dan meningkatkan retention',
            'action_items' => [
                'Email/SMS campaign dengan promo khusus',
                'Point bonus untuk transaksi berikutnya',
                'Birthday promo untuk at-risk members',
                'Survey untuk understand why mereka jarang belanja'
            ],
            'timeline' => '1-2 minggu'
        ];
        
        // Strategy 3: Maximize Champions
        $strategies[] = [
            'title' => 'VIP Program untuk Champions',
            'description' => 'Reward champions dengan exclusive benefits dan early access',
            'target_segment' => 'champions',
            'priority' => 'medium',
            'expected_impact' => 'Meningkatkan loyalty dan word-of-mouth',
            'action_items' => [
                'VIP tier dengan benefits khusus',
                'Exclusive events untuk champions',
                'Referral program dengan rewards lebih besar',
                'Early access untuk menu baru atau promo'
            ],
            'timeline' => '4-6 minggu'
        ];
        
        // Strategy 4: Win Back Lost Customers
        $strategies[] = [
            'title' => 'Win-Back Campaign untuk Lost Customers',
            'description' => 'Re-engage customers yang sudah lama tidak belanja',
            'target_segment' => 'lost',
            'priority' => 'medium',
            'expected_impact' => 'Recover revenue dari lost customers',
            'action_items' => [
                'Special comeback promo (misal: "Kami Rindu Anda - Diskon 30%")',
                'Survey untuk understand alasan mereka berhenti',
                'Personalized message dari management',
                'Limited time offer untuk first visit kembali'
            ],
            'timeline' => '2-3 minggu'
        ];
        
        // Strategy 5: Increase AOV
        $strategies[] = [
            'title' => 'Upselling & Cross-selling Strategy',
            'description' => 'Tingkatkan average order value dengan bundle deals dan recommendations',
            'target_segment' => 'all',
            'priority' => 'high',
            'expected_impact' => 'Meningkatkan revenue per transaction',
            'action_items' => [
                'Bundle deals untuk item yang sering dibeli bersamaan',
                'Staff training untuk upselling',
                'Digital menu dengan recommendations',
                'Add-on suggestions saat checkout'
            ],
            'timeline' => '2-4 minggu'
        ];
        
        return $strategies;
    }

    /**
     * Generate basic strategies (fallback)
     */
    private function generateBasicStrategies($dateFrom, $dateTo, $outletCode = null)
    {
        $behaviorData = $this->behaviorService->getBehaviorAnalysis($dateFrom, $dateTo, $outletCode);
        $prompt = $this->prepareAIPrompt($behaviorData, $dateFrom, $dateTo);
        return [
            'strategies' => $this->generateBasicStrategiesFromData($prompt),
            'data_summary' => $this->getDataSummary($behaviorData),
            'priority_actions' => $this->getPriorityActions($behaviorData, []),
            'campaign_suggestions' => $this->generateCampaignSuggestions($behaviorData),
            'target_segments' => $this->getTargetSegments($behaviorData)
        ];
    }

    /**
     * Get data summary untuk quick reference
     */
    private function getDataSummary($behaviorData)
    {
        $summary = $behaviorData['summary'] ?? [];
        return [
            'member_revenue' => $summary['member_insights']['total_revenue'] ?? 0,
            'non_member_revenue' => $summary['non_member_insights']['total_revenue'] ?? 0,
            'member_count' => $summary['member_insights']['unique_customers'] ?? 0,
            'non_member_count' => $summary['non_member_insights']['unique_customers'] ?? 0,
            'at_risk_count' => $behaviorData['churn_risk']['at_risk_count'] ?? 0
        ];
    }

    /**
     * Get priority actions berdasarkan data
     */
    private function getPriorityActions($behaviorData, $strategies)
    {
        $actions = [];
        $summary = $behaviorData['summary'] ?? [];
        
        // Compare member vs non-member
        if (isset($summary['comparison'])) {
            $comp = $summary['comparison'];
            if ($comp['non_member_revenue_share'] > 50) {
                $actions[] = [
                    'action' => 'Fokus konversi non-member ke member',
                    'reason' => 'Non-member contribute lebih dari 50% revenue',
                    'priority' => 'high'
                ];
            }
        }
        
        // Churn risk
        if (($behaviorData['churn_risk']['at_risk_count'] ?? 0) > 100) {
            $actions[] = [
                'action' => 'Immediate re-engagement campaign untuk at-risk members',
                'reason' => 'Lebih dari 100 members berisiko churn',
                'priority' => 'high'
                ];
        }
        
        // Engagement
        if (isset($summary['engagement_metrics'])) {
            $eng = $summary['engagement_metrics'];
            if ($eng['promo_usage_rate'] < 20) {
                $actions[] = [
                    'action' => 'Tingkatkan awareness promo/voucher',
                    'reason' => 'Promo usage rate rendah (' . $eng['promo_usage_rate'] . '%)',
                    'priority' => 'medium'
                ];
            }
        }
        
        return $actions;
    }

    /**
     * Generate campaign suggestions
     */
    private function generateCampaignSuggestions($behaviorData)
    {
        $suggestions = [];
        $summary = $behaviorData['summary'] ?? [];
        $timePatterns = $behaviorData['time_patterns'] ?? [];
        
        // Time-based campaigns
        if (isset($timePatterns['member']['peak_hours'])) {
            $peakHours = $timePatterns['member']['peak_hours'];
            if (!empty($peakHours)) {
                $suggestions[] = [
                    'type' => 'time_based',
                    'name' => 'Off-Peak Hour Promo',
                    'description' => 'Promo untuk jam-jam sepi untuk meningkatkan traffic',
                    'target_hours' => array_column($peakHours, 'hour')
                ];
            }
        }
        
        // Segment-based campaigns
        $rfmSummary = $behaviorData['rfm_segmentation']['summary'] ?? [];
        if (isset($rfmSummary['at_risk_count']) && $rfmSummary['at_risk_count'] > 0) {
            $suggestions[] = [
                'type' => 'segment_based',
                'name' => 'Comeback Campaign',
                'description' => 'Special promo untuk at-risk members',
                'target_segment' => 'at_risk',
                'offer' => 'Diskon 25% + Bonus Point'
            ];
        }
        
        return $suggestions;
    }

    /**
     * Get target segments dengan details
     */
    private function getTargetSegments($behaviorData)
    {
        $segments = [];
        $rfmSummary = $behaviorData['rfm_segmentation']['summary'] ?? [];
        
        if (isset($rfmSummary['champions_count'])) {
            $segments['champions'] = [
                'count' => $rfmSummary['champions_count'],
                'description' => 'High value, frequent, recent customers',
                'strategy' => 'Reward & retain dengan VIP program'
            ];
        }
        
        if (isset($rfmSummary['at_risk_count'])) {
            $segments['at_risk'] = [
                'count' => $rfmSummary['at_risk_count'],
                'description' => 'Frequent but not recent customers',
                'strategy' => 'Re-engage dengan personalized offers'
            ];
        }
        
        if (isset($rfmSummary['lost_count'])) {
            $segments['lost'] = [
                'count' => $rfmSummary['lost_count'],
                'description' => 'Low frequency, not recent customers',
                'strategy' => 'Win-back campaign dengan special promo'
            ];
        }
        
        return $segments;
    }
}
