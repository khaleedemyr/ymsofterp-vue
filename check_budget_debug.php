<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;

// Configuration
$categoryId = 17;
$outletName = 'Justus Steak House Ciwalk';
$dateFrom = date('Y-m-01'); // First day of current month
$dateTo = date('Y-m-t'); // Last day of current month

echo "=== BUDGET DEBUG ANALYSIS ===\n";
echo "Category ID: {$categoryId}\n";
echo "Outlet: {$outletName}\n";
echo "Date Range: {$dateFrom} to {$dateTo}\n\n";

// Get outlet ID
$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', $outletName)->first();
if (!$outlet) {
    echo "ERROR: Outlet not found!\n";
    exit;
}
$outletId = $outlet->id_outlet;
echo "Outlet ID: {$outletId}\n\n";

// Get category
$category = PurchaseRequisitionCategory::find($categoryId);
if (!$category) {
    echo "ERROR: Category not found!\n";
    exit;
}
echo "Category: {$category->name} ({$category->division})\n";
echo "Budget Type: {$category->budget_type}\n";
echo "Budget Limit: Rp " . number_format($category->budget_limit, 0, ',', '.') . "\n\n";

// Get outlet budget
$outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
    ->where('outlet_id', $outletId)
    ->first();
    
if ($outletBudget) {
    echo "Outlet Budget: Rp " . number_format($outletBudget->allocated_budget, 0, ',', '.') . "\n\n";
} else {
    echo "WARNING: No outlet budget found!\n\n";
}

// ============================================
// 1. GET PR IDs FOR THIS OUTLET
// ============================================
echo "=== 1. PR IDs FOR THIS OUTLET ===\n";
$prIds = DB::table('purchase_requisitions as pr')
    ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
    ->where(function($q) use ($categoryId, $outletId) {
        $q->where(function($q2) use ($categoryId, $outletId) {
            $q2->where('pr.category_id', $categoryId)
               ->where('pr.outlet_id', $outletId);
        })
        ->orWhere(function($q2) use ($categoryId, $outletId) {
            $q2->where('pri.category_id', $categoryId)
               ->where('pri.outlet_id', $outletId);
        });
    })
    ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
    ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
    ->where('pr.is_held', false)
    ->distinct()
    ->pluck('pr.id')
    ->toArray();

echo "Total PR IDs: " . count($prIds) . "\n";
if (count($prIds) > 0) {
    echo "PR IDs: " . implode(', ', array_slice($prIds, 0, 10));
    if (count($prIds) > 10) echo " ... (showing first 10)";
    echo "\n\n";
}

// ============================================
// 2. GET PO IDs LINKED TO PRS
// ============================================
echo "=== 2. PO IDs LINKED TO PRS ===\n";
$poIdsInCategory = DB::table('purchase_order_ops_items as poi')
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
    ->where(function($q) use ($categoryId, $outletId) {
        $q->where(function($q2) use ($categoryId, $outletId) {
            $q2->where('pr.category_id', $categoryId)
               ->where('pr.outlet_id', $outletId);
        })
        ->orWhere(function($q2) use ($categoryId, $outletId) {
            $q2->where('pri.category_id', $categoryId)
               ->where('pri.outlet_id', $outletId);
        });
    })
    ->select('poi.purchase_order_ops_id', 'pr.id as pr_id', 'pr.pr_number', 'poi.total as poi_total')
    ->get();

