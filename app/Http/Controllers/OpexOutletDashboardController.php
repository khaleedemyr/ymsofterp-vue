<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class OpexOutletDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        
        // Validasi outlet: jika user bukan admin (id_outlet != 1), gunakan outlet user
        // Jika admin (id_outlet = 1), bisa pilih outlet dari request
        if ($userOutletId == 1) {
            $outletId = $request->get('outlet_id', null);
        } else {
            $outletId = $userOutletId; // Force menggunakan outlet user
        }

        // Get dashboard data - hanya jika outlet sudah dipilih (untuk admin) atau sudah ada outlet (untuk non-admin)
        $dashboardData = null;
        if ($outletId) {
            $dashboardData = $this->getDashboardData($dateFrom, $dateTo, $outletId, $request);
        } else {
            // Return empty dashboard data structure
            $dashboardData = [
                'overview' => null,
                'opexTrend' => [],
                'opexByCategory' => [],
                'unpaidPRs' => [],
                'recentPayments' => [],
                'retailNonFood' => []
            ];
        }
        
        // Get outlets list for filter (hanya untuk admin)
        $outlets = [];
        if ($userOutletId == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']);
        } else {
            // Untuk non-admin, tambahkan outlet user ke list untuk ditampilkan
            $userOutlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $userOutletId)
                ->first(['id_outlet', 'nama_outlet']);
            if ($userOutlet) {
                $outlets = [$userOutlet];
            }
        }
        
        return Inertia::render('OpexOutletDashboard/Index', [
            'dashboardData' => $dashboardData,
            'outlets' => $outlets,
            'userOutletId' => $userOutletId,
            'selectedOutletId' => $outletId,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId
            ]
        ]);
    }

    private function getDashboardData($dateFrom, $dateTo, $outletId = null, $request = null)
    {
        // 1. Overview Metrics
        $overview = $this->getOverviewMetrics($dateFrom, $dateTo, $outletId);
        
        // 2. Opex Trend (Daily)
        $opexTrend = $this->getOpexTrend($dateFrom, $dateTo, $outletId);
        
        // 3. Opex by Category
        $opexByCategory = $this->getOpexByCategory($dateFrom, $dateTo, $outletId);
        
        // 4. Unpaid PRs
        $unpaidPRs = $this->getUnpaidPRs($dateFrom, $dateTo, $outletId);
        
        // 5. Recent Payments
        $recentPayments = $this->getRecentPayments($dateFrom, $dateTo, $outletId);
        
        // 6. Retail Non Food (with pagination)
        $retailPage = $request ? $request->get('retail_page', 1) : 1;
        $retailNonFood = $this->getRetailNonFood($dateFrom, $dateTo, $outletId, $retailPage);

        return [
            'overview' => $overview,
            'opexTrend' => $opexTrend,
            'opexByCategory' => $opexByCategory,
            'unpaidPRs' => $unpaidPRs,
            'recentPayments' => $recentPayments,
            'retailNonFood' => $retailNonFood
        ];
    }

    private function getOverviewMetrics($dateFrom, $dateTo, $outletId = null)
    {
        // Get paid amount from Non Food Payment
        $paidQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->where('poi.source_type', 'purchase_requisition_ops');
        
        if ($outletId) {
            $paidQuery->where('pr.outlet_id', $outletId);
        }
        
        $totalPaid = (float) $paidQuery->sum('nfp.amount');
        $paymentCount = (int) $paidQuery->count('nfp.id');
        
        // Get direct payment (without PO)
        $directPaidQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id');
        
        if ($outletId) {
            $directPaidQuery->where('pr.outlet_id', $outletId);
        }
        
        $totalDirectPaid = (float) $directPaidQuery->sum('nfp.amount');
        $directPaymentCount = (int) $directPaidQuery->count('nfp.id');
        
        // Get Retail Non Food
        $retailNonFoodQuery = DB::table('retail_non_food as rnf')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved');
        
        if ($outletId) {
            $retailNonFoodQuery->where('rnf.outlet_id', $outletId);
        }
        
        $totalRetailNonFood = (float) $retailNonFoodQuery->sum('rnf.total_amount');
        $retailNonFoodCount = (int) $retailNonFoodQuery->count('rnf.id');
        
        // Get unpaid PRs
        $unpaidQuery = DB::table('purchase_requisitions as pr')
            ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
            ->where('pr.is_held', false);
        
        if ($outletId) {
            $unpaidQuery->where('pr.outlet_id', $outletId);
        }
        
        $unpaidPRs = $unpaidQuery->get();
        
        // Calculate unpaid amount and count only PRs with unpaid amount > 0
        $unpaidAmount = 0;
        $unpaidPRCount = 0;
        foreach ($unpaidPRs as $pr) {
            // Get PO total for this PR
            $poTotal = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poi.source_id', $pr->id)
                ->where('poo.status', 'approved')
                ->sum('poi.total');
            
            // Get paid amount for this PR
            $paidAmount = 0;
            if ($poTotal > 0) {
                $poIds = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poi.source_id', $pr->id)
                    ->where('poo.status', 'approved')
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
                
                $paidAmount = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIds)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
            } else {
                // Direct payment (without PO)
                $paidAmount = DB::table('non_food_payments')
                    ->where('purchase_requisition_id', $pr->id)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
            }
            
            $prAmount = $poTotal > 0 ? $poTotal : $pr->amount;
            $prUnpaidAmount = max(0, $prAmount - $paidAmount);
            
            if ($prUnpaidAmount > 0) {
                $unpaidAmount += $prUnpaidAmount;
                $unpaidPRCount++;
            }
        }
        
        // Get Food Expenses (Floor Order GR + Retail Food)
        $foodExpenses = $this->getFoodExpenses($dateFrom, $dateTo, $outletId);
        $totalFood = $foodExpenses['total'];
        $foodCount = $foodExpenses['count'];
        
        $totalOpex = $totalPaid + $totalDirectPaid + $totalRetailNonFood + $totalFood;
        
        return [
            'total_paid' => $totalPaid + $totalDirectPaid,
            'total_retail_non_food' => $totalRetailNonFood,
            'total_food' => $totalFood,
            'total_unpaid' => $unpaidAmount,
            'total_opex' => $totalOpex,
            'payment_count' => $paymentCount + $directPaymentCount,
            'retail_non_food_count' => $retailNonFoodCount,
            'food_count' => $foodCount,
            'unpaid_pr_count' => $unpaidPRCount
        ];
    }
    
    private function getFoodExpenses($dateFrom, $dateTo, $outletId = null)
    {
        // Get Floor Order GR (Good Receives yang sudah di-GR)
        $floorOrderGRQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->where(function($q) {
                         $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                           ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                     });
            })
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at');
        
        if ($outletId) {
            $floorOrderGRQuery->where('gr.outlet_id', $outletId);
        }
        
        $floorOrderGRTotal = (float) $floorOrderGRQuery->sum(DB::raw('i.received_qty * COALESCE(fo.price, 0)'));
        $floorOrderGRCount = (int) DB::table('outlet_food_good_receives as gr')
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at')
            ->when($outletId, function($q) use ($outletId) {
                return $q->where('gr.outlet_id', $outletId);
            })
            ->distinct('gr.id')
            ->count('gr.id');
        
        // Get Retail Food
        $retailFoodQuery = DB::table('retail_food as rf')
            ->whereBetween('rf.transaction_date', [$dateFrom, $dateTo])
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at');
        
        if ($outletId) {
            $retailFoodQuery->where('rf.outlet_id', $outletId);
        }
        
        $retailFoodTotal = (float) $retailFoodQuery->sum('rf.total_amount');
        $retailFoodCount = (int) $retailFoodQuery->count('rf.id');
        
        return [
            'total' => $floorOrderGRTotal + $retailFoodTotal,
            'count' => $floorOrderGRCount + $retailFoodCount,
            'floor_order_gr_total' => $floorOrderGRTotal,
            'floor_order_gr_count' => $floorOrderGRCount,
            'retail_food_total' => $retailFoodTotal,
            'retail_food_count' => $retailFoodCount
        ];
    }

    private function getOpexTrend($dateFrom, $dateTo, $outletId = null)
    {
        // Get all dates in range
        $dates = [];
        $current = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
        
        $result = [];
        foreach ($dates as $date) {
            // Get paid amount for this date
            $paidQuery = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->whereDate('nfp.payment_date', $date)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops');
            
            if ($outletId) {
                $paidQuery->where('pr.outlet_id', $outletId);
            }
            
            $paidAmount = (float) $paidQuery->sum('nfp.amount');
            
            // Get direct payment
            $directPaidQuery = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->whereDate('nfp.payment_date', $date)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->whereNotNull('nfp.purchase_requisition_id');
            
            if ($outletId) {
                $directPaidQuery->where('pr.outlet_id', $outletId);
            }
            
            $directPaidAmount = (float) $directPaidQuery->sum('nfp.amount');
            
            // Get retail non food
            $retailQuery = DB::table('retail_non_food')
                ->whereDate('transaction_date', $date)
                ->where('status', 'approved');
            
            if ($outletId) {
                $retailQuery->where('outlet_id', $outletId);
            }
            
            $retailAmount = (float) $retailQuery->sum('total_amount');
            
            // Get food expenses for this date (Floor Order GR + Retail Food)
            $foodAmount = $this->getFoodExpensesForDate($date, $outletId);
            
            $result[] = [
                'date' => $date,
                'paid_amount' => $paidAmount + $directPaidAmount,
                'retail_non_food_amount' => $retailAmount,
                'food_amount' => $foodAmount
            ];
        }
        
        return $result;
    }
    
    private function getFoodExpensesForDate($date, $outletId = null)
    {
        // Get Floor Order GR for this date
        $floorOrderGRQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->where(function($q) {
                         $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                           ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                     });
            })
            ->whereDate('gr.receive_date', $date)
            ->whereNull('gr.deleted_at');
        
        if ($outletId) {
            $floorOrderGRQuery->where('gr.outlet_id', $outletId);
        }
        
        $floorOrderGRTotal = (float) $floorOrderGRQuery->sum(DB::raw('i.received_qty * COALESCE(fo.price, 0)'));
        
        // Get Retail Food for this date
        $retailFoodQuery = DB::table('retail_food as rf')
            ->whereDate('rf.transaction_date', $date)
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at');
        
        if ($outletId) {
            $retailFoodQuery->where('rf.outlet_id', $outletId);
        }
        
        $retailFoodTotal = (float) $retailFoodQuery->sum('rf.total_amount');
        
        return $floorOrderGRTotal + $retailFoodTotal;
    }

    private function getOpexByCategory($dateFrom, $dateTo, $outletId = null)
    {
        // Get paid amount by category from PRs (via PO)
        $paidByCategory = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops');
        
        if ($outletId) {
            $paidByCategory->where('pr.outlet_id', $outletId);
        }
        
        $paidByCategory = $paidByCategory->select('prc.id as category_id', 'prc.name as category_name', 'prc.division', DB::raw('COALESCE(SUM(nfp.amount), 0) as paid_amount'))
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();
        
        // Get direct payment by category (without PO)
        $directPaidByCategory = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id');
        
        if ($outletId) {
            $directPaidByCategory->where('pr.outlet_id', $outletId);
        }
        
        $directPaidByCategory = $directPaidByCategory->select('prc.id as category_id', 'prc.name as category_name', 'prc.division', DB::raw('COALESCE(SUM(nfp.amount), 0) as paid_amount'))
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();
        
        // Get retail non food by category
        $retailByCategory = DB::table('retail_non_food as rnf')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved');
        
        if ($outletId) {
            $retailByCategory->where('rnf.outlet_id', $outletId);
        }
        
        $retailByCategory = $retailByCategory->select('prc.id as category_id', 'prc.name as category_name', 'prc.division', DB::raw('COALESCE(SUM(rnf.total_amount), 0) as retail_non_food_amount'))
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->get();
        
        // Get uncategorized payments (via PO) - where PR has no category
        $uncategorizedPaidQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereNull('pr.category_id');
        
        if ($outletId) {
            $uncategorizedPaidQuery->where('pr.outlet_id', $outletId);
        }
        
        $uncategorizedPaid = (float) $uncategorizedPaidQuery->sum('nfp.amount');
        
        // Get uncategorized direct payments
        $uncategorizedDirectPaidQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereNull('pr.category_id');
        
        if ($outletId) {
            $uncategorizedDirectPaidQuery->where('pr.outlet_id', $outletId);
        }
        
        $uncategorizedDirectPaid = (float) $uncategorizedDirectPaidQuery->sum('nfp.amount');
        
        // Get uncategorized retail non food
        $uncategorizedRetailQuery = DB::table('retail_non_food as rnf')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.category_budget_id');
        
        if ($outletId) {
            $uncategorizedRetailQuery->where('rnf.outlet_id', $outletId);
        }
        
        $uncategorizedRetail = (float) $uncategorizedRetailQuery->sum('rnf.total_amount');
        
        // Combine results
        $combined = [];
        $uncategorizedKey = 'uncategorized';
        
        foreach ($paidByCategory as $item) {
            $key = $item->category_id ? $item->category_id : $uncategorizedKey;
            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'category_id' => $item->category_id ?: $uncategorizedKey,
                    'category_name' => $item->category_name ?: 'Uncategorized',
                    'division' => $item->division ?: '',
                    'paid_amount' => 0,
                    'retail_non_food_amount' => 0
                ];
            }
            $combined[$key]['paid_amount'] += (float) $item->paid_amount;
        }
        
        foreach ($directPaidByCategory as $item) {
            $key = $item->category_id ? $item->category_id : $uncategorizedKey;
            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'category_id' => $item->category_id ?: $uncategorizedKey,
                    'category_name' => $item->category_name ?: 'Uncategorized',
                    'division' => $item->division ?: '',
                    'paid_amount' => 0,
                    'retail_non_food_amount' => 0
                ];
            }
            $combined[$key]['paid_amount'] += (float) $item->paid_amount;
        }
        
        foreach ($retailByCategory as $item) {
            $key = $item->category_id ? $item->category_id : $uncategorizedKey;
            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'category_id' => $item->category_id ?: $uncategorizedKey,
                    'category_name' => $item->category_name ?: 'Uncategorized',
                    'division' => $item->division ?: '',
                    'paid_amount' => 0,
                    'retail_non_food_amount' => 0
                ];
            }
            $combined[$key]['retail_non_food_amount'] += (float) $item->retail_non_food_amount;
        }
        
        // Add uncategorized if there's any amount
        if ($uncategorizedPaid > 0 || $uncategorizedDirectPaid > 0 || $uncategorizedRetail > 0) {
            if (!isset($combined[$uncategorizedKey])) {
                $combined[$uncategorizedKey] = [
                    'category_id' => $uncategorizedKey,
                    'category_name' => 'Uncategorized',
                    'division' => '',
                    'paid_amount' => 0,
                    'retail_non_food_amount' => 0
                ];
            }
            $combined[$uncategorizedKey]['paid_amount'] += $uncategorizedPaid + $uncategorizedDirectPaid;
            $combined[$uncategorizedKey]['retail_non_food_amount'] += $uncategorizedRetail;
        }
        
        // Filter and sort
        $result = array_filter($combined, function($item) {
            return ($item['paid_amount'] > 0 || $item['retail_non_food_amount'] > 0);
        });
        
        usort($result, function($a, $b) {
            $totalA = $a['paid_amount'] + $a['retail_non_food_amount'];
            $totalB = $b['paid_amount'] + $b['retail_non_food_amount'];
            return $totalB <=> $totalA;
        });
        
        return array_values($result);
    }

    private function getPaymentMethods($dateFrom, $dateTo, $outletId = null)
    {
        $query = DB::table('non_food_payments as nfp')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled');
        
        if ($outletId) {
            $query->where(function($q) use ($outletId) {
                $q->whereExists(function($subQ) use ($outletId) {
                    $subQ->select(DB::raw(1))
                        ->from('purchase_order_ops as poo')
                        ->join('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                        ->join('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->whereColumn('poo.id', 'nfp.purchase_order_ops_id')
                        ->where('pr.outlet_id', $outletId);
                })
                ->orWhereExists(function($subQ) use ($outletId) {
                    $subQ->select(DB::raw(1))
                        ->from('purchase_requisitions as pr')
                        ->whereColumn('pr.id', 'nfp.purchase_requisition_id')
                        ->where('pr.outlet_id', $outletId);
                });
            });
        }
        
        return $query->select('nfp.payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(nfp.amount) as total_amount'))
            ->groupBy('nfp.payment_method')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    private function getUnpaidPRs($dateFrom, $dateTo, $outletId = null)
    {
        $query = DB::table('purchase_requisitions as pr')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
            ->where('pr.is_held', false);
        
        if ($outletId) {
            $query->where('pr.outlet_id', $outletId);
        }
        
        $prs = $query->select(
                'pr.id',
                'pr.pr_number',
                'pr.title',
                'pr.amount',
                'pr.status',
                'pr.created_at',
                'o.nama_outlet as outlet_name',
                'prc.name as category_name'
            )
            ->orderBy('pr.created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Calculate unpaid amount for each PR
        $result = [];
        foreach ($prs as $pr) {
            // Get PO total
            $poTotal = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poi.source_id', $pr->id)
                ->where('poo.status', 'approved')
                ->sum('poi.total');
            
            // Get paid amount
            $paidAmount = 0;
            if ($poTotal > 0) {
                $poIds = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poi.source_id', $pr->id)
                    ->where('poo.status', 'approved')
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
                
                $paidAmount = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIds)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
            } else {
                $paidAmount = DB::table('non_food_payments')
                    ->where('purchase_requisition_id', $pr->id)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
            }
            
            $prAmount = $poTotal > 0 ? $poTotal : $pr->amount;
            $unpaidAmount = max(0, $prAmount - $paidAmount);
            
            if ($unpaidAmount > 0) {
                // Get PR items
                $items = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $pr->id)
                    ->select('id', 'item_name', 'qty', 'unit', 'unit_price as price', 'subtotal as total')
                    ->get();
                
                $result[] = [
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'title' => $pr->title,
                    'amount' => (float) $prAmount,
                    'paid_amount' => (float) $paidAmount,
                    'unpaid_amount' => (float) $unpaidAmount,
                    'status' => $pr->status,
                    'outlet_name' => $pr->outlet_name,
                    'category_name' => $pr->category_name,
                    'created_at' => $pr->created_at,
                    'items' => $items
                ];
            }
        }
        
        return $result;
    }

    private function getRecentPayments($dateFrom, $dateTo, $outletId = null)
    {
        // Get payments via PO
        $poPaymentsQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('suppliers as s', 'nfp.supplier_id', '=', 's.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops');
        
        if ($outletId) {
            $poPaymentsQuery->where('pr.outlet_id', $outletId);
        }
        
        $poPayments = $poPaymentsQuery->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.amount',
                'nfp.payment_method',
                'nfp.payment_date',
                'nfp.status',
                'pr.pr_number',
                'poo.id as po_id',
                'o.nama_outlet as outlet_name',
                's.name as supplier_name'
            )
            ->groupBy('nfp.id', 'nfp.payment_number', 'nfp.amount', 'nfp.payment_method', 'nfp.payment_date', 'nfp.status', 'pr.pr_number', 'poo.id', 'o.nama_outlet', 's.name')
            ->orderBy('nfp.payment_date', 'desc')
            ->orderBy('nfp.created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Get direct payments (without PO)
        $directPaymentsQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('suppliers as s', 'nfp.supplier_id', '=', 's.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id');
        
        if ($outletId) {
            $directPaymentsQuery->where('pr.outlet_id', $outletId);
        }
        
        $directPayments = $directPaymentsQuery->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.amount',
                'nfp.payment_method',
                'nfp.payment_date',
                'nfp.status',
                'pr.pr_number',
                'pr.id as pr_id',
                'o.nama_outlet as outlet_name',
                's.name as supplier_name'
            )
            ->orderBy('nfp.payment_date', 'desc')
            ->orderBy('nfp.created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Combine and get items
        $allPayments = $poPayments->merge($directPayments)->sortByDesc('payment_date')->take(20);
        
        foreach ($allPayments as $payment) {
            $items = [];
            
            if (isset($payment->po_id) && $payment->po_id) {
                // Get items from PO
                $poItems = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->where('poi.purchase_order_ops_id', $payment->po_id)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->select('poi.id', 'poi.item_name', 'poi.quantity as qty', 'poi.unit', 'poi.price', 'poi.total', 'pr.pr_number')
                    ->get();
                $items = $poItems;
            } elseif (isset($payment->pr_id) && $payment->pr_id) {
                // Get items from PR (direct payment)
                $prItems = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $payment->pr_id)
                    ->select('id', 'item_name', 'qty', 'unit', 'unit_price as price', 'subtotal as total')
                    ->get();
                $items = $prItems;
            }
            
            $payment->items = $items;
        }
        
        return $allPayments->values();
    }

    private function getRetailNonFood($dateFrom, $dateTo, $outletId = null, $page = 1)
    {
        $perPage = 10;
        $query = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
            ->leftJoin('users as creator', 'rnf.created_by', '=', 'creator.id')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved');
        
        if ($outletId) {
            $query->where('rnf.outlet_id', $outletId);
        }
        
        $total = $query->count();
        $data = $query->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                'rnf.status',
                'o.nama_outlet as outlet_name',
                'prc.name as category_name',
                'prc.division as category_division',
                'creator.nama_lengkap as creator_name',
                'rnf.created_at'
            )
            ->orderBy('rnf.transaction_date', 'desc')
            ->orderBy('rnf.created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        
        // Get items for each retail non food
        foreach ($data as $rnf) {
            $items = DB::table('retail_non_food_items')
                ->where('retail_non_food_id', $rnf->id)
                ->select('id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
                ->get();
            $rnf->items = $items;
        }
        
        return [
            'data' => $data,
            'current_page' => (int) $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage)
        ];
    }

    public function getCategoryDetail(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        $categoryId = $request->get('category_id');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        
        // Validasi outlet
        if ($userOutletId == 1) {
            $outletId = $request->get('outlet_id', null);
        } else {
            $outletId = $userOutletId;
        }

        if (!$categoryId) {
            return response()->json(['error' => 'Category ID is required'], 400);
        }

        // Get category info
        $category = DB::table('purchase_requisition_categories')
            ->where('id', $categoryId)
            ->first(['id', 'name', 'division']);

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        // Get trend data for this category
        $trend = $this->getCategoryTrend($dateFrom, $dateTo, $categoryId, $outletId);

        // Get transactions (payments and retail non food)
        $transactions = $this->getCategoryTransactions($dateFrom, $dateTo, $categoryId, $outletId);

        return response()->json([
            'category' => $category,
            'trend' => $trend,
            'transactions' => $transactions
        ]);
    }

    private function getCategoryTrend($dateFrom, $dateTo, $categoryId, $outletId = null)
    {
        $dates = [];
        $current = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
        
        $result = [];
        foreach ($dates as $date) {
            // Get paid amount for this category and date
            $paidQuery = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->whereDate('nfp.payment_date', $date)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('pr.category_id', $categoryId);
            
            if ($outletId) {
                $paidQuery->where('pr.outlet_id', $outletId);
            }
            
            $paidAmount = (float) $paidQuery->sum('nfp.amount');
            
            // Get direct payment
            $directPaidQuery = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->whereDate('nfp.payment_date', $date)
                ->whereIn('nfp.status', ['paid', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->whereNotNull('nfp.purchase_requisition_id')
                ->where('pr.category_id', $categoryId);
            
            if ($outletId) {
                $directPaidQuery->where('pr.outlet_id', $outletId);
            }
            
            $directPaidAmount = (float) $directPaidQuery->sum('nfp.amount');
            
            // Get retail non food
            $retailQuery = DB::table('retail_non_food')
                ->whereDate('transaction_date', $date)
                ->where('status', 'approved')
                ->where('category_budget_id', $categoryId);
            
            if ($outletId) {
                $retailQuery->where('outlet_id', $outletId);
            }
            
            $retailAmount = (float) $retailQuery->sum('total_amount');
            
            $result[] = [
                'date' => $date,
                'paid_amount' => $paidAmount + $directPaidAmount,
                'retail_non_food_amount' => $retailAmount
            ];
        }
        
        return $result;
    }

    private function getCategoryTransactions($dateFrom, $dateTo, $categoryId, $outletId = null)
    {
        $transactions = [];

        // Get payments via PO
        $poPaymentsQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->where('pr.category_id', $categoryId);
        
        if ($outletId) {
            $poPaymentsQuery->where('pr.outlet_id', $outletId);
        }
        
        $poPayments = $poPaymentsQuery->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.amount',
                'nfp.payment_date',
                'nfp.payment_method',
                'pr.pr_number',
                'poo.id as po_id',
                'o.nama_outlet as outlet_name',
                DB::raw("'payment' as type")
            )
            ->groupBy('nfp.id', 'nfp.payment_number', 'nfp.amount', 'nfp.payment_date', 'nfp.payment_method', 'pr.pr_number', 'poo.id', 'o.nama_outlet')
            ->get();

        // Get direct payments
        $directPaymentsQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id')
            ->where('pr.category_id', $categoryId);
        
        if ($outletId) {
            $directPaymentsQuery->where('pr.outlet_id', $outletId);
        }
        
        $directPayments = $directPaymentsQuery->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.amount',
                'nfp.payment_date',
                'nfp.payment_method',
                'pr.pr_number',
                'pr.id as pr_id',
                'o.nama_outlet as outlet_name',
                DB::raw("'payment' as type")
            )
            ->get();

        // Get retail non food
        $retailQuery = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved')
            ->where('rnf.category_budget_id', $categoryId);
        
        if ($outletId) {
            $retailQuery->where('rnf.outlet_id', $outletId);
        }
        
        $retailNonFood = $retailQuery->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.total_amount as amount',
                'rnf.transaction_date as payment_date',
                DB::raw("'Retail Non Food' as payment_method"),
                DB::raw("NULL as pr_number"),
                'o.nama_outlet as outlet_name',
                DB::raw("'retail_non_food' as type")
            )
            ->get();

        // Combine and get items
        $allTransactions = $poPayments->merge($directPayments)->merge($retailNonFood)->sortByDesc('payment_date');

        foreach ($allTransactions as $transaction) {
            $items = [];
            
            if ($transaction->type === 'payment') {
                if (isset($transaction->po_id) && $transaction->po_id) {
                    $items = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->where('poi.purchase_order_ops_id', $transaction->po_id)
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->select('poi.id', 'poi.item_name', 'poi.quantity as qty', 'poi.unit', 'poi.price', 'poi.total')
                        ->get();
                } elseif (isset($transaction->pr_id) && $transaction->pr_id) {
                    $items = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $transaction->pr_id)
                        ->select('id', 'item_name', 'qty', 'unit', 'unit_price as price', 'subtotal as total')
                        ->get();
                }
            } elseif ($transaction->type === 'retail_non_food') {
                $items = DB::table('retail_non_food_items')
                    ->where('retail_non_food_id', $transaction->id)
                    ->select('id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
                    ->get();
            }
            
            $transaction->items = $items;
            $transactions[] = $transaction;
        }

        return $transactions;
    }

    public function getCardDetail(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        $type = $request->get('type'); // total_paid, retail_non_food, unpaid_pr, total_opex
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $modalDateFrom = $request->get('modal_date_from');
        $modalDateTo = $request->get('modal_date_to');
        $search = $request->get('search', '');
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        
        // Validasi outlet
        if ($userOutletId == 1) {
            $outletId = $request->get('outlet_id', null);
        } else {
            $outletId = $userOutletId;
        }

        if (!$type) {
            return response()->json(['error' => 'Type is required'], 400);
        }

        // Use modal date filter if provided, otherwise use main date filter
        $filterDateFrom = $modalDateFrom ?: $dateFrom;
        $filterDateTo = $modalDateTo ?: $dateTo;

        // Get trend data (use main date filter for trend)
        $trend = $this->getCardTrend($dateFrom, $dateTo, $type, $outletId);

        // Get transactions based on type (use modal date filter)
        $transactions = [];
        if ($type === 'total_paid') {
            $transactions = $this->getAllPayments($filterDateFrom, $filterDateTo, $outletId);
        } elseif ($type === 'retail_non_food') {
            $transactions = $this->getAllRetailNonFood($filterDateFrom, $filterDateTo, $outletId);
        } elseif ($type === 'food') {
            $transactions = $this->getAllFoodTransactions($filterDateFrom, $filterDateTo, $outletId);
        } elseif ($type === 'unpaid_pr') {
            $transactions = $this->getAllUnpaidPRs($filterDateFrom, $filterDateTo, $outletId);
        } elseif ($type === 'total_opex') {
            $payments = $this->getAllPayments($filterDateFrom, $filterDateTo, $outletId);
            $retail = $this->getAllRetailNonFood($filterDateFrom, $filterDateTo, $outletId);
            $food = $this->getAllFoodTransactions($filterDateFrom, $filterDateTo, $outletId);
            $transactions = $payments->merge($retail)->merge($food)->sortByDesc(function($item) {
                return $item->payment_date ?? $item->transaction_date ?? $item->receive_date ?? $item->created_at;
            })->values();
        }

        // Apply search filter if provided
        if ($search) {
            $transactions = collect($transactions)->filter(function($transaction) use ($search) {
                $searchLower = strtolower($search);
                return 
                    (isset($transaction->number) && stripos($transaction->number, $search) !== false) ||
                    (isset($transaction->payment_number) && stripos($transaction->payment_number, $search) !== false) ||
                    (isset($transaction->retail_number) && stripos($transaction->retail_number, $search) !== false) ||
                    (isset($transaction->pr_number) && stripos($transaction->pr_number, $search) !== false) ||
                    (isset($transaction->outlet_name) && stripos($transaction->outlet_name, $search) !== false) ||
                    (isset($transaction->creator_name) && stripos($transaction->creator_name, $search) !== false) ||
                    (isset($transaction->title) && stripos($transaction->title, $search) !== false);
            })->values();
        }

        // Pagination
        $total = $transactions->count();
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedTransactions = $transactions->slice($offset, $perPage)->values();

        return response()->json([
            'trend' => $trend,
            'transactions' => $paginatedTransactions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }

    public function getFoodByCategory(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        
        // Validasi outlet
        if ($userOutletId == 1) {
            $outletId = $request->get('outlet_id', null);
        } else {
            $outletId = $userOutletId;
        }

        // Get Floor Order GR data per category and sub category
        $floorOrderGRQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->where(function($q) {
                         $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                           ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                     });
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at');
        
        if ($outletId) {
            $floorOrderGRQuery->where('gr.outlet_id', $outletId);
        }
        
        $floorOrderGRData = $floorOrderGRQuery->select(
                DB::raw("CASE 
                    WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'Main Kitchen'
                    WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN 'Chemical'
                    WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN 'Stationary'
                    WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN 'Marketing'
                    WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN 'Main Store'
                    ELSE 'Other'
                END as category_name"),
                'sc.name as sub_category_name',
                'sc.id as sub_category_id',
                DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as total_amount')
            )
            ->groupBy('category_name', 'sc.name', 'sc.id')
            ->get();
        
        // Get Retail Food data per category and sub category
        $retailFoodQuery = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('items as it', 'rfi.item_name', '=', 'it.name')
            ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereBetween('rf.transaction_date', [$dateFrom, $dateTo])
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at');
        
        if ($outletId) {
            $retailFoodQuery->where('rf.outlet_id', $outletId);
        }
        
        $retailFoodData = $retailFoodQuery->select(
                DB::raw("CASE 
                    WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'Main Kitchen'
                    WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN 'Chemical'
                    WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN 'Stationary'
                    WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN 'Marketing'
                    WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN 'Main Store'
                    ELSE 'Other'
                END as category_name"),
                'sc.name as sub_category_name',
                'sc.id as sub_category_id',
                DB::raw('SUM(rfi.subtotal) as total_amount')
            )
            ->groupBy('category_name', 'sc.name', 'sc.id')
            ->get();
        
        // Get locked budgets for this outlet, keyed by sub_category_id
        $lockedBudgets = [];
        if ($outletId) {
            $lockedBudgetQuery = DB::table('locked_budget_food_categories as lb')
                ->where('lb.outlet_id', $outletId)
                ->select(
                    'lb.sub_category_id',
                    'lb.budget'
                )
                ->get();
            
            foreach ($lockedBudgetQuery as $budget) {
                $lockedBudgets[$budget->sub_category_id] = (float) $budget->budget;
            }
        }
        
        // Merge data
        $subCategoryTotals = [];
        $categoryTotals = [];
        
        foreach ($floorOrderGRData as $item) {
            $categoryName = $item->category_name ?: 'Other';
            $subCategoryName = $item->sub_category_name;
            
            // Add to sub category totals
            if ($subCategoryName && $item->sub_category_id) {
                $subKey = $categoryName . '|' . $subCategoryName;
                if (!isset($subCategoryTotals[$subKey])) {
                    $subCategoryTotals[$subKey] = [
                        'category_name' => $categoryName,
                        'sub_category_name' => $subCategoryName,
                        'sub_category_id' => $item->sub_category_id,
                        'total_amount' => 0,
                        'is_sub_category' => true,
                        'locked_budget' => null,
                        'is_over_budget' => false
                    ];
                    
                    // Check if there's a locked budget for this sub category
                    if (isset($lockedBudgets[$item->sub_category_id])) {
                        $subCategoryTotals[$subKey]['locked_budget'] = $lockedBudgets[$item->sub_category_id];
                    }
                }
                $subCategoryTotals[$subKey]['total_amount'] += (float) $item->total_amount;
                
                // Check if over budget
                if ($subCategoryTotals[$subKey]['locked_budget'] !== null) {
                    $subCategoryTotals[$subKey]['is_over_budget'] = 
                        $subCategoryTotals[$subKey]['total_amount'] > $subCategoryTotals[$subKey]['locked_budget'];
                }
            } else {
                // Add to category totals (only if no sub category)
                if (!isset($categoryTotals[$categoryName])) {
                    $categoryTotals[$categoryName] = [
                        'category_name' => $categoryName,
                        'sub_category_name' => null,
                        'total_amount' => 0,
                        'is_sub_category' => false,
                        'locked_budget' => null,
                        'is_over_budget' => false
                    ];
                }
                $categoryTotals[$categoryName]['total_amount'] += (float) $item->total_amount;
            }
        }
        
        foreach ($retailFoodData as $item) {
            $categoryName = $item->category_name ?: 'Other';
            $subCategoryName = $item->sub_category_name;
            
            // Add to sub category totals
            if ($subCategoryName && $item->sub_category_id) {
                $subKey = $categoryName . '|' . $subCategoryName;
                if (!isset($subCategoryTotals[$subKey])) {
                    $subCategoryTotals[$subKey] = [
                        'category_name' => $categoryName,
                        'sub_category_name' => $subCategoryName,
                        'sub_category_id' => $item->sub_category_id,
                        'total_amount' => 0,
                        'is_sub_category' => true,
                        'locked_budget' => null,
                        'is_over_budget' => false
                    ];
                    
                    // Check if there's a locked budget for this sub category
                    if (isset($lockedBudgets[$item->sub_category_id])) {
                        $subCategoryTotals[$subKey]['locked_budget'] = $lockedBudgets[$item->sub_category_id];
                    }
                }
                $subCategoryTotals[$subKey]['total_amount'] += (float) $item->total_amount;
                
                // Check if over budget
                if ($subCategoryTotals[$subKey]['locked_budget'] !== null) {
                    $subCategoryTotals[$subKey]['is_over_budget'] = 
                        $subCategoryTotals[$subKey]['total_amount'] > $subCategoryTotals[$subKey]['locked_budget'];
                }
            } else {
                // Add to category totals (only if no sub category)
                if (!isset($categoryTotals[$categoryName])) {
                    $categoryTotals[$categoryName] = [
                        'category_name' => $categoryName,
                        'sub_category_name' => null,
                        'total_amount' => 0,
                        'is_sub_category' => false,
                        'locked_budget' => null,
                        'is_over_budget' => false
                    ];
                }
                $categoryTotals[$categoryName]['total_amount'] += (float) $item->total_amount;
            }
        }
        
        // Combine results: sub categories first, then categories (only those without sub categories)
        $result = [];
        
        // Add sub categories grouped by category
        $order = ['Main Kitchen', 'Main Store', 'Chemical', 'Stationary', 'Marketing', 'Other'];
        foreach ($order as $catName) {
            // Add sub categories for this category
            foreach ($subCategoryTotals as $key => $value) {
                if ($value['category_name'] === $catName) {
                    $result[] = $value;
                }
            }
        }
        
        // Add remaining sub categories not in predefined order
        foreach ($subCategoryTotals as $key => $value) {
            if (!in_array($value['category_name'], $order)) {
                $result[] = $value;
            }
        }
        
        // Add categories that don't have sub categories (only those without sub categories)
        foreach ($order as $catName) {
            // Check if this category has sub categories
            $hasSubCategories = false;
            foreach ($subCategoryTotals as $value) {
                if ($value['category_name'] === $catName) {
                    $hasSubCategories = true;
                    break;
                }
            }
            
            // Only add category if it doesn't have sub categories
            if (!$hasSubCategories && isset($categoryTotals[$catName])) {
                $result[] = $categoryTotals[$catName];
            }
        }
        
        // Add remaining categories not in predefined order
        foreach ($categoryTotals as $key => $value) {
            if (!in_array($key, $order)) {
                // Check if this category has sub categories
                $hasSubCategories = false;
                foreach ($subCategoryTotals as $subValue) {
                    if ($subValue['category_name'] === $key) {
                        $hasSubCategories = true;
                        break;
                    }
                }
                
                if (!$hasSubCategories) {
                    $result[] = $value;
                }
            }
        }
        
        return response()->json($result);
    }
    
    public function getFoodCategoryItems(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;
        
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $categoryName = $request->get('category_name');
        $subCategoryName = $request->get('sub_category_name');
        
        // Validasi outlet
        if ($userOutletId == 1) {
            $outletId = $request->get('outlet_id', null);
        } else {
            $outletId = $userOutletId;
        }
        
        if (!$categoryName) {
            return response()->json(['error' => 'Category name is required'], 400);
        }
        
        $result = [];
        
        // Get Floor Order GR items
        $floorOrderGRQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->where(function($q) {
                         $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                           ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                     });
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at')
            ->whereRaw("CASE 
                WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'Main Kitchen'
                WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN 'Chemical'
                WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN 'Stationary'
                WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN 'Marketing'
                WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN 'Main Store'
                ELSE 'Other'
            END = ?", [$categoryName]);
        
        if ($subCategoryName) {
            $floorOrderGRQuery->where('sc.name', $subCategoryName);
        }
        
        if ($outletId) {
            $floorOrderGRQuery->where('gr.outlet_id', $outletId);
        }
        
        $floorOrderItems = $floorOrderGRQuery
            ->leftJoin('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('users as fo_creator', 'ffo.user_id', '=', 'fo_creator.id')
            ->leftJoin('users as fo_ro_creator', 'ffo_ro.user_id', '=', 'fo_ro_creator.id')
            ->leftJoin('users as do_creator', 'do.created_by', '=', 'do_creator.id')
            ->leftJoin('users as gr_creator', 'gr.created_by', '=', 'gr_creator.id')
            ->select(
                'it.name as item_name',
                'u.name as item_unit',
                'fo.price',
                'i.received_qty',
                'fo.qty as fo_qty',
                'gr.id as gr_id',
                'gr.number as gr_number',
                'do.number as do_number',
                'ffo.order_number as floor_order_number',
                'ffo_ro.order_number as ro_floor_order_number',
                'fo_creator.nama_lengkap as fo_creator_name',
                'fo_ro_creator.nama_lengkap as fo_ro_creator_name',
                'do_creator.nama_lengkap as do_creator_name',
                'gr_creator.nama_lengkap as gr_creator_name',
                'ffo.created_at as fo_created_at',
                'ffo_ro.created_at as fo_ro_created_at',
                'do.created_at as do_created_at',
                'gr.created_at as gr_created_at',
                DB::raw("'floor_order' as source_type")
            )
            ->get();
        
        foreach ($floorOrderItems as $item) {
            $key = $item->item_name . '|' . ($item->price ?? 0);
            if (!isset($result[$key])) {
                $result[$key] = [
                    'item_name' => $item->item_name,
                    'unit' => $item->item_unit,
                    'price' => (float) ($item->price ?? 0),
                    'total_qty' => 0,
                    'source_type' => 'floor_order',
                    'transactions' => []
                ];
            }
            $result[$key]['total_qty'] += (float) $item->received_qty;
            
            // Add transaction detail
            $result[$key]['transactions'][] = [
                'gr_id' => $item->gr_id,
                'gr_number' => $item->gr_number,
                'do_number' => $item->do_number,
                'floor_order_number' => $item->floor_order_number ?: $item->ro_floor_order_number,
                'fo_creator_name' => $item->fo_creator_name ?: $item->fo_ro_creator_name,
                'do_creator_name' => $item->do_creator_name,
                'gr_creator_name' => $item->gr_creator_name,
                'fo_created_at' => $item->fo_created_at ?: $item->fo_ro_created_at,
                'do_created_at' => $item->do_created_at,
                'gr_created_at' => $item->gr_created_at,
                'fo_qty' => (float) ($item->fo_qty ?? 0),
                'fo_price' => (float) ($item->price ?? 0),
                'received_qty' => (float) $item->received_qty
            ];
        }
        
        // Get Retail Food items
        $retailFoodQuery = DB::table('retail_food as rf')
            ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
            ->leftJoin('items as it', 'rfi.item_name', '=', 'it.name')
            ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereBetween('rf.transaction_date', [$dateFrom, $dateTo])
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereRaw("CASE 
                WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'Main Kitchen'
                WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN 'Chemical'
                WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN 'Stationary'
                WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN 'Marketing'
                WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN 'Main Store'
                ELSE 'Other'
            END = ?", [$categoryName]);
        
        if ($subCategoryName) {
            $retailFoodQuery->where('sc.name', $subCategoryName);
        }
        
        if ($outletId) {
            $retailFoodQuery->where('rf.outlet_id', $outletId);
        }
        
        $retailFoodItems = $retailFoodQuery
            ->leftJoin('users as rf_creator', 'rf.created_by', '=', 'rf_creator.id')
            ->select(
                'rfi.item_name',
                'rfi.unit as item_unit',
                'rfi.price',
                'rfi.qty',
                'rf.id as retail_food_id',
                'rf.retail_number',
                'rf_creator.nama_lengkap as rf_creator_name',
                'rf.created_at as rf_created_at',
                DB::raw("'retail_food' as source_type")
            )
            ->get();
        
        foreach ($retailFoodItems as $item) {
            $key = $item->item_name . '|' . ($item->price ?? 0);
            if (!isset($result[$key])) {
                $result[$key] = [
                    'item_name' => $item->item_name,
                    'unit' => $item->item_unit,
                    'price' => (float) ($item->price ?? 0),
                    'total_qty' => 0,
                    'source_type' => 'retail_food',
                    'transactions' => []
                ];
            }
            $result[$key]['total_qty'] += (float) $item->qty;
            
            // If already exists from floor_order, mark as mixed
            if ($result[$key]['source_type'] === 'floor_order') {
                $result[$key]['source_type'] = 'mixed';
            }
            
            // Add transaction detail for retail food
            if (!isset($result[$key]['transactions'])) {
                $result[$key]['transactions'] = [];
            }
            $result[$key]['transactions'][] = [
                'retail_food_id' => $item->retail_food_id,
                'retail_number' => $item->retail_number,
                'rf_creator_name' => $item->rf_creator_name,
                'rf_created_at' => $item->rf_created_at,
                'qty' => (float) $item->qty,
                'price' => (float) ($item->price ?? 0)
            ];
        }
        
        // Convert to array and sort by item_name, then by price
        $finalResult = array_values($result);
        usort($finalResult, function($a, $b) {
            if ($a['item_name'] === $b['item_name']) {
                return $a['price'] <=> $b['price'];
            }
            return strcmp($a['item_name'], $b['item_name']);
        });
        
        return response()->json($finalResult);
    }

    private function getCardTrend($dateFrom, $dateTo, $type, $outletId = null)
    {
        $dates = [];
        $current = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
        
        $result = [];
        foreach ($dates as $date) {
            $amount = 0;
            
            if ($type === 'total_paid' || $type === 'total_opex') {
                // Get paid amount
                $paidQuery = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->whereDate('nfp.payment_date', $date)
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops');
                
                if ($outletId) {
                    $paidQuery->where('pr.outlet_id', $outletId);
                }
                
                $paidAmount = (float) $paidQuery->sum('nfp.amount');
                
                // Get direct payment
                $directPaidQuery = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                    ->whereDate('nfp.payment_date', $date)
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->whereNotNull('nfp.purchase_requisition_id');
                
                if ($outletId) {
                    $directPaidQuery->where('pr.outlet_id', $outletId);
                }
                
                $directPaidAmount = (float) $directPaidQuery->sum('nfp.amount');
                $amount = $paidAmount + $directPaidAmount;
            }
            
            if ($type === 'retail_non_food' || $type === 'total_opex') {
                $retailQuery = DB::table('retail_non_food')
                    ->whereDate('transaction_date', $date)
                    ->where('status', 'approved');
                
                if ($outletId) {
                    $retailQuery->where('outlet_id', $outletId);
                }
                
                $retailAmount = (float) $retailQuery->sum('total_amount');
                
                if ($type === 'retail_non_food') {
                    $amount = $retailAmount;
                } else {
                    $amount += $retailAmount;
                }
            }
            
            if ($type === 'food' || $type === 'total_opex') {
                // Get Floor Order GR amount for this date
                $floorOrderGRQuery = DB::table('outlet_food_good_receives as gr')
                    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
                    // Join untuk RO Supplier GR
                    ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
                    ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
                    ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
                    ->leftJoin('food_floor_order_items as fo', function($join) {
                        $join->on('i.item_id', '=', 'fo.item_id')
                             ->where(function($q) {
                                 $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                                   ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                             });
                    })
                    ->whereDate('gr.receive_date', $date)
                    ->whereNull('gr.deleted_at');
                
                if ($outletId) {
                    $floorOrderGRQuery->where('gr.outlet_id', $outletId);
                }
                
                $floorOrderAmount = (float) $floorOrderGRQuery->sum(DB::raw('i.received_qty * COALESCE(fo.price, 0)'));
                
                // Get Retail Food amount for this date
                $retailFoodQuery = DB::table('retail_food')
                    ->whereDate('transaction_date', $date)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
                
                if ($outletId) {
                    $retailFoodQuery->where('outlet_id', $outletId);
                }
                
                $retailFoodAmount = (float) $retailFoodQuery->sum('total_amount');
                
                if ($type === 'food') {
                    $amount = $floorOrderAmount + $retailFoodAmount;
                } else {
                    $amount += $floorOrderAmount + $retailFoodAmount;
                }
            }
            
            if ($type === 'unpaid_pr') {
                // Get unpaid PRs created on or before this date
                $unpaidQuery = DB::table('purchase_requisitions as pr')
                    ->whereDate('pr.created_at', '<=', $date)
                    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->where('pr.is_held', false);
                
                if ($outletId) {
                    $unpaidQuery->where('pr.outlet_id', $outletId);
                }
                
                $prs = $unpaidQuery->get();
                $unpaidAmount = 0;
                
                foreach ($prs as $pr) {
                    $poTotal = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->where('poi.source_id', $pr->id)
                        ->where('poo.status', 'approved')
                        ->sum('poi.total');
                    
                    $paidAmount = 0;
                    if ($poTotal > 0) {
                        $poIds = DB::table('purchase_order_ops_items as poi')
                            ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                            ->where('poi.source_type', 'purchase_requisition_ops')
                            ->where('poi.source_id', $pr->id)
                            ->where('poo.status', 'approved')
                            ->pluck('poi.purchase_order_ops_id')
                            ->toArray();
                        
                        $paidAmount = DB::table('non_food_payments')
                            ->whereIn('purchase_order_ops_id', $poIds)
                            ->whereIn('status', ['paid', 'approved'])
                            ->where('status', '!=', 'cancelled')
                            ->whereDate('payment_date', '<=', $date)
                            ->sum('amount');
                    } else {
                        $paidAmount = DB::table('non_food_payments')
                            ->where('purchase_requisition_id', $pr->id)
                            ->whereIn('status', ['paid', 'approved'])
                            ->where('status', '!=', 'cancelled')
                            ->whereDate('payment_date', '<=', $date)
                            ->sum('amount');
                    }
                    
                    $prAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                    $unpaidAmount += max(0, $prAmount - $paidAmount);
                }
                
                $amount = $unpaidAmount;
            }
            
            $result[] = [
                'date' => $date,
                'amount' => $amount
            ];
        }
        
        return $result;
    }

    private function getAllPayments($dateFrom, $dateTo, $outletId = null)
    {
        // Get payments via PO
        $poPaymentsQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as pr_creator', 'pr.created_by', '=', 'pr_creator.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->where('poi.source_type', 'purchase_requisition_ops');
        
        if ($outletId) {
            $poPaymentsQuery->where('pr.outlet_id', $outletId);
        }
        
        $poPayments = $poPaymentsQuery->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.amount',
                'nfp.payment_date',
                'nfp.payment_method',
                'pr.pr_number',
                'poo.id as po_id',
                'o.nama_outlet as outlet_name',
                'pr_creator.nama_lengkap as creator_name',
                'prc.division as category_division',
                'prc.name as category_name',
                DB::raw("'payment' as type")
            )
            ->groupBy('nfp.id', 'nfp.payment_number', 'nfp.amount', 'nfp.payment_date', 'nfp.payment_method', 'pr.pr_number', 'poo.id', 'o.nama_outlet', 'pr_creator.nama_lengkap', 'prc.division', 'prc.name')
            ->get();

        // Get direct payments
        $directPaymentsQuery = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as pr_creator', 'pr.created_by', '=', 'pr_creator.id')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->whereNotNull('nfp.purchase_requisition_id')
            ->whereNull('nfp.purchase_order_ops_id');
        
        if ($outletId) {
            $directPaymentsQuery->where('pr.outlet_id', $outletId);
        }
        
        $directPayments = $directPaymentsQuery->select(
                'nfp.id',
                'nfp.payment_number',
                'nfp.amount',
                'nfp.payment_date',
                'nfp.payment_method',
                'pr.pr_number',
                'pr.id as pr_id',
                'o.nama_outlet as outlet_name',
                'pr_creator.nama_lengkap as creator_name',
                'prc.division as category_division',
                'prc.name as category_name',
                DB::raw("'payment' as type")
            )
            ->get();

        // Combine and get items
        $allPayments = $poPayments->merge($directPayments)->sortByDesc('payment_date');

        foreach ($allPayments as $payment) {
            $items = [];
            
            if (isset($payment->po_id) && $payment->po_id) {
                $items = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->where('poi.purchase_order_ops_id', $payment->po_id)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->select('poi.id', 'poi.item_name', 'poi.quantity as qty', 'poi.unit', 'poi.price', 'poi.total')
                    ->get();
            } elseif (isset($payment->pr_id) && $payment->pr_id) {
                $items = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $payment->pr_id)
                    ->select('id', 'item_name', 'qty', 'unit', 'unit_price as price', 'subtotal as total')
                    ->get();
            }
            
            $payment->items = $items;
        }

        return $allPayments->values();
    }

    private function getAllRetailNonFood($dateFrom, $dateTo, $outletId = null)
    {
        $query = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->where('rnf.status', 'approved');
        
        if ($outletId) {
            $query->where('rnf.outlet_id', $outletId);
        }
        
        $data = $query->leftJoin('users as creator', 'rnf.created_by', '=', 'creator.id')
            ->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount as amount',
                'rnf.transaction_date as payment_date',
                DB::raw("'Retail Non Food' as payment_method"),
                DB::raw("NULL as pr_number"),
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name',
                'prc.division as category_division',
                'prc.name as category_name',
                DB::raw("'retail_non_food' as type")
            )
            ->orderBy('rnf.transaction_date', 'desc')
            ->orderBy('rnf.created_at', 'desc')
            ->get();
        
        // Get items for each retail non food
        foreach ($data as $rnf) {
            $items = DB::table('retail_non_food_items')
                ->where('retail_non_food_id', $rnf->id)
                ->select('id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
                ->get();
            $rnf->items = $items;
        }
        
        return $data;
    }

    private function getAllUnpaidPRs($dateFrom, $dateTo, $outletId = null)
    {
        $query = DB::table('purchase_requisitions as pr')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
            ->where('pr.is_held', false);
        
        if ($outletId) {
            $query->where('pr.outlet_id', $outletId);
        }
        
        $prs = $query->leftJoin('users as creator', 'pr.created_by', '=', 'creator.id')
            ->select(
                'pr.id',
                'pr.pr_number',
                'pr.title',
                'pr.amount',
                'pr.status',
                'pr.created_at',
                'o.nama_outlet as outlet_name',
                'prc.name as category_name',
                'creator.nama_lengkap as creator_name'
            )
            ->orderBy('pr.created_at', 'desc')
            ->get();
        
        $result = [];
        foreach ($prs as $pr) {
            $poTotal = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->where('poi.source_id', $pr->id)
                ->where('poo.status', 'approved')
                ->sum('poi.total');
            
            $paidAmount = 0;
            if ($poTotal > 0) {
                $poIds = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poi.source_id', $pr->id)
                    ->where('poo.status', 'approved')
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
                
                $paidAmount = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIds)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
            } else {
                $paidAmount = DB::table('non_food_payments')
                    ->where('purchase_requisition_id', $pr->id)
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
            }
            
            $prAmount = $poTotal > 0 ? $poTotal : $pr->amount;
            $unpaidAmount = max(0, $prAmount - $paidAmount);
            
            if ($unpaidAmount > 0) {
                $items = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $pr->id)
                    ->select('id', 'item_name', 'qty', 'unit', 'unit_price as price', 'subtotal as total')
                    ->get();
                
                $result[] = (object)[
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'title' => $pr->title,
                    'amount' => (float) $unpaidAmount,
                    'unpaid_amount' => (float) $unpaidAmount,
                    'creator_name' => $pr->creator_name,
                    'payment_date' => $pr->created_at,
                    'created_at' => $pr->created_at,
                    'outlet_name' => $pr->outlet_name,
                    'category_name' => $pr->category_name,
                    'type' => 'unpaid_pr',
                    'items' => $items
                ];
            }
        }
        
        return collect($result);
    }
    
    private function getAllFoodTransactions($dateFrom, $dateTo, $outletId = null)
    {
        $result = [];
        
        // Get Floor Order GR transactions
        $floorOrderGRQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            // Join untuk RO Supplier GR
            ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
            ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
            ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->where(function($q) {
                         $q->whereColumn('fo.floor_order_id', 'do.floor_order_id')
                           ->orWhereColumn('fo.floor_order_id', 'ffo_ro.id');
                     });
            })
            ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('gr.receive_date', [$dateFrom, $dateTo])
            ->whereNull('gr.deleted_at');
        
        if ($outletId) {
            $floorOrderGRQuery->where('gr.outlet_id', $outletId);
        }
        
        $floorOrderGRs = $floorOrderGRQuery->leftJoin('users as creator', 'gr.created_by', '=', 'creator.id')
            ->select(
                'gr.id',
                'gr.number as gr_number',
                'gr.receive_date',
                'do.number as do_number',
                'do.id as do_id',
                'do.source_type as do_source_type',
                'ffo.order_number as floor_order_number',
                'ffo_ro.order_number as ro_floor_order_number',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name',
                DB::raw("SUM(i.received_qty * COALESCE(fo.price, 0)) as amount"),
                DB::raw("'floor_order_gr' as type")
            )
            ->groupBy('gr.id', 'gr.number', 'gr.receive_date', 'do.number', 'do.id', 'do.source_type', 'ffo.order_number', 'ffo_ro.order_number', 'o.nama_outlet', 'creator.nama_lengkap')
            ->get();
        
        foreach ($floorOrderGRs as $gr) {
            // Get payment info for this GR (if exists)
            $payment = DB::table('outlet_payments')
                ->where('gr_id', $gr->id)
                ->whereIn('status', ['paid', 'approved'])
                ->where('status', '!=', 'cancelled')
                ->select('payment_number', 'status')
                ->first();
            
            $paymentNumber = $payment ? $payment->payment_number : null;
            $hasPayment = $payment ? true : false;
            $paymentStatus = $hasPayment ? 'paid' : 'unpaid';
            
            // Build combined number string
            $numberParts = [];
            // Floor Order number (bisa dari do.floor_order_id atau dari ro_supplier_gr)
            $floorOrderNum = $gr->floor_order_number ?: $gr->ro_floor_order_number;
            if ($floorOrderNum) {
                $numberParts[] = 'FO: ' . $floorOrderNum;
            }
            if ($gr->do_number) {
                $numberParts[] = 'DO: ' . $gr->do_number;
            }
            if ($gr->gr_number) {
                $numberParts[] = 'GR: ' . $gr->gr_number;
            }
            if ($paymentNumber) {
                $numberParts[] = 'Payment: ' . $paymentNumber;
            }
            
            $combinedNumber = !empty($numberParts) ? implode(' | ', $numberParts) : '-';
            
            // Get items for this GR
            $grData = DB::table('outlet_food_good_receives as gr')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_good_receives as gr_ro', 'do.ro_supplier_gr_id', '=', 'gr_ro.id')
                ->leftJoin('purchase_order_foods as po', 'gr_ro.po_id', '=', 'po.id')
                ->leftJoin('food_floor_orders as ffo_ro', 'po.source_id', '=', 'ffo_ro.id')
                ->where('gr.id', $gr->id)
                ->select('do.floor_order_id', 'ffo_ro.id as ro_floor_order_id')
                ->first();
            
            $floorOrderId = $grData->floor_order_id ?: $grData->ro_floor_order_id;
            
            $items = DB::table('outlet_food_good_receive_items as i')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->leftJoin('food_floor_order_items as fo', function($join) use ($floorOrderId) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->where('fo.floor_order_id', '=', $floorOrderId);
                })
                ->leftJoin('units as u', 'i.unit_id', '=', 'u.id')
                ->where('i.outlet_food_good_receive_id', $gr->id)
                ->select(
                    'i.id',
                    'it.name as item_name',
                    'i.received_qty as qty',
                    'u.name as unit',
                    'fo.price as price',
                    DB::raw('i.received_qty * COALESCE(fo.price, 0) as total')
                )
                ->get();
            
            $result[] = (object)[
                'id' => 'gr_' . $gr->id,
                'number' => $combinedNumber,
                'gr_number' => $gr->gr_number,
                'do_number' => $gr->do_number,
                'floor_order_number' => $gr->floor_order_number,
                'payment_number' => $paymentNumber,
                'has_payment' => $hasPayment,
                'payment_status' => $paymentStatus,
                'amount' => (float) $gr->amount,
                'payment_date' => $gr->receive_date,
                'transaction_date' => $gr->receive_date,
                'receive_date' => $gr->receive_date,
                'outlet_name' => $gr->outlet_name,
                'creator_name' => $gr->creator_name,
                'type' => 'floor_order_gr',
                'items' => $items
            ];
        }
        
        // Get Retail Food transactions
        $retailFoodQuery = DB::table('retail_food as rf')
            ->leftJoin('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as creator', 'rf.created_by', '=', 'creator.id')
            ->whereBetween('rf.transaction_date', [$dateFrom, $dateTo])
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at');
        
        if ($outletId) {
            $retailFoodQuery->where('rf.outlet_id', $outletId);
        }
        
        $retailFoods = $retailFoodQuery->select(
                'rf.id',
                'rf.retail_number',
                'rf.transaction_date',
                'rf.total_amount as amount',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name',
                DB::raw("'retail_food' as type")
            )
            ->orderBy('rf.transaction_date', 'desc')
            ->orderBy('rf.created_at', 'desc')
            ->get();
        
        foreach ($retailFoods as $rf) {
            // Get items for this retail food
            $items = DB::table('retail_food_items')
                ->where('retail_food_id', $rf->id)
                ->select('id', 'item_name', 'qty', 'unit', 'price', 'subtotal as total')
                ->get();
            
            $result[] = (object)[
                'id' => 'rf_' . $rf->id,
                'number' => $rf->retail_number,
                'amount' => (float) $rf->amount,
                'payment_date' => $rf->transaction_date,
                'transaction_date' => $rf->transaction_date,
                'outlet_name' => $rf->outlet_name,
                'creator_name' => $rf->creator_name,
                'type' => 'retail_food',
                'items' => $items
            ];
        }
        
        return collect($result)->sortByDesc(function($item) {
            return $item->payment_date ?? $item->transaction_date ?? $item->receive_date;
        })->values();
    }
}

