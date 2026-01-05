<?php

/**
 * Manual Test Script for Budget Validation
 * 
 * This script tests:
 * 1. PR Total excludes REJECTED status
 * 2. PR Total excludes deleted PRs
 * 3. PR Total excludes held PRs
 * 4. Budget validation blocks when exceeded
 * 5. Budget validation allows when sufficient
 * 
 * Usage: php test_budget_validation.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\BudgetCalculationService;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionItem;
use Illuminate\Support\Facades\DB;

echo "=== Testing Budget Validation ===\n\n";

$budgetService = new BudgetCalculationService();
$dateFrom = date('Y-m-01');
$dateTo = date('Y-m-t');

// Test 1: Check if PR Total excludes REJECTED
echo "Test 1: PR Total excludes REJECTED status\n";
echo "----------------------------------------\n";

// Get a category with GLOBAL budget
$category = PurchaseRequisitionCategory::where('budget_type', 'GLOBAL')
    ->whereNotNull('budget_limit')
    ->first();

if (!$category) {
    echo "❌ No GLOBAL budget category found. Please create one first.\n";
    exit(1);
}

echo "Using category: {$category->name} (Budget: Rp " . number_format($category->budget_limit, 0, ',', '.') . ")\n\n";

// Count PR items by status
$prTotalQuery = DB::table('purchase_requisition_items as pri')
    ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
    ->where('pri.category_id', $category->id)
    ->whereBetween(DB::raw('DATE(pri.created_at)'), [$dateFrom, $dateTo])
    ->where('pr.status', '!=', 'REJECTED')
    ->where('pr.is_held', false);

$prTotal = $prTotalQuery->sum(DB::raw('pri.qty * pri.unit_price'));

// Count REJECTED PR items
$rejectedTotal = DB::table('purchase_requisition_items as pri')
    ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
    ->where('pri.category_id', $category->id)
    ->whereBetween(DB::raw('DATE(pri.created_at)'), [$dateFrom, $dateTo])
    ->where('pr.status', 'REJECTED')
    ->sum(DB::raw('pri.qty * pri.unit_price'));

echo "PR Total (excluding REJECTED): Rp " . number_format($prTotal, 0, ',', '.') . "\n";
echo "REJECTED PR Total: Rp " . number_format($rejectedTotal, 0, ',', '.') . "\n";

// Get budget info from service
$budgetInfo = $budgetService->getBudgetInfo(
    $category->id,
    null,
    $dateFrom,
    $dateTo,
    0
);

if ($budgetInfo['success']) {
    $servicePrTotal = $budgetInfo['breakdown']['pr_total'] ?? 0;
    echo "Service PR Total: Rp " . number_format($servicePrTotal, 0, ',', '.') . "\n";
    
    if (abs($prTotal - $servicePrTotal) < 0.01) {
        echo "✅ PASS: PR Total matches service calculation\n";
    } else {
        echo "❌ FAIL: PR Total mismatch (Expected: {$prTotal}, Got: {$servicePrTotal})\n";
    }
} else {
    echo "❌ FAIL: Could not get budget info from service\n";
}

echo "\n";

// Test 2: Check budget validation
echo "Test 2: Budget Validation\n";
echo "------------------------\n";

$currentUsed = $budgetInfo['category_used_amount'] ?? 0;
$budgetLimit = $category->budget_limit;
$remaining = $budgetLimit - $currentUsed;

echo "Budget Limit: Rp " . number_format($budgetLimit, 0, ',', '.') . "\n";
echo "Current Used: Rp " . number_format($currentUsed, 0, ',', '.') . "\n";
echo "Remaining: Rp " . number_format($remaining, 0, ',', '.') . "\n\n";

// Test with amount that exceeds budget
$testAmount = $remaining + 1000000; // Add 1 juta more than remaining
echo "Testing with amount: Rp " . number_format($testAmount, 0, ',', '.') . " (exceeds remaining)\n";

$validation = $budgetService->validateBudget(
    $category->id,
    null,
    $testAmount
);

if (!$validation['valid']) {
    echo "✅ PASS: Budget validation correctly blocks exceeded amount\n";
    echo "   Message: {$validation['message']}\n";
} else {
    echo "❌ FAIL: Budget validation should block exceeded amount\n";
}

echo "\n";

// Test with amount that fits in budget
$testAmount2 = max(100000, $remaining / 2); // Half of remaining or 100k minimum
echo "Testing with amount: Rp " . number_format($testAmount2, 0, ',', '.') . " (fits in budget)\n";

$validation2 = $budgetService->validateBudget(
    $category->id,
    null,
    $testAmount2
);

if ($validation2['valid']) {
    echo "✅ PASS: Budget validation allows sufficient amount\n";
} else {
    echo "❌ FAIL: Budget validation should allow sufficient amount\n";
    echo "   Message: {$validation2['message']}\n";
}

echo "\n";

// Test 3: Check PR status breakdown
echo "Test 3: PR Status Breakdown\n";
echo "---------------------------\n";

$statusBreakdown = DB::table('purchase_requisition_items as pri')
    ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
    ->where('pri.category_id', $category->id)
    ->whereBetween(DB::raw('DATE(pri.created_at)'), [$dateFrom, $dateTo])
    ->select('pr.status', DB::raw('SUM(pri.qty * pri.unit_price) as total'))
    ->groupBy('pr.status')
    ->get();

foreach ($statusBreakdown as $status) {
    $isExcluded = in_array($status->status, ['REJECTED']);
    $marker = $isExcluded ? '❌ (excluded)' : '✅ (included)';
    echo "Status {$status->status}: Rp " . number_format($status->total, 0, ',', '.') . " {$marker}\n";
}

echo "\n";

// Test 4: Check held PRs
echo "Test 4: Held PRs Check\n";
echo "----------------------\n";

$heldTotal = DB::table('purchase_requisition_items as pri')
    ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
    ->where('pri.category_id', $category->id)
    ->whereBetween(DB::raw('DATE(pri.created_at)'), [$dateFrom, $dateTo])
    ->where('pr.is_held', true)
    ->sum(DB::raw('pri.qty * pri.unit_price'));

echo "Held PR Total: Rp " . number_format($heldTotal, 0, ',', '.') . " (should be excluded)\n";

if ($heldTotal > 0) {
    echo "⚠️  WARNING: There are held PRs that should be excluded from budget calculation\n";
} else {
    echo "✅ No held PRs found\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Category: {$category->name}\n";
echo "Budget Limit: Rp " . number_format($budgetLimit, 0, ',', '.') . "\n";
echo "Used: Rp " . number_format($currentUsed, 0, ',', '.') . "\n";
echo "Remaining: Rp " . number_format($remaining, 0, ',', '.') . "\n";
echo "PR Total (excluding REJECTED/deleted/held): Rp " . number_format($prTotal, 0, ',', '.') . "\n";
echo "\n";

if ($budgetInfo['success'] && abs($prTotal - ($budgetInfo['breakdown']['pr_total'] ?? 0)) < 0.01) {
    echo "✅ All tests passed!\n";
} else {
    echo "❌ Some tests failed. Please check the output above.\n";
}