echo "Total PO Items: " . count($poIdsInCategory) . "\n";
$uniquePoIds = $poIdsInCategory->pluck('purchase_order_ops_id')->unique()->toArray();
echo "Unique PO IDs: " . count($uniquePoIds) . "\n";
if (count($uniquePoIds) > 0) {
    echo "PO IDs: " . implode(', ', array_slice($uniquePoIds, 0, 10));
    if (count($uniquePoIds) > 10) echo " ... (showing first 10)";
    echo "\n\n";
    
    // Group by PR to see which PRs these POs come from
    $poByPr = $poIdsInCategory->groupBy('pr_id');
    echo "PO Items grouped by PR:\n";
    foreach ($poByPr as $prId => $items) {
        $pr = DB::table('purchase_requisitions')->where('id', $prId)->first();
        $prNumber = $pr ? $pr->pr_number : "PR ID {$prId}";
        $prCreatedAt = $pr ? $pr->created_at : 'N/A';
        $poIds = $items->pluck('purchase_order_ops_id')->unique()->toArray();
        echo "  PR #{$prNumber} (ID: {$prId}): " . count($items) . " PO items, PO IDs: " . implode(', ', $poIds) . "\n";
        echo "    Created At: {$prCreatedAt}\n";
        
        // Check if this PR has items for outlet 6 and category 17
        $prItems = DB::table('purchase_requisition_items')
            ->where('purchase_requisition_id', $prId)
            ->where('outlet_id', $outletId)
            ->where('category_id', $categoryId)
            ->get();
        
        if (count($prItems) > 0) {
            echo "    Has items for Outlet {$outletId} and Category {$categoryId}: YES (" . count($prItems) . " items)\n";
            foreach ($prItems as $item) {
                $qty = $item->qty ?? $item->quantity ?? 'N/A';
                echo "      - {$item->item_name}: Rp " . number_format($item->subtotal, 0, ',', '.') . "\n";
            }
        } else {
            echo "    Has items for Outlet {$outletId} and Category {$categoryId}: NO\n";
        }
        echo "\n";
    }
    echo "\n";
}

$poIdsInCategory = $uniquePoIds;

// ============================================
// 3. CALCULATE PAID AMOUNT FROM PO
// ============================================
echo "=== 3. NFP PAID AMOUNT (FROM PO) ===\n";
$paidAmountFromPo = 0;
$paidDetails = [];

if (!empty($poIdsInCategory)) {
    foreach ($poIdsInCategory as $poId) {
        // Get PO items yang berasal dari PR items di outlet ini
        $outletPoItemIds = DB::table('purchase_order_ops_items as poi')
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
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->where(function($q) use ($categoryId, $outletId) {
                $q->where(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pr.category_id', $categoryId)
                       ->where('pr.outlet_id', $outletId);
                })
                ->orWhere(function($q2) use ($categoryId, $outletId) {
                    $q2->where('pri.category_id', $categoryId)
                       ->where('pri.outlet_id', $outletId);
                });
            })
            ->pluck('poi.total')
            ->toArray();
        
        // Get payment untuk PO ini
        $poPayment = DB::table('non_food_payments')
            ->where('purchase_order_ops_id', $poId)
            ->where('status', 'paid')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->first();
        
        if ($poPayment) {
            $poStatus = DB::table('purchase_order_ops')
                ->where('id', $poId)
                ->value('status');
            
            if ($poStatus === 'approved') {
                $poTotalItems = DB::table('purchase_order_ops_items')
                    ->where('purchase_order_ops_id', $poId)
                    ->sum('total');
                
                if ($poTotalItems > 0) {
                    $outletPoItemsTotal = array_sum($outletPoItemIds);
                    $proportion = $outletPoItemsTotal / $poTotalItems;
                    $allocatedAmount = $poPayment->amount * $proportion;
                    $paidAmountFromPo += $allocatedAmount;
                    
                    $paidDetails[] = [
                        'po_id' => $poId,
                        'po_number' => DB::table('purchase_order_ops')->where('id', $poId)->value('number'),
                        'payment_amount' => $poPayment->amount,
                        'outlet_items_total' => $outletPoItemsTotal,
                        'po_total_items' => $poTotalItems,
                        'proportion' => $proportion,
                        'allocated_amount' => $allocatedAmount
                    ];
                }
            }
        }
    }
}

echo "Total Paid Amount from PO: Rp " . number_format($paidAmountFromPo, 0, ',', '.') . "\n";
echo "Number of payments: " . count($paidDetails) . "\n\n";

