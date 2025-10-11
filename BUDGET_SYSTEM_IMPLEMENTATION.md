# Purchase Requisition Budget System Implementation

## 📋 Overview

Implementasi sistem budget yang mendukung dua tipe:
1. **GLOBAL**: Budget dihitung total semua outlet dalam kategori
2. **PER_OUTLET**: Budget dihitung per outlet dalam kategori

## 🗄️ Database Changes

### 1. Alter Table Categories
```sql
ALTER TABLE purchase_requisition_categories 
ADD COLUMN budget_type ENUM('GLOBAL', 'PER_OUTLET') DEFAULT 'GLOBAL' AFTER budget_limit;
```

### 2. Create Outlet Budgets Table
```sql
CREATE TABLE purchase_requisition_outlet_budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    outlet_id BIGINT UNSIGNED NOT NULL,
    allocated_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    used_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (category_id) REFERENCES purchase_requisition_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (outlet_id) REFERENCES tbl_data_outlet(id_outlet) ON DELETE CASCADE,
    UNIQUE KEY unique_category_outlet (category_id, outlet_id)
);
```

## 🏗️ Model Changes

### 1. PurchaseRequisitionCategory
- Added `budget_type` to fillable
- Added `outletBudgets()` relationship
- Added helper methods: `isGlobalBudget()`, `isPerOutletBudget()`
- Added scope: `byBudgetType()`

### 2. PurchaseRequisitionOutletBudget (New Model)
- Manages outlet-specific budget allocations
- Includes budget status calculations
- Helper methods for budget validation

## 🎮 Controller Changes

### 1. PurchaseRequisitionController
- Updated `getBudgetInfo()` method to handle both budget types
- Added `validateBudgetLimit()` helper method
- Updated `store()` and `update()` methods to use new validation

### 2. Budget Validation Logic
```php
// GLOBAL: Calculate across all outlets
if ($category->isGlobalBudget()) {
    $usedAmount = PurchaseRequisition::where('category_id', $categoryId)
        ->whereYear('created_at', $currentYear)
        ->whereMonth('created_at', $currentMonth)
        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
        ->sum('amount');
}

// PER_OUTLET: Calculate per specific outlet
else if ($category->isPerOutletBudget()) {
    $usedAmount = PurchaseRequisition::where('category_id', $categoryId)
        ->where('outlet_id', $outletId)
        ->whereYear('created_at', $currentYear)
        ->whereMonth('created_at', $currentMonth)
        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
        ->sum('amount');
}
```

## 🎨 Frontend Changes

### 1. API Calls
- Updated budget info API calls to include `outlet_id` parameter
- Both Create.vue and Edit.vue updated

### 2. UI Updates
- Dynamic budget info display based on budget type
- Different labels for GLOBAL vs PER_OUTLET
- Outlet-specific information for PER_OUTLET budgets

## 📊 API Response Structure

### GLOBAL Budget Response
```json
{
  "success": true,
  "budget_type": "GLOBAL",
  "category_budget": 40000000,
  "category_used_amount": 15000000,
  "current_amount": 5000000,
  "total_with_current": 20000000,
  "category_remaining_amount": 25000000,
  "remaining_after_current": 20000000,
  "exceeds_budget": false
}
```

### PER_OUTLET Budget Response
```json
{
  "success": true,
  "budget_type": "PER_OUTLET",
  "category_budget": 40000000,
  "outlet_budget": 15000000,
  "outlet_used_amount": 5000000,
  "current_amount": 2000000,
  "total_with_current": 7000000,
  "outlet_remaining_amount": 10000000,
  "remaining_after_current": 8000000,
  "exceeds_budget": false,
  "outlet_info": {
    "id": 1,
    "name": "Outlet Name"
  }
}
```

## 🔧 Usage Instructions

### 1. Setup Database
```bash
# Run the SQL script
mysql -u username -p database_name < database/sql/update_budget_system.sql
```

### 2. Configure Categories
```php
// Set category to PER_OUTLET
$category = PurchaseRequisitionCategory::find(1);
$category->update(['budget_type' => 'PER_OUTLET']);

// Setup outlet budgets
PurchaseRequisitionOutletBudget::create([
    'category_id' => 1,
    'outlet_id' => 1,
    'allocated_budget' => 15000000
]);
```

### 3. Frontend Usage
- Budget info will automatically load based on category and outlet selection
- UI will show appropriate budget type information
- Validation will prevent budget exceeded submissions

## 🚀 Migration Strategy

1. **Phase 1**: Deploy database changes (backward compatible)
2. **Phase 2**: Set specific categories to PER_OUTLET
3. **Phase 3**: Configure outlet budgets for PER_OUTLET categories
4. **Phase 4**: Test and validate functionality

## ✅ Benefits

- **Backward Compatible**: Existing GLOBAL budgets continue to work
- **Flexible**: Mix GLOBAL and PER_OUTLET in same system
- **Clear Logic**: Easy to understand and maintain
- **Real-time Validation**: Immediate feedback on budget status
- **Comprehensive UI**: Clear indication of budget type and status

## 🔍 Testing

### Test Cases
1. GLOBAL budget validation across all outlets
2. PER_OUTLET budget validation per outlet
3. Budget exceeded scenarios
4. Mixed budget types in same system
5. Frontend UI updates based on budget type

### Sample Data
- Marketing Enhancement: PER_OUTLET (15M per outlet)
- Daily Regular Expenses (MAINTENANCE): PER_OUTLET (20M per outlet)
- Other categories: GLOBAL (existing behavior)
