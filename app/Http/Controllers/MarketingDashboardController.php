<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Services\CustomerBehaviorAnalysisService;
use App\Services\MarketingStrategyAIService;
use App\Services\AIDatabaseHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Controller untuk Marketing Dashboard
 * Fokus pada behavior analysis dan strategy suggestions untuk tim marketing
 */
class MarketingDashboardController extends Controller
{
    protected $behaviorService;
    protected $strategyService;

    public function __construct(
        CustomerBehaviorAnalysisService $behaviorService,
        MarketingStrategyAIService $strategyService
    ) {
        $this->behaviorService = $behaviorService;
        $this->strategyService = $strategyService;
    }

    /**
     * Main dashboard page
     */
    public function index(Request $request)
    {
        // Default: Bulan berjalan (tanggal 1 bulan ini sampai hari ini)
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            // Get behavior analysis
            $behaviorData = $this->behaviorService->getBehaviorAnalysis($dateFrom, $dateTo, $outletCode);
            
            // Get strategy suggestions
            $strategies = $this->strategyService->generateStrategies($dateFrom, $dateTo, $outletCode);
            
            return Inertia::render('Marketing/Dashboard', [
                'behaviorData' => $behaviorData,
                'strategies' => $strategies,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Marketing Dashboard Error: ' . $e->getMessage());
            return Inertia::render('Marketing/Dashboard', [
                'error' => 'Error loading dashboard data: ' . $e->getMessage(),
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        }
    }

    /**
     * API endpoint untuk behavior analysis
     */
    public function getBehaviorAnalysis(Request $request)
    {
        // Default: Bulan berjalan
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $data = $this->behaviorService->getBehaviorAnalysis($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Behavior Analysis API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading behavior analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk marketing strategies
     */
    public function getStrategies(Request $request)
    {
        // Default: Bulan berjalan
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $strategies = $this->strategyService->generateStrategies($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $strategies,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Marketing Strategies API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating strategies: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk RFM segmentation
     */
    public function getRFMSegmentation(Request $request)
    {
        // Default: Bulan berjalan
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $rfmData = $this->behaviorService->getRFMSegmentation($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $rfmData,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('RFM Segmentation API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading RFM segmentation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk churn risk analysis
     */
    public function getChurnRisk(Request $request)
    {
        $daysThreshold = $request->get('days_threshold', 30);
        $outletCode = $request->get('outlet_code');
        $limit = $request->get('limit', 50);

        try {
            $churnData = app(AIDatabaseHelper::class)->getChurnRiskAnalysis($daysThreshold, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'at_risk_count' => count($churnData),
                    'at_risk_customers' => array_slice($churnData, 0, $limit),
                    'threshold_days' => $daysThreshold
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Churn Risk API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading churn risk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk product preferences
     */
    public function getProductPreferences(Request $request)
    {
        // Default: Bulan berjalan
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $customerType = $request->get('customer_type', 'all'); // all, member, non_member
        $outletCode = $request->get('outlet_code');
        $limit = $request->get('limit', 10); // Default 10 untuk top products

        try {
            $preferences = app(AIDatabaseHelper::class)->getProductPreferenceAnalysis(
                $dateFrom, 
                $dateTo, 
                $customerType, 
                $outletCode, 
                $limit
            );
            
            return response()->json([
                'success' => true,
                'data' => $preferences,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'customer_type' => $customerType,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Product Preferences API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading product preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk mendapatkan detail customer per RFM segment
     */
    public function getRFMDetail(Request $request)
    {
        $segment = $request->get('segment'); // champions, loyal, at_risk, lost
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        
        // Filter
        $search = $request->get('search', '');
        $minRevenue = $request->get('min_revenue');
        $maxRevenue = $request->get('max_revenue');
        $minOrders = $request->get('min_orders');
        $maxOrders = $request->get('max_orders');
        
        // Sort
        $sortBy = $request->get('sort_by', 'total_revenue'); // total_revenue, total_orders, last_order_date, recency_days, avg_order_value
        $sortOrder = $request->get('sort_order', 'desc'); // asc, desc

        try {
            $result = $this->behaviorService->getRFMSegmentDetail(
                $segment, 
                $dateFrom, 
                $dateTo, 
                $outletCode,
                $perPage,
                $page,
                $search,
                $minRevenue,
                $maxRevenue,
                $minOrders,
                $maxOrders,
                $sortBy,
                $sortOrder
            );
            
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (\Exception $e) {
            Log::error('RFM Detail Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching RFM detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk mendapatkan transaksi berdasarkan promo atau manual discount
     */
    public function getPromoTransactions(Request $request)
    {
        $promoId = $request->get('promo_id');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        
        // Filter
        $search = $request->get('search', '');
        $minAmount = $request->get('min_amount');
        $maxAmount = $request->get('max_amount');
        
        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        try {
            $query = DB::table('orders as o')
                ->join('order_promos as op', 'o.id', '=', 'op.order_id')
                ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                ->where('op.promo_id', $promoId)
                ->where('op.status', 'active')
                ->whereBetween(DB::raw('DATE(o.created_at)'), [$dateFrom, $dateTo])
                ->select(
                    'o.id',
                    'o.nomor',
                    'o.created_at',
                    'o.grand_total',
                    'o.discount',
                    'o.member_id',
                    'o.kode_outlet',
                    'outlet.nama_outlet'
                );

            if ($outletCode) {
                $query->where('o.kode_outlet', $outletCode);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('o.nomor', 'like', "%{$search}%")
                      ->orWhere('o.member_id', 'like', "%{$search}%");
                });
            }

            if ($minAmount !== null) {
                $query->where('o.grand_total', '>=', $minAmount);
            }

            if ($maxAmount !== null) {
                $query->where('o.grand_total', '<=', $maxAmount);
            }

            // Sort
            $query->orderBy($sortBy, $sortOrder);

            $total = $query->count();
            $transactions = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                    'to' => min($page * $perPage, $total)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Promo Transactions API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk mendapatkan transaksi berdasarkan manual discount reason
     */
    public function getManualDiscountTransactions(Request $request)
    {
        $reason = $request->get('reason');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        
        // Filter
        $search = $request->get('search', '');
        $minAmount = $request->get('min_amount');
        $maxAmount = $request->get('max_amount');
        
        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        try {
            $query = DB::table('orders as o')
                ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
                ->where('o.manual_discount_amount', '>', 0)
                ->whereBetween(DB::raw('DATE(o.created_at)'), [$dateFrom, $dateTo])
                ->select(
                    'o.id',
                    'o.nomor',
                    'o.created_at',
                    'o.grand_total',
                    'o.discount',
                    'o.manual_discount_amount',
                    'o.manual_discount_reason',
                    'o.member_id',
                    'o.kode_outlet',
                    'outlet.nama_outlet'
                );

            // Filter by reason - sesuai dengan mapping di AIDatabaseHelper
            if ($reason === 'No Reason') {
                $query->where(function($q) {
                    $q->whereNull('o.manual_discount_reason')
                      ->orWhere('o.manual_discount_reason', '');
                });
            } elseif (stripos($reason, 'bank') !== false || stripos($reason, 'BANK') !== false) {
                // Untuk bank, gunakan reason asli (tidak digabung)
                // Reason bisa seperti "BANK BCA", "BANK MANDIRI", dll
                $query->where('o.manual_discount_reason', 'like', '%' . $reason . '%');
            } else {
                // For other categories, use the mapped reason
                $reasonMap = [
                    'Investor' => '%investor%',
                    'Founder' => '%founder%',
                    'Entertainment' => '%entertainment%',
                    'Guest Satisfaction' => '%guest%',
                    'Compliment' => '%compliment%',
                    'Outlet City Ledger' => '%city ledger%',
                ];
                
                if (isset($reasonMap[$reason])) {
                    $query->where('o.manual_discount_reason', 'like', $reasonMap[$reason]);
                } else {
                    // For other reasons, use like match
                    $query->where('o.manual_discount_reason', 'like', '%' . $reason . '%');
                }
            }

            if ($outletCode) {
                $query->where('o.kode_outlet', $outletCode);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('o.nomor', 'like', "%{$search}%")
                      ->orWhere('o.member_id', 'like', "%{$search}%")
                      ->orWhere('o.manual_discount_reason', 'like', "%{$search}%");
                });
            }

            if ($minAmount !== null) {
                $query->where('o.grand_total', '>=', $minAmount);
            }

            if ($maxAmount !== null) {
                $query->where('o.grand_total', '<=', $maxAmount);
            }

            // Sort
            $query->orderBy($sortBy, $sortOrder);

            $total = $query->count();
            $transactions = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                    'to' => min($page * $perPage, $total)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Manual Discount Transactions API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk time pattern analysis
     */
    public function getTimePatterns(Request $request)
    {
        // Default: Bulan berjalan
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $customerType = $request->get('customer_type', 'all');
        $outletCode = $request->get('outlet_code');

        try {
            $timePatterns = app(AIDatabaseHelper::class)->getTimePatternAnalysis(
                $dateFrom, 
                $dateTo, 
                $customerType, 
                $outletCode
            );
            
            return response()->json([
                'success' => true,
                'data' => $timePatterns,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'customer_type' => $customerType,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Time Patterns API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading time patterns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export behavior analysis report
     */
    public function exportReport(Request $request)
    {
        // Default: Bulan berjalan
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');
        $format = $request->get('format', 'pdf'); // pdf, excel, csv

        try {
            $behaviorData = $this->behaviorService->getBehaviorAnalysis($dateFrom, $dateTo, $outletCode);
            $strategies = $this->strategyService->generateStrategies($dateFrom, $dateTo, $outletCode);
            
            // TODO: Implement export functionality
            // Bisa menggunakan library seperti Maatwebsite/Excel untuk Excel export
            // atau DomPDF untuk PDF export
            
            return response()->json([
                'success' => true,
                'message' => 'Export functionality akan diimplementasikan',
                'data' => [
                    'behavior' => $behaviorData,
                    'strategies' => $strategies
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Export Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Customer Lifetime Value (CLV)
     */
    public function getCustomerLifetimeValue(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $clv = app(AIDatabaseHelper::class)->getCustomerLifetimeValue($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $clv,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('CLV API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading CLV data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Repeat Purchase Rate
     */
    public function getRepeatPurchaseRate(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $repeatRate = app(AIDatabaseHelper::class)->getRepeatPurchaseRate($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $repeatRate,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Repeat Purchase Rate API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading repeat purchase rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Average Days Between Orders
     */
    public function getAverageDaysBetweenOrders(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $avgDays = app(AIDatabaseHelper::class)->getAverageDaysBetweenOrders($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $avgDays,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Average Days Between Orders API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading average days between orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Basket Analysis / Product Affinity
     */
    public function getBasketAnalysis(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');
        $limit = $request->get('limit', 10);

        try {
            $basketAnalysis = app(AIDatabaseHelper::class)->getBasketAnalysis($dateFrom, $dateTo, $outletCode, $limit);
            
            return response()->json([
                'success' => true,
                'data' => $basketAnalysis,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Basket Analysis API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading basket analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Peak Hours & Day Analysis
     */
    public function getPeakHoursDayAnalysis(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');

        try {
            $peakAnalysis = app(AIDatabaseHelper::class)->getPeakHoursDayAnalysis($dateFrom, $dateTo, $outletCode);
            
            return response()->json([
                'success' => true,
                'data' => $peakAnalysis,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Peak Hours Day Analysis API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading peak hours day analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Customer Acquisition Trends
     */
    public function getCustomerAcquisitionTrends(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletCode = $request->get('outlet_code');
        $groupBy = $request->get('group_by', 'day'); // day, week, month

        try {
            $acquisitionTrends = app(AIDatabaseHelper::class)->getCustomerAcquisitionTrends($dateFrom, $dateTo, $outletCode, $groupBy);
            
            return response()->json([
                'success' => true,
                'data' => $acquisitionTrends,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode,
                    'group_by' => $groupBy
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Customer Acquisition Trends API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading customer acquisition trends: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Analysis by Region
     */
    public function getAnalysisByRegion(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        try {
            $regionAnalysis = app(AIDatabaseHelper::class)->getAnalysisByRegion($dateFrom, $dateTo);
            
            return response()->json([
                'success' => true,
                'data' => $regionAnalysis,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Analysis By Region API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading region analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint untuk Analysis by Outlet
     */
    public function getAnalysisByOutlet(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $regionId = $request->get('region_id');

        try {
            $outletAnalysis = app(AIDatabaseHelper::class)->getAnalysisByOutlet($dateFrom, $dateTo, $regionId);
            
            return response()->json([
                'success' => true,
                'data' => $outletAnalysis,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'region_id' => $regionId
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Analysis By Outlet API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading outlet analysis: ' . $e->getMessage()
            ], 500);
        }
    }
}
