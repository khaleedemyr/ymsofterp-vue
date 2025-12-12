# Budget Calculation Service - Usage Guide

## Overview
`BudgetCalculationService` adalah service class terpusat untuk menghitung dan memvalidasi budget. Service ini menangani:
- **GLOBAL Budget**: Budget untuk semua outlet (digabungkan)
- **PER_OUTLET Budget**: Budget per outlet spesifik

## Features
1. ✅ Perhitungan konsisten untuk semua menu
2. ✅ Validasi budget saat create PR
3. ✅ Support proporsi untuk PO yang gabungan dari beberapa outlet
4. ✅ Filter berdasarkan PR created_at month (budget adalah monthly)

## Usage

### 1. Get Budget Info

```php
use App\Services\BudgetCalculationService;

$budgetService = new BudgetCalculationService();

// For PER_OUTLET budget
$budgetInfo = $budgetService->getBudgetInfo(
    categoryId: 17,
    outletId: 18,
    dateFrom: '2025-12-01',
    dateTo: '2025-12-31',
    currentAmount: 500000 // Amount being added (optional)
);

// For GLOBAL budget
$budgetInfo = $budgetService->getBudgetInfo(
    categoryId: 17,
    outletId: null, // null for GLOBAL
    dateFrom: '2025-12-01',
    dateTo: '2025-12-31',
    currentAmount: 500000
);
```

### 2. Validate Budget (Before Save PR)

```php
use App\Services\BudgetCalculationService;

$budgetService = new BudgetCalculationService();

// Calculate total amount from PR items
$totalAmount = 0;
foreach ($items as $item) {
    $totalAmount += $item['subtotal'];
}

// Validate budget
$validation = $budgetService->validateBudget(
    categoryId: $categoryId,
    outletId: $outletId, // null for GLOBAL budget
    currentAmount: $totalAmount,
    dateFrom: date('Y-m-01'),
    dateTo: date('Y-m-t')
);

if (!$validation['valid']) {
    return response()->json([
        'success' => false,
        'message' => $validation['message'],
        'budget_info' => $validation['budget_info']
    ], 400);
}

// Continue with PR creation...
```

### 3. Response Structure

```php
// Success Response
[
    'success' => true,
    'budget_type' => 'PER_OUTLET' | 'GLOBAL',
    'outlet_budget' => 2000000, // For PER_OUTLET
    'category_budget' => 45000000, // For GLOBAL
    'outlet_used_amount' => 3191250, // For PER_OUTLET
    'category_used_amount' => 5000000, // For GLOBAL
    'current_amount' => 500000,
    'total_with_current' => 3691250,
    'outlet_remaining_amount' => -1191250, // For PER_OUTLET
    'category_remaining_amount' => 40000000, // For GLOBAL
    'remaining_after_current' => -1691250,
    'exceeds_budget' => true,
    'breakdown' => [
        'pr_unpaid' => 2741250,
        'po_unpaid' => 0,
        'nfp_submitted' => 0,
        'nfp_approved' => 0,
        'nfp_paid' => 450000,
        'retail_non_food' => 0,
    ],
]

// Validation Response
[
    'valid' => false,
    'message' => 'Budget exceeded! Total (Rp 3.691.250) exceeds available budget (Rp 2.000.000). Remaining: Rp -1.691.250',
    'budget_info' => [...]
]
```

## Integration

### Update PurchaseRequisitionController

```php
use App\Services\BudgetCalculationService;

// In getBudgetInfo method
public function getBudgetInfo(Request $request)
{
    $budgetService = new BudgetCalculationService();
    
    $budgetInfo = $budgetService->getBudgetInfo(
        categoryId: $request->input('category_id'),
        outletId: $request->input('outlet_id'),
        dateFrom: $request->input('date_from'),
        dateTo: $request->input('date_to'),
        currentAmount: $request->input('current_amount', 0)
    );
    
    return response()->json($budgetInfo);
}

// In store method (before saving PR)
public function store(Request $request)
{
    // ... validation ...
    
    // For pr_ops and purchase_payment mode, check budget per outlet/category from items
    if ($validated['mode'] === 'pr_ops' || $validated['mode'] === 'purchase_payment') {
        // Group items by outlet_id and category_id
        $budgetChecks = [];
        foreach ($validated['items'] as $item) {
            if (!empty($item['category_id'])) {
                $key = $item['outlet_id'] . '_' . $item['category_id'];
                if (!isset($budgetChecks[$key])) {
                    $budgetChecks[$key] = [
                        'outlet_id' => $item['outlet_id'],
                        'category_id' => $item['category_id'],
                        'amount' => 0
                    ];
                }
                $budgetChecks[$key]['amount'] += $item['subtotal'];
            }
        }
        
        // Validate each outlet/category combination
        $budgetService = new BudgetCalculationService();
        foreach ($budgetChecks as $check) {
            $validation = $budgetService->validateBudget(
                categoryId: $check['category_id'],
                outletId: $check['outlet_id'],
                currentAmount: $check['amount']
            );
            
            if (!$validation['valid']) {
                return back()->withErrors([
                    'budget_exceeded' => $validation['message']
                ]);
            }
        }
    } else if ($validated['category_id']) {
        // For other modes (kasbon, travel_application), use main category_id and outlet_id
        $budgetService = new BudgetCalculationService();
        $validation = $budgetService->validateBudget(
            categoryId: $validated['category_id'],
            outletId: $validated['outlet_id'] ?? null,
            currentAmount: $validated['amount']
        );
        
        if (!$validation['valid']) {
            return back()->withErrors([
                'budget_exceeded' => $validation['message']
            ]);
        }
    }
    
    // Continue with PR creation...
}
```

### Update OpexReportController

```php
use App\Services\BudgetCalculationService;

// In getAllCategoriesWithBudget method
private function getAllCategoriesWithBudget($dateFrom = null, $dateTo = null)
{
    $budgetService = new BudgetCalculationService();
    
    foreach ($categories as $category) {
        if ($category->budget_type === 'PER_OUTLET') {
            foreach ($outletBudgets as $outletBudget) {
                $budgetInfo = $budgetService->getBudgetInfo(
                    categoryId: $category->id,
                    outletId: $outletBudget->outlet_id,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo
                );
                
                // Use budgetInfo data...
            }
        } else {
            $budgetInfo = $budgetService->getBudgetInfo(
                categoryId: $category->id,
                outletId: null,
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );
            
            // Use budgetInfo data...
        }
    }
}
```

## Important Notes

1. **Budget is Monthly**: Filter berdasarkan PR created_at month, bukan payment_date
2. **Proportion Calculation**: Untuk PER_OUTLET, hitung proporsi jika PO gabungan dari beberapa outlet
3. **No Double Counting**: Pastikan tidak menghitung payment yang sama dua kali
4. **PR Created At Month**: PO IDs query harus filter by PR created_at month

