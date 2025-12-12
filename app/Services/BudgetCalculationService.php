<?php

namespace App\Services;

use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use Illuminate\Support\Facades\DB;

class BudgetCalculationService
{
    /**
     * Calculate budget information for a category and outlet
     * 
     * @param int $categoryId
     * @param int|null $outletId Required for PER_OUTLET budget
     * @param string|null $dateFrom Default: first day of current month
     * @param string|null $dateTo Default: last day of current month
     * @param float|null $currentAmount Amount being added (for validation)
     * @return array
     */
    public function getBudgetInfo(
        int $categoryId,
        ?int $outletId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?float $currentAmount = 0
    ): array {
        // Set default dates
        if (!$dateFrom) {
            $dateFrom = date('Y-m-01');
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-t');
        }

        $year = date('Y', strtotime($dateFrom));
        $month = date('m', strtotime($dateFrom));

        // Get category
        $category = PurchaseRequisitionCategory::find($categoryId);
        if (!$category) {
            return [
                'success' => false,
                'message' => 'Category not found'
            ];
        }

        // Calculate based on budget type
        if ($category->isGlobalBudget()) {
            return $this->calculateGlobalBudget($category, $year, $month, $dateFrom, $dateTo, $currentAmount);
        } else if ($category->isPerOutletBudget()) {
            if (!$outletId) {
                return [
                    'success' => false,
                    'message' => 'Outlet ID is required for per-outlet budget'
                ];
            }
            return $this->calculatePerOutletBudget($category, $outletId, $year, $month, $dateFrom, $dateTo, $currentAmount);
        }

        return [
            'success' => false,
            'message' => 'Unknown budget type'
        ];
    }