if (count($paidDetails) > 0) {
    echo "Payment Details:\n";
    foreach (array_slice($paidDetails, 0, 5) as $detail) {
        echo "  PO #{$detail['po_number']} (ID: {$detail['po_id']}):\n";
        echo "    Payment Amount: Rp " . number_format($detail['payment_amount'], 0, ',', '.') . "\n";
        echo "    Outlet Items Total: Rp " . number_format($detail['outlet_items_total'], 0, ',', '.') . "\n";
        echo "    PO Total Items: Rp " . number_format($detail['po_total_items'], 0, ',', '.') . "\n";
        echo "    Proportion: " . number_format($detail['proportion'] * 100, 2) . "%\n";
        echo "    Allocated Amount: Rp " . number_format($detail['allocated_amount'], 0, ',', '.') . "\n\n";
    }
    if (count($paidDetails) > 5) {
        echo "  ... (" . (count($paidDetails) - 5) . " more payments)\n\n";
    }
}

// ============================================
// 4. CHECK PR #552 STATUS
// ============================================
echo "=== 4. CHECK PR #552 STATUS ===\n";
$pr552 = DB::table('purchase_requisitions')->where('id', 552)->first();
if ($pr552) {
    echo "PR #552 Details:\n";
    echo "  PR Number: {$pr552->pr_number}\n";
    echo "  Status: {$pr552->status}\n";
    echo "  Mode: {$pr552->mode}\n";
    echo "  Amount: Rp " . number_format($pr552->amount, 0, ',', '.') . "\n";
    echo "  Created At: {$pr552->created_at}\n\n";
    
    // Check if PR #552 has been converted to PO
    $poItemsFromPr552 = DB::table('purchase_order_ops_items')
        ->where('source_id', 552)
        ->where('source_type', 'purchase_requisition_ops')
        ->get();
    
    echo "PO Items from PR #552: " . count($poItemsFromPr552) . "\n";
    if (count($poItemsFromPr552) > 0) {
        echo "PO IDs: ";
        $poIds = $poItemsFromPr552->pluck('purchase_order_ops_id')->unique()->toArray();
        echo implode(', ', $poIds) . "\n";
        
        foreach ($poIds as $poId) {
            $po = DB::table('purchase_order_ops')->where('id', $poId)->first();
            if ($po) {
                echo "  PO #{$po->number} (ID: {$poId}): Status = {$po->status}\n";
            }
        }
    }
    echo "\n";
    
    // Check PR items for outlet 6 and category 17
    $prItems = DB::table('purchase_requisition_items')
        ->where('purchase_requisition_id', 552)
        ->where('outlet_id', $outletId)
        ->where('category_id', $categoryId)
        ->get();
    
    echo "PR Items for Outlet {$outletId} and Category {$categoryId}: " . count($prItems) . "\n";
    foreach ($prItems as $item) {
        $qty = $item->qty ?? $item->quantity ?? 'N/A';
        echo "  Item: {$item->item_name}, Qty: {$qty}, Subtotal: Rp " . number_format($item->subtotal, 0, ',', '.') . "\n";
    }
    echo "\n";
}

// ============================================
// 5. GET PR UNPAID
// ============================================
echo "=== 5. PR UNPAID ===\n";
$prIdsForUnpaid = DB::table('purchase_requisitions as pr')
    ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
    ->leftJoin('purchase_order_ops_items as poi', function($join) {
        $join->on('pr.id', '=', 'poi.source_id')
             ->where('poi.source_type', '=', 'purchase_requisition_ops');
    })
    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
    ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
    ->where(function($q) use ($categoryId, $outletId) {
        $q->where(function($q2) use ($categoryId, $outletId) {
            $q2->where('pr.category_id', $categoryId)
               ->where('pr.outlet_id', $outletId);
        })
        ->orWhere(function($q2) use ($categoryId, $outletId) {
            $q2->where('pri.category_id', $categoryId)
               ->where('pri.outlet_id', $outletId);
        });
    })
    ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
    ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED'])
    ->where('pr.is_held', false)
    ->whereNull('poo.id')
    ->whereNull('nfp.id')
    ->distinct()
    ->pluck('pr.id')
    ->toArray();

