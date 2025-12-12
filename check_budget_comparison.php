<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\OpexReportController;

// Configuration
$categoryId = 17;
$outletName = 'Justus Steak House Buah Batu';
$dateFrom = date('Y-m-01'); // First day of current month
$dateTo = date('Y-m-t'); // Last day of current month

echo "=== BUDGET COMPARISON: Opex Report vs Purchase Requisition Ops ===\n";
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
echo "Budget Type: {$category->budget_type}\n\n";

// Get outlet budget
$outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
    ->where('outlet_id', $outletId)
    ->first();
    
if (!$outletBudget) {
    echo "ERROR: No outlet budget found!\n";
    exit;
}
echo "Outlet Budget: Rp " . number_format($outletBudget->allocated_budget, 0, ',', '.') . "\n\n";

// ============================================
// 1. GET BUDGET INFO FROM PurchaseRequisitionController
// ============================================
echo "=== 1. Purchase Requisition Ops (getBudgetInfo) ===\n";
$prController = new PurchaseRequisitionController();
$request = new \Illuminate\Http\Request();
$request->merge([
    'category_id' => $categoryId,
    'outlet_id' => $outletId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);

try {
    $budgetInfoResponse = $prController->getBudgetInfo($request);
    $budgetInfo = json_decode($budgetInfoResponse->getContent(), true);
    
    if ($budgetInfo && isset($budgetInfo['data'])) {
        $data = $budgetInfo['data'];
        echo "Used Amount: Rp " . number_format($data['outlet_used_amount'] ?? 0, 0, ',', '.') . "\n";
        echo "Paid Amount: Rp " . number_format($data['breakdown']['nfp_paid'] ?? 0, 0, ',', '.') . "\n";
        echo "PR Unpaid: Rp " . number_format($data['breakdown']['pr_unpaid'] ?? 0, 0, ',', '.') . "\n";
        echo "PO Unpaid: Rp " . number_format($data['breakdown']['po_unpaid'] ?? 0, 0, ',', '.') . "\n";
        echo "NFP Approved: Rp " . number_format($data['breakdown']['nfp_approved'] ?? 0, 0, ',', '.') . "\n";
        echo "Retail Non Food: Rp " . number_format($data['breakdown']['retail_non_food'] ?? 0, 0, ',', '.') . "\n";
    } else {
        echo "ERROR: Could not get budget info\n";
        echo "Response: " . $budgetInfoResponse->getContent() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// 2. GET BUDGET INFO FROM OpexReportController
// ============================================
echo "=== 2. Opex Report (getAllCategoriesWithBudget) ===\n";
$opexController = new OpexReportController();
$reflection = new \ReflectionClass($opexController);
$method = $reflection->getMethod('getAllCategoriesWithBudget');
$method->setAccessible(true);

try {
    $allCategories = $method->invoke($opexController, $dateFrom, $dateTo);
    
    // Find the category
    $categoryData = null;
    foreach ($allCategories as $cat) {
        if ($cat['category_id'] == $categoryId) {
            $categoryData = $cat;
            break;
        }
    }
    
    if ($categoryData && isset($categoryData['outlets'])) {
        // Find the outlet
        $outletData = null;
        foreach ($categoryData['outlets'] as $out) {
            if ($out['outlet_id'] == $outletId) {
                $outletData = $out;
                break;
            }
        }
        
        if ($outletData) {
            echo "Used Amount: Rp " . number_format($outletData['used_amount'] ?? 0, 0, ',', '.') . "\n";
            echo "Paid Amount: Rp " . number_format($outletData['paid_amount'] ?? 0, 0, ',', '.') . "\n";
            echo "PR Unpaid: Rp " . number_format($outletData['breakdown']['pr_unpaid'] ?? 0, 0, ',', '.') . "\n";
            echo "PO Unpaid: Rp " . number_format($outletData['breakdown']['po_unpaid'] ?? 0, 0, ',', '.') . "\n";
            echo "NFP Approved: Rp " . number_format($outletData['breakdown']['nfp_approved'] ?? 0, 0, ',', '.') . "\n";
            echo "NFP Paid: Rp " . number_format($outletData['breakdown']['nfp_paid'] ?? 0, 0, ',', '.') . "\n";
            echo "Retail Non Food: Rp " . number_format($outletData['breakdown']['retail_non_food'] ?? 0, 0, ',', '.') . "\n";
        } else {
            echo "ERROR: Outlet not found in Opex Report data\n";
        }
    } else {
        echo "ERROR: Category not found in Opex Report data\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// ============================================
// 3. DEBUG: Check PO IDs and Payments
// ============================================
echo "\n=== 3. DEBUG: PO IDs and Payments ===\n";

// Get PR IDs
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

echo "PR IDs: " . implode(', ', $prIds) . "\n";

// Get PO IDs (with filter PR created_at month)
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
    ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
    ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
    ->where('pr.is_held', false)
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
    ->distinct()
    ->pluck('poi.purchase_order_ops_id')
    ->toArray();

echo "PO IDs (with PR created_at filter): " . implode(', ', $poIdsInCategory) . "\n";

// Get PO IDs (without PR created_at filter - like before fix)
$poIdsInCategoryOld = DB::table('purchase_order_ops_items as poi')
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
    ->distinct()
    ->pluck('poi.purchase_order_ops_id')
    ->toArray();

echo "PO IDs (without PR created_at filter): " . implode(', ', $poIdsInCategoryOld) . "\n";

// Check payments
if (!empty($poIdsInCategory)) {
    foreach ($poIdsInCategory as $poId) {
        $payment = DB::table('non_food_payments')
            ->where('purchase_order_ops_id', $poId)
            ->where('status', 'paid')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->first();
        
        if ($payment) {
            echo "PO #{$poId}: Payment found - Rp " . number_format($payment->amount, 0, ',', '.') . " (Date: {$payment->payment_date})\n";
        } else {
            // Check if payment exists but outside date range
            $paymentAny = DB::table('non_food_payments')
                ->where('purchase_order_ops_id', $poId)
                ->where('status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->first();
            
            if ($paymentAny) {
                echo "PO #{$poId}: Payment exists but outside date range - Rp " . number_format($paymentAny->amount, 0, ',', '.') . " (Date: {$paymentAny->payment_date})\n";
            } else {
                echo "PO #{$poId}: No payment found\n";
            }
        }
    }
}

echo "\n=== END OF COMPARISON ===\n";