    /**
     * Calculate GLOBAL budget (all outlets combined)
     */
    private function calculateGlobalBudget(
        PurchaseRequisitionCategory $category,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo,
        float $currentAmount
    ): array {
        $categoryId = $category->id;
        $categoryBudget = $category->budget_limit;

        // Get Retail Non Food
        $retailNonFoodApproved = DB::table('retail_non_food')
            ->where('category_budget_id', $categoryId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->sum('total_amount');

        // Get paid amount from non_food_payments (GLOBAL - sum all outlets)
        $paidAmountFromPo = $this->calculatePaidAmountFromPo($categoryId, null, $year, $month, $dateFrom, $dateTo);

        // Get unpaid amounts
        $prUnpaidAmount = $this->calculatePrUnpaidAmount($categoryId, null, $year, $month);
        $poUnpaidAmount = $this->calculatePoUnpaidAmount($categoryId, null, $year, $month);
        $nfpUnpaidAmount = $this->calculateNfpUnpaidAmount($categoryId, null, $year, $month, $dateFrom, $dateTo);

        // Get NFP breakdown
        $nfpSubmittedAmount = $this->calculateNfpSubmittedAmount($categoryId, null, $year, $month, $dateFrom, $dateTo);
        $nfpApprovedAmount = $this->calculateNfpApprovedAmount($categoryId, null, $year, $month, $dateFrom, $dateTo);
        $nfpPaidAmount = $paidAmountFromPo;

        // Calculate totals
        $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
        $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
        $categoryUsedAmount = $paidAmount + $unpaidAmount;

        $totalWithCurrent = $categoryUsedAmount + $currentAmount;
        $remainingAfterCurrent = $categoryBudget - $totalWithCurrent;

        return [
            'success' => true,
            'budget_type' => 'GLOBAL',
            'category_budget' => $categoryBudget,
            'category_used_amount' => $categoryUsedAmount,
            'current_amount' => $currentAmount,
            'total_with_current' => $totalWithCurrent,
            'category_remaining_amount' => $categoryBudget - $categoryUsedAmount,
            'remaining_after_current' => $remainingAfterCurrent,
            'exceeds_budget' => $totalWithCurrent > $categoryBudget,
            'breakdown' => [
                'pr_unpaid' => $prUnpaidAmount,
                'po_unpaid' => $poUnpaidAmount,
                'nfp_submitted' => $nfpSubmittedAmount,
                'nfp_approved' => $nfpApprovedAmount,
                'nfp_paid' => $nfpPaidAmount,
                'retail_non_food' => $retailNonFoodApproved,
            ],
        ];
    }

    /**
     * Calculate PER_OUTLET budget (specific outlet)
     */
    private function calculatePerOutletBudget(
        PurchaseRequisitionCategory $category,
        int $outletId,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo,
        float $currentAmount
    ): array {
        $categoryId = $category->id;

        // Get outlet budget allocation
        $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
            ->where('outlet_id', $outletId)
            ->first();

        if (!$outletBudget) {
            return [
                'success' => false,
                'message' => 'Outlet budget not configured for this category'
            ];
        }

        // Get Retail Non Food for this outlet
        $outletRetailNonFoodApproved = DB::table('retail_non_food')
            ->where('category_budget_id', $categoryId)
            ->where('outlet_id', $outletId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->sum('total_amount');

        // Get paid amount from non_food_payments for this outlet (with proportion)
        $paidAmountFromPo = $this->calculatePaidAmountFromPo($categoryId, $outletId, $year, $month, $dateFrom, $dateTo);

        // Get unpaid amounts for this outlet
        $prUnpaidAmount = $this->calculatePrUnpaidAmount($categoryId, $outletId, $year, $month);
        $poUnpaidAmount = $this->calculatePoUnpaidAmount($categoryId, $outletId, $year, $month);
        $nfpUnpaidAmount = $this->calculateNfpUnpaidAmount($categoryId, $outletId, $year, $month, $dateFrom, $dateTo);

        // Get NFP breakdown for this outlet
        $nfpSubmittedAmount = $this->calculateNfpSubmittedAmount($categoryId, $outletId, $year, $month, $dateFrom, $dateTo);
        $nfpApprovedAmount = $this->calculateNfpApprovedAmount($categoryId, $outletId, $year, $month, $dateFrom, $dateTo);
        $nfpPaidAmount = $paidAmountFromPo;

        // Calculate totals
        $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
        $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
        $outletUsedAmount = $paidAmount + $unpaidAmount;

        $totalWithCurrent = $outletUsedAmount + $currentAmount;
        $remainingAfterCurrent = $outletBudget->allocated_budget - $totalWithCurrent;

        return [
            'success' => true,
            'budget_type' => 'PER_OUTLET',
            'category_budget' => $category->budget_limit,
            'outlet_budget' => $outletBudget->allocated_budget,
            'outlet_used_amount' => $outletUsedAmount,
            'current_amount' => $currentAmount,
            'total_with_current' => $totalWithCurrent,
            'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
            'remaining_after_current' => $remainingAfterCurrent,
            'exceeds_budget' => $totalWithCurrent > $outletBudget->allocated_budget,
            'outlet_info' => [
                'id' => $outletBudget->outlet_id,
                'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
            ],
            'breakdown' => [
                'pr_unpaid' => $prUnpaidAmount,
                'po_unpaid' => $poUnpaidAmount,
                'nfp_submitted' => $nfpSubmittedAmount,
                'nfp_approved' => $nfpApprovedAmount,
                'nfp_paid' => $nfpPaidAmount,
                'retail_non_food' => $outletRetailNonFoodApproved,
            ],
        ];
    }

    /**
     * Calculate paid amount from PO (with proportion for PER_OUTLET)
     */
    private function calculatePaidAmountFromPo(
        int $categoryId,
        ?int $outletId,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo
    ): float {
        // Get PO IDs linked to PRs
        $poIdsInCategory = $this->getPoIdsInCategory($categoryId, $outletId, $year, $month);

        $paidAmountFromPo = 0;
        if (!empty($poIdsInCategory)) {
            foreach ($poIdsInCategory as $poId) {
                // Get PO items for this outlet/category (if outletId provided, calculate proportion)
                $outletPoItemIds = $this->getOutletPoItems($poId, $categoryId, $outletId);
                
                // Get payment for this PO
                $poPayment = DB::table('non_food_payments')
                    ->where('purchase_order_ops_id', $poId)
                    ->where('status', 'paid')
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('payment_date', [$dateFrom, $dateTo])
                    ->first();
                
                if ($poPayment) {
                    // Verify PO is still approved
                    $poStatus = DB::table('purchase_order_ops')
                        ->where('id', $poId)
                        ->value('status');
                    
                    if ($poStatus === 'approved') {
                        if ($outletId) {
                            // PER_OUTLET: Calculate proportion
                            $poTotalItems = DB::table('purchase_order_ops_items')
                                ->where('purchase_order_ops_id', $poId)
                                ->sum('total');
                            
                            if ($poTotalItems > 0) {
                                $outletPoItemsTotal = array_sum($outletPoItemIds);
                                $proportion = $outletPoItemsTotal / $poTotalItems;
                                $paidAmountFromPo += $poPayment->amount * $proportion;
                            }
                        } else {
                            // GLOBAL: Sum all payments
                            $paidAmountFromPo += $poPayment->amount;
                        }
                    }
                }
            }
        }

        return $paidAmountFromPo;
    }

    /**
     * Get PO IDs in category (filtered by PR created_at month)
     */
    private function getPoIdsInCategory(int $categoryId, ?int $outletId, int $year, int $month): array
    {
        $query = DB::table('purchase_order_ops_items as poi')
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
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false);

        // Filter by category and outlet
        if ($outletId) {
            // PER_OUTLET: Filter by outlet
            $query->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            // GLOBAL: Filter by category only
            $query->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        return $query->distinct()
            ->pluck('poi.purchase_order_ops_id')
            ->toArray();
    }

    /**
     * Get PO items for specific outlet/category
     */
    private function getOutletPoItems(int $poId, int $categoryId, ?int $outletId): array
    {
        $query = DB::table('purchase_order_ops_items as poi')
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
            ->where('poi.purchase_order_ops_id', $poId)
            ->where('poi.source_type', 'purchase_requisition_ops');

        if ($outletId) {
            // PER_OUTLET: Filter by outlet and category
            $query->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            // GLOBAL: Filter by category only
            $query->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        return $query->pluck('poi.total')->toArray();
    }

    /**
     * Calculate PR Unpaid Amount
     */
    private function calculatePrUnpaidAmount(int $categoryId, ?int $outletId, int $year, int $month): float
    {
        $query = DB::table('purchase_requisitions as pr')
            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
            ->leftJoin('purchase_order_ops_items as poi', function($join) {
                $join->on('pr.id', '=', 'poi.source_id')
                     ->where('poi.source_type', '=', 'purchase_requisition_ops');
            })
            ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED'])
            ->where('pr.is_held', false)
            ->whereNull('poo.id')
            ->whereNull('nfp.id');

        // Filter by category and outlet
        if ($outletId) {
            $query->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $query->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $prIds = $query->distinct()->pluck('pr.id')->toArray();
        $allPrs = DB::table('purchase_requisitions')->whereIn('id', $prIds)->get();

        $prUnpaidAmount = 0;
        foreach ($allPrs as $pr) {
            if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                // PR Ops: Calculate based on items
                if ($outletId) {
                    $outletItemsSubtotal = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('outlet_id', $outletId)
                        ->where('category_id', $categoryId)
                        ->sum('subtotal');
                    $prUnpaidAmount += $outletItemsSubtotal ?? 0;
                } else {
                    $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('category_id', $categoryId)
                        ->sum('subtotal');
                    $prUnpaidAmount += $categoryItemsSubtotal ?? 0;
                }
            } else {
                // Other modes: Use PR amount
                $prUnpaidAmount += $pr->amount;
            }
        }

        return $prUnpaidAmount;
    }

    /**
     * Calculate PO Unpaid Amount
     */
    private function calculatePoUnpaidAmount(int $categoryId, ?int $outletId, int $year, int $month): float
    {
        $query = DB::table('purchase_order_ops as poo')
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
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereIn('poo.status', ['submitted', 'approved'])
            ->whereNull('nfp.id');

        // Filter by category and outlet
        if ($outletId) {
            $query->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $query->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $allPOs = $query->groupBy('poo.id')
            ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
            ->get();

        $poUnpaidAmount = 0;
        foreach ($allPOs as $po) {
            $poUnpaidAmount += $po->po_total ?? 0;
        }

        return $poUnpaidAmount;
    }

    /**
     * Calculate NFP Unpaid Amount
     */
    private function calculateNfpUnpaidAmount(
        int $categoryId,
        ?int $outletId,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo
    ): float {
        // NFP from PR (direct)
        $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['pending', 'approved'])
            ->where('nfp.status', '!=', 'cancelled');

        if ($outletId) {
            $nfpUnpaidFromPr->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $nfpUnpaidFromPr->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $nfpUnpaidFromPrAmount = $nfpUnpaidFromPr->sum('nfp.amount');

        // NFP from PO
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
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['pending', 'approved'])
            ->where('nfp.status', '!=', 'cancelled');

        if ($outletId) {
            $nfpUnpaidFromPo->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $nfpUnpaidFromPo->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $nfpUnpaidFromPoAmount = $nfpUnpaidFromPo->sum('nfp.amount');

        return ($nfpUnpaidFromPrAmount ?? 0) + ($nfpUnpaidFromPoAmount ?? 0);
    }

    /**
     * Calculate NFP Submitted Amount
     */
    private function calculateNfpSubmittedAmount(
        int $categoryId,
        ?int $outletId,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo
    ): float {
        // NFP from PR (direct) - status 'submitted'
        $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->where('nfp.status', 'submitted')
            ->where('nfp.status', '!=', 'cancelled');

        if ($outletId) {
            $nfpSubmittedFromPr->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $nfpSubmittedFromPr->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $nfpSubmittedFromPrAmount = $nfpSubmittedFromPr->sum('nfp.amount');

        // NFP from PO - status 'submitted'
        $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
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
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->where('nfp.status', 'submitted')
            ->where('nfp.status', '!=', 'cancelled');

        if ($outletId) {
            $nfpSubmittedFromPo->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $nfpSubmittedFromPo->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $nfpSubmittedFromPoAmount = $nfpSubmittedFromPo->sum('nfp.amount');

        return ($nfpSubmittedFromPrAmount ?? 0) + ($nfpSubmittedFromPoAmount ?? 0);
    }

    /**
     * Calculate NFP Approved Amount (unpaid, status = 'approved')
     */
    private function calculateNfpApprovedAmount(
        int $categoryId,
        ?int $outletId,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo
    ): float {
        // NFP from PR (direct) - status 'approved'
        $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
            ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->whereNull('nfp.purchase_order_ops_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->where('nfp.status', 'approved')
            ->where('nfp.status', '!=', 'cancelled');

        if ($outletId) {
            $nfpApprovedFromPr->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $nfpApprovedFromPr->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $nfpApprovedFromPrAmount = $nfpApprovedFromPr->sum('nfp.amount');

        // NFP from PO - status 'approved'
        $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
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
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->whereNotNull('nfp.purchase_order_ops_id')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->where('nfp.status', 'approved')
            ->where('nfp.status', '!=', 'cancelled');

        if ($outletId) {
            $nfpApprovedFromPo->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            $nfpApprovedFromPo->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        $nfpApprovedFromPoAmount = $nfpApprovedFromPo->sum('nfp.amount');

        return ($nfpApprovedFromPrAmount ?? 0) + ($nfpApprovedFromPoAmount ?? 0);
    }

    /**
     * Validate if current amount exceeds budget
     * 
     * @param int $categoryId
     * @param int|null $outletId
     * @param float $currentAmount
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int|null $excludePrId PR ID to exclude from calculation (for update scenario)
     * @return array ['valid' => bool, 'message' => string, 'budget_info' => array]
     */
    public function validateBudget(
        int $categoryId,
        ?int $outletId,
        float $currentAmount,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $excludePrId = null
    ): array {
        $budgetInfo = $this->getBudgetInfo($categoryId, $outletId, $dateFrom, $dateTo, $currentAmount);

        if (!$budgetInfo['success']) {
            return [
                'valid' => false,
                'message' => $budgetInfo['message'] ?? 'Failed to get budget information',
                'budget_info' => $budgetInfo
            ];
        }

        $exceedsBudget = $budgetInfo['exceeds_budget'] ?? false;
        $remainingAfterCurrent = $budgetInfo['remaining_after_current'] ?? 0;

        if ($exceedsBudget) {
            $budgetType = $budgetInfo['budget_type'];
            $budgetLimit = $budgetType === 'PER_OUTLET' 
                ? $budgetInfo['outlet_budget'] 
                : $budgetInfo['category_budget'];
            
            return [
                'valid' => false,
                'message' => "Budget exceeded! Total (Rp " . number_format($budgetInfo['total_with_current'], 0, ',', '.') . ") exceeds available budget (Rp " . number_format($budgetLimit, 0, ',', '.') . "). Remaining: Rp " . number_format($remainingAfterCurrent, 0, ',', '.'),
                'budget_info' => $budgetInfo
            ];
        }

        return [
            'valid' => true,
            'message' => 'Budget is sufficient',
            'budget_info' => $budgetInfo
        ];
    }
}

