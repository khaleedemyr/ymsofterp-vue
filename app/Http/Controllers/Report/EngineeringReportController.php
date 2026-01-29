<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\OrderDetailExport;
use App\Exports\ItemEngineeringMultiSheetExport;
use App\Models\RetailFood;
use App\Models\RetailNonFood;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionCategory;

/**
 * Engineering Report Controller
 * 
 * Handles engineering, operational, and item reports
 * Split from ReportController for better organization and performance
 * 
 * Functions:
 * - reportItemEngineering: Get item engineering report (items + modifiers)
 * - exportItemEngineering: Export item engineering to Excel multi-sheet
 * - exportOrderDetail: Export order detail
 * - apiOutletExpenses: Get outlet expenses (retail food + non-food with budget info)
 */
class EngineeringReportController extends Controller
{
    use ReportHelperTrait;
    
    /**
     * Report Item Engineering
     * 
     * Returns items and modifiers engineering data
     * Grouped by category, sorted by quantity sold
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportItemEngineering(Request $request)
    {
        $outlet = $request->input('outlet');
        $region = $request->input('region');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Query untuk items dengan category
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->select([
                'order_items.item_name',
                'categories.name as category_name',
                DB::raw('SUM(order_items.qty) as qty_terjual'),
                DB::raw('MAX(order_items.price) as harga_jual'),
                DB::raw('SUM(order_items.qty * order_items.price) as subtotal'),
            ]);
        
        // Filter by outlet or region
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        } elseif ($region) {
            // If region is selected, get all outlets in that region (using cached helper)
            $outletCodes = $this->getCachedOutletQrCodesByRegion($region);
            $query->whereIn('orders.kode_outlet', $outletCodes);
        }
        if ($dateFrom) {
            $query->whereDate('orders.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('orders.created_at', '<=', $dateTo);
        }
        $query->groupBy('order_items.item_name', 'categories.name')
            ->orderBy('categories.name')
            ->orderByDesc('qty_terjual');

        $items = $query->get();
        $grand_total = $items->sum('subtotal');

        // Group items by category
        $itemsByCategory = $items->groupBy('category_name')->map(function($categoryItems) {
            return [
                'items' => $categoryItems,
                'total_qty' => $categoryItems->sum('qty_terjual'),
                'total_subtotal' => $categoryItems->sum('subtotal'),
            ];
        });

        // MODIFIER ENGINEERING
        $orderItems = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(['order_items.modifiers', 'order_items.qty'])
            ->when($outlet, function($q) use ($outlet) {
                $q->where('orders.kode_outlet', $outlet);
            })
            ->when($region && !$outlet, function($q) use ($region) {
                // If region is selected and no specific outlet, get all outlets in that region (using cached helper)
                $outletCodes = $this->getCachedOutletQrCodesByRegion($region);
                $q->whereIn('orders.kode_outlet', $outletCodes);
            })
            ->when($dateFrom, function($q) use ($dateFrom) {
                $q->whereDate('orders.created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function($q) use ($dateTo) {
                $q->whereDate('orders.created_at', '<=', $dateTo);
            })
            ->get();
        $modifierMap = [];
        foreach ($orderItems as $oi) {
            if (!$oi->modifiers) continue;
            $mods = json_decode($oi->modifiers, true);
            if (!is_array($mods)) continue;
            foreach ($mods as $group) {
                if (is_array($group)) {
                    foreach ($group as $name => $qty) {
                        if (!isset($modifierMap[$name])) $modifierMap[$name] = 0;
                        $modifierMap[$name] += $qty;
                    }
                }
            }
        }
        $modifiers = [];
        foreach ($modifierMap as $name => $qty) {
            $modifiers[] = [ 'name' => $name, 'qty' => $qty ];
        }
        usort($modifiers, function($a, $b) { return $b['qty'] <=> $a['qty']; });

        return response()->json([
            'items' => $items, 
            'items_by_category' => $itemsByCategory,
            'modifiers' => $modifiers, 
            'grand_total' => $grand_total
        ]);
    }

    /**
     * Export Order Detail
     * 
     * Export orders detail for a specific date and outlet
     * 
     * @param Request $request
     * @return \Maatwebsite\Excel\Excel
     */
    public function exportOrderDetail(Request $request)
    {
        $outlet = $request->input('outlet');
        $date = $request->input('date');
        // Query orders for the given date and outlet
        $query = DB::table('orders')
            ->select([
                'orders.id',
                'orders.nomor',
                'orders.table',
                'orders.pax',
                'orders.total',
                'orders.discount',
                'orders.cashback',
                'orders.service',
                'orders.pb1',
                'orders.grand_total',
                'orders.status',
            ]);
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        }
        if ($date) {
            $query->whereDate('orders.created_at', $date);
        }
        $orders = $query->orderBy('orders.created_at')->get();
        return new OrderDetailExport($orders, $date);
    }

    /**
     * Export Item Engineering
     * 
     * Export item engineering report to Excel with multiple sheets
     * Contains items and modifiers data
     * 
     * @param Request $request
     * @return \Maatwebsite\Excel\Excel
     */
    public function exportItemEngineering(Request $request)
    {
        $outlet = $request->input('outlet');
        $region = $request->input('region');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $outletName = null;
        if ($outlet) {
            // Get outlet name by QR code (using cached helper)
            $outletName = $this->getCachedOutletNameByQrCode($outlet);
        }
        
        // Query untuk items dengan category (sama seperti reportItemEngineering)
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->select([
                'order_items.item_name',
                'categories.name as category_name',
                DB::raw('SUM(order_items.qty) as qty_terjual'),
                DB::raw('MAX(order_items.price) as harga_jual'),
                DB::raw('SUM(order_items.qty * order_items.price) as subtotal'),
            ]);
        // Filter by outlet or region
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        } elseif ($region) {
            // If region is selected, get all outlets in that region (using cached helper)
            $outletCodes = $this->getCachedOutletQrCodesByRegion($region);
            $query->whereIn('orders.kode_outlet', $outletCodes);
        }
        if ($dateFrom) {
            $query->whereDate('orders.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('orders.created_at', '<=', $dateTo);
        }
        $query->groupBy('order_items.item_name', 'categories.name')
            ->orderBy('categories.name')
            ->orderByDesc('qty_terjual');
        $items = $query->get();
        
        // Get modifiers (same logic as in reportItemEngineering)
        $orderItems = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(['order_items.modifiers', 'order_items.qty'])
            ->when($outlet, function($q) use ($outlet) {
                $q->where('orders.kode_outlet', $outlet);
            })
            ->when($region && !$outlet, function($q) use ($region) {
                // If region is selected and no specific outlet, get all outlets in that region (using cached helper)
                $outletCodes = $this->getCachedOutletQrCodesByRegion($region);
                $q->whereIn('orders.kode_outlet', $outletCodes);
            })
            ->when($dateFrom, function($q) use ($dateFrom) {
                $q->whereDate('orders.created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function($q) use ($dateTo) {
                $q->whereDate('orders.created_at', '<=', $dateTo);
            })
            ->get();
        $modifierMap = [];
        foreach ($orderItems as $oi) {
            if (!$oi->modifiers) continue;
            $mods = json_decode($oi->modifiers, true);
            if (!is_array($mods)) continue;
            foreach ($mods as $group) {
                if (is_array($group)) {
                    foreach ($group as $name => $qty) {
                        if (!isset($modifierMap[$name])) $modifierMap[$name] = 0;
                        $modifierMap[$name] += $qty;
                    }
                }
            }
        }
        $modifiers = [];
        foreach ($modifierMap as $name => $qty) {
            $modifiers[] = [ 'name' => $name, 'qty' => $qty ];
        }
        usort($modifiers, function($a, $b) { return $b['qty'] <=> $a['qty']; });
        return new ItemEngineeringMultiSheetExport($items, $modifiers, $outletName, $dateFrom, $dateTo);
    }

    /**
     * API Outlet Expenses
     * 
     * Get retail food and retail non-food expenses for an outlet on a specific date
     * Includes budget information and calculations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiOutletExpenses(Request $request)
    {
        Log::info('apiOutletExpenses called', [
            'outlet_id' => $request->input('outlet_id'),
            'date' => $request->input('date'),
        ]);
        
        $user = auth()->user();
        $outletId = $request->input('outlet_id');
        $date = $request->input('date');
        
        // Validasi: user hanya bisa mengakses data outlet mereka sendiri, kecuali superuser (id_outlet = 1)
        if ($user->id_outlet != 1 && $user->id_outlet != $outletId) {
            Log::warning('apiOutletExpenses: unauthorized access attempt', [
                'user_id_outlet' => $user->id_outlet,
                'requested_outlet_id' => $outletId,
            ]);
            return response()->json([
                'retail_food' => [],
                'retail_non_food' => [],
            ]);
        }
        
        // Retail Food - hanya yang cash
        $retailFoods = RetailFood::with(['items', 'invoices'])
            ->where('outlet_id', $outletId)
            ->whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->where('payment_method', 'cash')
            ->get()
            ->map(function($rf) {
                return [
                    'id' => $rf->id,
                    'retail_number' => $rf->retail_number,
                    'transaction_date' => $rf->transaction_date,
                    'total_amount' => $rf->total_amount,
                    'notes' => $rf->notes,
                    'items' => $rf->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name ?? $item->nama_barang,
                            'qty' => $item->qty,
                            'harga_barang' => $item->harga_barang,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'invoices' => $rf->invoices->map(function($inv) {
                        return [
                            'file_path' => $inv->file_path
                        ];
                    }),
                ];
            });
        // Retail Non Food - hanya yang payment_method bukan contra_bon
        $retailNonFoods = RetailNonFood::with(['items', 'invoices', 'categoryBudget'])
            ->where('outlet_id', $outletId)
            ->whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->where(function($q) {
                $q->where('payment_method', '!=', 'contra_bon')
                  ->orWhereNull('payment_method');
            })
            ->get()
            ->map(function($rnf) use ($outletId) {
                $budgetInfo = null;
                
                // Get budget info if category_budget_id exists (with error handling)
                // Use same logic as Purchase Requisition Ops
                try {
                    if ($rnf->category_budget_id) {
                        $categoryId = $rnf->category_budget_id;
                        $transactionDate = \Carbon\Carbon::parse($rnf->transaction_date);
                        $year = $transactionDate->year;
                        $month = $transactionDate->month;
                        
                        // Get date range for the month
                        $dateFrom = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
                        $dateTo = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
                        
                        // Get category budget (same as Purchase Requisition Ops)
                        $category = PurchaseRequisitionCategory::find($categoryId);
                        
                        if ($category) {
                            // Use same logic as Purchase Requisition Ops
                            $isGlobalBudget = $category->isGlobalBudget();
                            $budgetType = $isGlobalBudget ? 'GLOBAL' : 'PER_OUTLET';
                            $categoryBudget = $category->budget_limit ?? 0;
                            
                            // Get PR IDs in this category for the month (same as Purchase Requisition Ops)
                            // Support both old structure (category at PR level) and new structure (category at items level)
                            $prIds = DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->where('pr.is_held', false)
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            // Get PO IDs linked to PRs in this category
                            $poIdsInCategory = !empty($prIds) ? DB::table('purchase_order_ops_items as poi')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poi.source_id', $prIds)
                                ->distinct()
                                ->pluck('poi.purchase_order_ops_id')
                                ->toArray() : [];
                            
                            // Get paid amount from non_food_payments (BUDGET IS MONTHLY - filter by payment_date)
                            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
                            $paidAmountFromPo = !empty($poIdsInCategory) ? DB::table('non_food_payments as nfp')
                                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                                ->where('nfp.status', 'paid')
                                ->where('nfp.status', '!=', 'cancelled')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->sum('nfp.amount') : 0;
                            
                            // Get Retail Non Food amounts (BUDGET IS MONTHLY - filter by transaction_date)
                            $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $category->id)
                                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                                ->where('status', 'approved')
                                ->sum('total_amount');
                            
                            // Get unpaid PR data (same as Purchase Requisition Ops)
                            $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('pr.id', '=', 'poi.source_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
                                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED'])
                                ->where('pr.is_held', false)
                                ->whereNull('poo.id')
                                ->whereNull('nfp.id')
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
                            $prUnpaidAmount = 0;
                            foreach ($allPrs as $pr) {
                                $prUnpaidAmount += $pr->amount;
                            }
                            
                            // Get unpaid PO data (same as Purchase Requisition Ops)
                            $allPOs = DB::table('purchase_order_ops as poo')
                                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', function($join) {
                                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                                         ->where(function($q) {
                                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                               ->orWhere(function($q2) {
                                                   $q2->whereNull('poi.pr_ops_item_id')
                                                      ->whereColumn('poi.item_name', 'pri.item_name');
                                               });
                                         });
                                })
                                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved'])
                                ->whereNull('nfp.id')
                                ->groupBy('poo.id')
                                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                                ->get();
                            
                            $poUnpaidAmount = 0;
                            foreach ($allPOs as $po) {
                                $poUnpaidAmount += $po->po_total ?? 0;
                            }
                            
                            // Calculate unpaid NFP (same as Purchase Requisition Ops)
                            // Case 1: NFP langsung dari PR
                            $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->where('pr.is_held', false)
                                ->whereNull('nfp.purchase_order_ops_id')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            // Case 2: NFP melalui PO
                            $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', function($join) {
                                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                                         ->where(function($q) {
                                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                               ->orWhere(function($q2) {
                                                   $q2->whereNull('poi.pr_ops_item_id')
                                                      ->whereColumn('poi.item_name', 'pri.item_name');
                                               });
                                         });
                                })
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->where('pr.is_held', false)
                                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpUnpaidAmount = $nfpUnpaidFromPr + $nfpUnpaidFromPo;
                            
                            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
                            $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
                            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
                            $categoryUsedAmount = $paidAmount + $unpaidAmount;
                            
                            $budgetInfo = [
                                'budget_type' => $budgetType,
                                'current_year' => $year,
                                'current_month' => $month,
                                'category_budget' => $categoryBudget,
                                'category_used_amount' => $categoryUsedAmount,
                                'category_remaining_amount' => $categoryBudget - $categoryUsedAmount,
                                'paid_amount' => $paidAmount,
                                'unpaid_amount' => $unpaidAmount,
                                'retail_non_food_approved' => $retailNonFoodApproved,
                                'category_name' => $category->name,
                                'division_name' => $category->division ?? null,
                            ];
                        } else {
                            $budgetInfo = null;
                        }
                    } else {
                        // If category_budget_id exists but category not found, still return basic info
                        $budgetInfo = [
                            'budget_type' => 'TOTAL',
                            'budget_amount' => 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'category_used_amount' => 0,
                            'remaining_budget' => 0,
                            'category_name' => null,
                        ];
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the transaction
                    Log::error('Error calculating budget info for RNF: ' . $e->getMessage(), [
                        'rnf_id' => $rnf->id,
                        'category_budget_id' => $rnf->category_budget_id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Still return budget_info with null values if category_budget_id exists
                    if ($rnf->category_budget_id) {
                        $budgetInfo = [
                            'budget_type' => 'TOTAL',
                            'budget_amount' => 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'category_used_amount' => 0,
                            'remaining_budget' => 0,
                            'category_name' => null,
                            'error' => 'Error calculating budget info',
                        ];
                    } else {
                        $budgetInfo = null;
                    }
                }
                
                return [
                    'id' => $rnf->id,
                    'retail_number' => $rnf->retail_number,
                    'transaction_date' => $rnf->transaction_date,
                    'total_amount' => $rnf->total_amount,
                    'notes' => $rnf->notes,
                    'category_budget_id' => $rnf->category_budget_id,
                    'budget_info' => $budgetInfo,
                    'items' => $rnf->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'qty' => $item->qty,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'invoices' => $rnf->invoices->map(function($inv) {
                        return [
                            'file_path' => $inv->file_path
                        ];
                    }),
                ];
            });
        // Log budget info for debugging
        $retailNonFoodsWithBudget = $retailNonFoods->filter(function($rnf) {
            return !empty($rnf['budget_info']);
        });
        
        Log::info('apiOutletExpenses result', [
            'retail_food_count' => $retailFoods->count(),
            'retail_non_food_count' => $retailNonFoods->count(),
            'retail_non_food_with_budget_count' => $retailNonFoodsWithBudget->count(),
            'retail_food_ids' => $retailFoods->pluck('id'),
            'retail_non_food_ids' => $retailNonFoods->pluck('id'),
            'retail_non_food_budget_info' => $retailNonFoods->map(function($rnf) {
                return [
                    'id' => $rnf['id'],
                    'retail_number' => $rnf['retail_number'],
                    'category_budget_id' => $rnf['category_budget_id'] ?? null,
                    'has_budget_info' => !empty($rnf['budget_info']),
                    'budget_info' => $rnf['budget_info'] ?? null,
                ];
            }),
        ]);
        
        return response()->json([
            'retail_food' => $retailFoods,
            'retail_non_food' => $retailNonFoods,
        ]);
    }
}