echo "PR IDs for Unpaid: " . count($prIdsForUnpaid) . "\n";
if (count($prIdsForUnpaid) > 0) {
    echo "PR IDs: " . implode(', ', array_slice($prIdsForUnpaid, 0, 10));
    if (count($prIdsForUnpaid) > 10) echo " ... (showing first 10)";
    echo "\n\n";
}

$allPrs = DB::table('purchase_requisitions')->whereIn('id', $prIdsForUnpaid)->get();
$prUnpaidAmount = 0;
$prUnpaidDetails = [];

foreach ($allPrs as $pr) {
    if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
        $outletItemsSubtotal = DB::table('purchase_requisition_items')
            ->where('purchase_requisition_id', $pr->id)
            ->where('outlet_id', $outletId)
            ->where('category_id', $categoryId)
            ->sum('subtotal');
        $prUnpaidAmount += $outletItemsSubtotal ?? 0;
        
        $prUnpaidDetails[] = [
            'pr_id' => $pr->id,
            'pr_number' => $pr->pr_number,
            'amount' => $outletItemsSubtotal ?? 0
        ];
    } else {
        $prUnpaidAmount += $pr->amount;
        $prUnpaidDetails[] = [
            'pr_id' => $pr->id,
            'pr_number' => $pr->pr_number,
            'amount' => $pr->amount
        ];
    }
}

echo "Total PR Unpaid Amount: Rp " . number_format($prUnpaidAmount, 0, ',', '.') . "\n";
echo "Number of PRs: " . count($prUnpaidDetails) . "\n\n";

if (count($prUnpaidDetails) > 0) {
    echo "PR Unpaid Details:\n";
    foreach (array_slice($prUnpaidDetails, 0, 5) as $detail) {
        echo "  PR #{$detail['pr_number']} (ID: {$detail['pr_id']}): Rp " . number_format($detail['amount'], 0, ',', '.') . "\n";
    }
    if (count($prUnpaidDetails) > 5) {
        echo "  ... (" . (count($prUnpaidDetails) - 5) . " more PRs)\n";
    }
    echo "\n";
}

// ============================================
// 6. GET RETAIL NON FOOD
// ============================================
echo "=== 6. RETAIL NON FOOD ===\n";
$outletRetailNonFoodApproved = DB::table('retail_non_food')
    ->where('category_budget_id', $categoryId)
    ->where('outlet_id', $outletId)
    ->whereBetween('transaction_date', [$dateFrom, $dateTo])
    ->where('status', 'approved')
    ->sum('total_amount');

echo "Retail Non Food Approved: Rp " . number_format($outletRetailNonFoodApproved, 0, ',', '.') . "\n\n";

// ============================================
// 7. SUMMARY
// ============================================
echo "=== SUMMARY ===\n";
$paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
$unpaidAmount = $prUnpaidAmount; // Simplified for now
$usedAmount = $paidAmount + $unpaidAmount;

echo "Paid Amount (NFP Paid + RNF): Rp " . number_format($paidAmount, 0, ',', '.') . "\n";
echo "  - NFP Paid from PO: Rp " . number_format($paidAmountFromPo, 0, ',', '.') . "\n";
echo "  - Retail Non Food: Rp " . number_format($outletRetailNonFoodApproved, 0, ',', '.') . "\n";
echo "Unpaid Amount (PR Unpaid): Rp " . number_format($unpaidAmount, 0, ',', '.') . "\n";
echo "Used Amount (Paid + Unpaid): Rp " . number_format($usedAmount, 0, ',', '.') . "\n\n";

if ($outletBudget) {
    echo "Allocated Budget: Rp " . number_format($outletBudget->allocated_budget, 0, ',', '.') . "\n";
    echo "Remaining Budget: Rp " . number_format($outletBudget->allocated_budget - $usedAmount, 0, ',', '.') . "\n";
    echo "Usage Percentage: " . number_format(($usedAmount / $outletBudget->allocated_budget) * 100, 1) . "%\n";
}

echo "\n=== END OF ANALYSIS ===\n";

