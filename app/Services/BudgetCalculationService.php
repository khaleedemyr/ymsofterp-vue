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
        ?float $currentAmount = 0,
        ?int $excludePrId = null
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
            return $this->calculateGlobalBudget($category, $year, $month, $dateFrom, $dateTo, $currentAmount, $excludePrId);
        } else if ($category->isPerOutletBudget()) {
            if (!$outletId) {
                return [
                    'success' => false,
                    'message' => 'Outlet ID is required for per-outlet budget'
                ];
            }
            return $this->calculatePerOutletBudget($category, $outletId, $year, $month, $dateFrom, $dateTo, $currentAmount, $excludePrId);
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
        float $currentAmount,
        ?int $excludePrId = null
    ): array {
        $categoryId = $category->id;
        $categoryBudget = $category->budget_limit;

        // Get Retail Non Food
        $retailNonFoodApproved = DB::table('retail_non_food')
            ->where('category_budget_id', $categoryId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->where('status', 'approved')
            ->sum('total_amount');

        // Get paid amount from PO items (GLOBAL - sum all outlets)
        // SIMPLIFIED: Tidak menggunakan non_food_payments, langsung dari PO items yang approved
        $paidAmountFromPo = $this->calculatePaidAmountFromPo($categoryId, null, $year, $month, $dateFrom, $dateTo);

        // Get total PR items (all PR items for this category - termasuk yang sudah jadi PO)
        // PENTING: PR Total = semua PR items yang sudah dibuat, TIDAK PEDULI STATUS
        // Exclude PR yang sedang di-approve untuk menghindari double counting
        $prTotalAmount = $this->calculatePrTotalAmount($categoryId, null, $year, $month, $dateFrom, $dateTo, $excludePrId);

        // Get unpaid amounts (SIMPLIFIED: hanya PR unpaid)
        // Exclude PR yang sedang di-approve untuk menghindari double counting
        $prUnpaidAmount = $this->calculatePrUnpaidAmount($categoryId, null, $year, $month, $excludePrId);
        
        // Get total PO items (all PO items for this category - approved)
        $poTotalAmount = $paidAmountFromPo; // PO total = paid amount from PO (approved PO items)
        
        // PENTING: PR Paid = PR Total - PR Unpaid (untuk memastikan konsistensi)
        // Ini memastikan bahwa PR Total = PR Unpaid + PR Paid
        // PR Paid adalah semua PR items yang sudah jadi PO (atau tidak unpaid)
        $prPaidAmount = $prTotalAmount - $prUnpaidAmount;
        
        $poUnpaidAmount = 0; // PO Unpaid = 0 (karena semua PO items sudah dihitung sebagai paid)
        $nfpUnpaidAmount = 0; // NFP Unpaid = 0 (karena kita tidak pakai NFP lagi)

        // Get NFP breakdown (untuk backward compatibility, set ke 0)
        $nfpSubmittedAmount = 0;
        $nfpApprovedAmount = 0;
        $nfpPaidAmount = 0;

        // Calculate totals
        // Used Budget = PR Total + RNF (karena RNF juga menggunakan budget)
        $categoryUsedAmount = $prTotalAmount + $retailNonFoodApproved;
        
        // Paid Amount = PO Total + RNF (untuk tracking payment status)
        $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
        
        // Unpaid Amount = PR Unpaid (untuk tracking unpaid items)
        $unpaidAmount = $prUnpaidAmount;

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
                'pr_total' => $prTotalAmount, // Semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                'pr_unpaid' => $prUnpaidAmount, // PR items yang belum jadi PO
                'pr_paid' => $prPaidAmount, // PR items yang sudah jadi PO = PR Total - PR Unpaid
                'po_total' => $poTotalAmount, // Total PO items (untuk referensi, bisa berbeda dari pr_paid)
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
        float $currentAmount,
        ?int $excludePrId = null
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

        // Get paid amount from PO items for this outlet (with proportion)
        // SIMPLIFIED: Tidak menggunakan non_food_payments, langsung dari PO items yang approved
        $paidAmountFromPo = $this->calculatePaidAmountFromPo($categoryId, $outletId, $year, $month, $dateFrom, $dateTo);

        // Get total PR items (all PR items for this category/outlet - termasuk yang sudah jadi PO)
        // PENTING: PR Total = semua PR items yang sudah dibuat, TIDAK PEDULI STATUS
        // Exclude PR yang sedang di-approve untuk menghindari double counting
        $prTotalAmount = $this->calculatePrTotalAmount($categoryId, $outletId, $year, $month, $dateFrom, $dateTo, $excludePrId);

        // Get unpaid amounts for this outlet (SIMPLIFIED: hanya PR unpaid)
        // Exclude PR yang sedang di-approve untuk menghindari double counting
        $prUnpaidAmount = $this->calculatePrUnpaidAmount($categoryId, $outletId, $year, $month, $excludePrId);
        
        // Get total PO items (all PO items for this category/outlet - approved)
        $poTotalAmount = $paidAmountFromPo; // PO total = paid amount from PO (approved PO items)
        
        // PENTING: PR Paid = PR Total - PR Unpaid (untuk memastikan konsistensi)
        // Ini memastikan bahwa PR Total = PR Unpaid + PR Paid
        // PR Paid adalah semua PR items yang sudah jadi PO (atau tidak unpaid)
        $prPaidAmount = $prTotalAmount - $prUnpaidAmount;
        
        $poUnpaidAmount = 0; // PO Unpaid = 0 (karena semua PO items sudah dihitung sebagai paid)
        $nfpUnpaidAmount = 0; // NFP Unpaid = 0 (karena kita tidak pakai NFP lagi)

        // Get NFP breakdown for this outlet (untuk backward compatibility, set ke 0)
        $nfpSubmittedAmount = 0;
        $nfpApprovedAmount = 0;
        $nfpPaidAmount = 0;

        // Calculate totals
        // Used Budget = PR Total + RNF (karena RNF juga menggunakan budget)
        $outletUsedAmount = $prTotalAmount + $outletRetailNonFoodApproved;
        
        // Paid Amount = PO Total + RNF (untuk tracking payment status)
        $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
        
        // Unpaid Amount = PR Unpaid (untuk tracking unpaid items)
        $unpaidAmount = $prUnpaidAmount;

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
                'pr_total' => $prTotalAmount, // Semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                'pr_unpaid' => $prUnpaidAmount, // PR items yang belum jadi PO
                'pr_paid' => $prPaidAmount, // PR items yang sudah jadi PO = PR Total - PR Unpaid
                'po_total' => $poTotalAmount, // Total PO items (untuk referensi, bisa berbeda dari pr_paid)
                'po_unpaid' => $poUnpaidAmount,
                'nfp_submitted' => $nfpSubmittedAmount,
                'nfp_approved' => $nfpApprovedAmount,
                'nfp_paid' => $nfpPaidAmount,
                'retail_non_food' => $outletRetailNonFoodApproved,
            ],
        ];
    }

    /**
     * Calculate paid amount from PO items (SIMPLIFIED: Direct from PO items, no NFP join)
     * Paid = PO items yang sudah ada di PO dengan status approved
     */
    private function calculatePaidAmountFromPo(
        int $categoryId,
        ?int $outletId,
        int $year,
        int $month,
        string $dateFrom,
        string $dateTo
    ): float {
        // SIMPLIFIED: Hitung langsung dari PO items, tidak perlu join ke non_food_payments
        // Paid = PO items yang sudah ada di PO dengan status approved (semua PO items dianggap "committed")
        $query = DB::table('purchase_order_ops_items as poi')
            ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) use ($categoryId, $outletId) {
                $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                     ->where(function($q) use ($categoryId, $outletId) {
                         // Prefer exact match by pr_ops_item_id
                         $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                           // Fallback: match by item_name
                           ->orWhere(function($q2) use ($categoryId, $outletId) {
                               if ($outletId) {
                                   // PER_OUTLET: Match by item_name + outlet_id + category_id to avoid duplicates
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name')
                                      ->where('pri.outlet_id', $outletId)
                                      ->where('pri.category_id', $categoryId)
                                      // Only match the first PR item if multiple exist (to avoid double counting)
                                      ->whereRaw('pri.id = (
                                          SELECT MIN(pri2.id) 
                                          FROM purchase_requisition_items as pri2 
                                          WHERE pri2.purchase_requisition_id = pri.purchase_requisition_id 
                                          AND pri2.item_name = pri.item_name
                                          AND pri2.outlet_id = ?
                                          AND pri2.category_id = ?
                                      )', [$outletId, $categoryId]);
                               } else {
                                   // GLOBAL: Match by item_name + category_id only (no outlet filter)
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name')
                                      ->where('pri.category_id', $categoryId)
                                      // Only match the first PR item if multiple exist (to avoid double counting)
                                      ->whereRaw('pri.id = (
                                          SELECT MIN(pri2.id) 
                                          FROM purchase_requisition_items as pri2 
                                          WHERE pri2.purchase_requisition_id = pri.purchase_requisition_id 
                                          AND pri2.item_name = pri.item_name
                                          AND pri2.category_id = ?
                                      )', [$categoryId]);
                               }
                           });
                     });
            })
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->where('pr.is_held', false)
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->where('poo.status', 'approved') // Only approved POs
            ->whereBetween('poo.date', [$dateFrom, $dateTo]);

        // Filter by category and outlet
        if ($outletId) {
            // PER_OUTLET: Filter by outlet and category
            $query->where(function($q) use ($categoryId, $outletId) {
                // Old structure: category and outlet at PR level
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                // New structure: category and outlet at items level
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            });
        } else {
            // GLOBAL: Filter by category only (NO outlet filter at all)
            $query->where(function($q) use ($categoryId) {
                $q->where('pr.category_id', $categoryId)
                  ->orWhere('pri.category_id', $categoryId);
            });
        }

        return $query->groupBy('poi.id') // Group by PO item ID to avoid duplicates
            ->sum('poi.total');
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
     * Calculate PR Unpaid Amount (SIMPLIFIED: PR items yang belum jadi PO items)
     */
    private function calculatePrUnpaidAmount(int $categoryId, ?int $outletId, int $year, int $month, ?int $excludePrId = null): float
    {
        // SIMPLIFIED: Hitung PR items yang belum ada di PO items
        $query = DB::table('purchase_requisition_items as pri')
            ->leftJoin('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->leftJoin('purchase_order_ops_items as poi', function($join) use ($categoryId, $outletId) {
                $join->on('pr.id', '=', 'poi.source_id')
                     ->where('poi.source_type', '=', 'purchase_requisition_ops')
                     ->where(function($q) use ($categoryId, $outletId) {
                         // Match by pr_ops_item_id
                         $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                           // Or match by item_name
                           ->orWhere(function($q2) use ($categoryId, $outletId) {
                               if ($outletId) {
                                   // PER_OUTLET: Match by item_name + outlet_id + category_id
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name')
                                      ->where('pri.outlet_id', $outletId)
                                      ->where('pri.category_id', $categoryId);
                               } else {
                                   // GLOBAL: Match by item_name + category_id only (no outlet filter)
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name')
                                      ->where('pri.category_id', $categoryId);
                               }
                           });
                     });
            })
            ->whereYear('pr.created_at', $year)
            ->whereMonth('pr.created_at', $month)
            ->whereIn('pr.status', ['SUBMITTED', 'APPROVED'])
            ->where('pr.is_held', false)
            ->whereNull('poi.id'); // PR item yang belum jadi PO item

        // Exclude PR yang sedang di-approve untuk menghindari double counting
        if ($excludePrId) {
            $query->where('pr.id', '!=', $excludePrId);
        }

        // Filter by category and outlet
        if ($outletId) {
            // PER_OUTLET: Filter by outlet and category
            $query->where('pri.outlet_id', $outletId)
                  ->where('pri.category_id', $categoryId);
        } else {
            // GLOBAL: Filter by category only (NO outlet filter at all)
            $query->where('pri.category_id', $categoryId);
        }

        return $query->groupBy('pri.id') // Group by PR item ID to avoid duplicates
            ->sum('pri.subtotal');
    }

    /**
     * Calculate PR Total Amount (all PR items for this category/outlet)
     * PENTING: PR Total = semua PR items yang sudah dibuat, EXCLUDE REJECTED dan DELETED
     * Gunakan filter tanggal dari pri.created_at untuk konsistensi dengan query manual
     * dan hitung menggunakan qty*unit_price untuk konsistensi dengan query manual
     */
    private function calculatePrTotalAmount(int $categoryId, ?int $outletId, int $year, int $month, string $dateFrom, string $dateTo, ?int $excludePrId = null): float
    {
        // PR Total = semua PR items yang sudah dibuat, EXCLUDE REJECTED
        // Join ke purchase_requisitions untuk filter status dan is_held
        // Note: Hard deleted PRs are automatically excluded because PR items will also be deleted
        $query = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->where('pri.category_id', $categoryId)
            ->whereBetween(DB::raw('DATE(pri.created_at)'), [$dateFrom, $dateTo])
            // Exclude REJECTED status
            ->where('pr.status', '!=', 'REJECTED')
            // Exclude held PRs
            ->where('pr.is_held', false);

        // Exclude PR yang sedang di-approve untuk menghindari double counting
        if ($excludePrId) {
            $query->where('pr.id', '!=', $excludePrId);
        }

        // Filter by outlet jika per outlet budget
        if ($outletId) {
            $query->where('pri.outlet_id', $outletId);
        }

        // Gunakan qty*unit_price untuk konsistensi dengan query manual
        // Tidak perlu groupBy karena kita langsung sum semua items
        return $query->sum(DB::raw('pri.qty * pri.unit_price'));
    }

    /**
     * Calculate PO Unpaid Amount (SIMPLIFIED: 0 karena semua PO items sudah dihitung sebagai paid)
     */
    private function calculatePoUnpaidAmount(int $categoryId, ?int $outletId, int $year, int $month): float
    {
        // SIMPLIFIED: PO Unpaid = 0 (karena semua PO items sudah dihitung sebagai paid)
        return 0;
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
        $budgetInfo = $this->getBudgetInfo($categoryId, $outletId, $dateFrom, $dateTo, $currentAmount, $excludePrId);

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

