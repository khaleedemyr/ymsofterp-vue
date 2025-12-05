# Metode Perhitungan Budget Info - Purchase Requisition Ops

## 📋 Overview

Budget Info adalah informasi yang menampilkan status penggunaan budget untuk kategori tertentu pada Purchase Requisition Ops. Perhitungan dilakukan **per bulan** (monthly) dan mendukung 2 jenis budget:

1. **GLOBAL BUDGET** - Budget yang berlaku untuk semua outlet
2. **PER_OUTLET BUDGET** - Budget yang dialokasikan per outlet

---

## 🔄 Alur Perhitungan

### 1. Input Parameter

Method `getBudgetInfo()` menerima parameter:
- `category_id` (required) - ID kategori budget
- `outlet_id` (required untuk PER_OUTLET) - ID outlet
- `current_amount` (optional, default: 0) - Jumlah yang sedang diinput
- `year` (optional, default: tahun sekarang) - Tahun budget
- `month` (optional, default: bulan sekarang) - Bulan budget

### 2. Periode Budget

Budget dihitung **per bulan**:
```php
$dateFrom = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year)); // Tanggal 1 bulan
$dateTo = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));   // Tanggal terakhir bulan
```

---

## 📊 Komponen Perhitungan

### A. PAID AMOUNT (Jumlah yang Sudah Dibayar)

#### 1. Paid dari Purchase Order (Non Food Payments)
```php
$paidAmountFromPo = SUM(non_food_payments.amount)
WHERE:
  - purchase_order_ops_id IN (PO yang terkait dengan PR kategori ini)
  - status = 'paid' (HANYA status 'paid', bukan 'approved')
  - status != 'cancelled'
  - payment_date BETWEEN $dateFrom AND $dateTo
```

**Catatan Penting:**
- Hanya menghitung NFP dengan status **'paid'** (bukan 'approved')
- Filter berdasarkan `payment_date` (tanggal pembayaran)
- Hanya untuk PO yang masih ada dan status 'approved'

#### 2. Paid dari Retail Non Food (RNF)
```php
$retailNonFoodApproved = SUM(retail_non_food.total_amount)
WHERE:
  - category_budget_id = $categoryId
  - outlet_id = $outletId (untuk PER_OUTLET)
  - status = 'approved'
  - transaction_date BETWEEN $dateFrom AND $dateTo
```

**Total Paid:**
```php
$paidAmount = $paidAmountFromPo + $retailNonFoodApproved
```

---

### B. UNPAID AMOUNT (Jumlah yang Belum Dibayar)

Unpaid amount terdiri dari 3 komponen:

#### 1. PR Unpaid (Purchase Requisition yang Belum Dibayar)

**Definisi:** PR dengan status `SUBMITTED` atau `APPROVED` yang:
- Belum jadi PO (belum ada Purchase Order)
- Belum jadi NFP (belum ada Non Food Payment)

```php
$prUnpaidAmount = SUM(purchase_requisitions.amount)
WHERE:
  - category_id = $categoryId (atau di items level)
  - outlet_id = $outletId (untuk PER_OUTLET, atau di items level)
  - status IN ('SUBMITTED', 'APPROVED')
  - is_held = false (exclude held PRs)
  - created_at tahun/bulan = $year/$month
  - NOT EXISTS (Purchase Order untuk PR ini)
  - NOT EXISTS (Non Food Payment untuk PR ini)
```

#### 2. PO Unpaid (Purchase Order yang Belum Dibayar)

**Definisi:** PO dengan status `submitted` atau `approved` yang:
- Belum jadi NFP (belum ada Non Food Payment)

```php
$poUnpaidAmount = SUM(purchase_order_ops_items.total)
WHERE:
  - PO terkait dengan PR kategori ini
  - status IN ('submitted', 'approved')
  - created_at tahun/bulan = $year/$month (berdasarkan PR)
  - is_held = false
  - NOT EXISTS (Non Food Payment untuk PO ini)
```

#### 3. NFP Unpaid (Non Food Payment yang Belum Dibayar)

**Definisi:** NFP dengan status `pending` atau `approved` yang belum status `paid`

**Case 1: NFP Langsung dari PR (tanpa PO)**
```php
$nfpUnpaidFromPr = SUM(non_food_payments.amount)
WHERE:
  - purchase_requisition_id IS NOT NULL
  - purchase_order_ops_id IS NULL
  - status IN ('pending', 'approved')
  - status != 'cancelled'
  - payment_date BETWEEN $dateFrom AND $dateTo
  - PR terkait kategori ini
```

**Case 2: NFP Melalui PO**
```php
$nfpUnpaidFromPo = SUM(non_food_payments.amount)
WHERE:
  - purchase_order_ops_id IS NOT NULL
  - status IN ('pending', 'approved')
  - status != 'cancelled'
  - payment_date BETWEEN $dateFrom AND $dateTo
  - PO terkait dengan PR kategori ini
```

**Total NFP Unpaid:**
```php
$nfpUnpaidAmount = $nfpUnpaidFromPr + $nfpUnpaidFromPo
```

**Total Unpaid:**
```php
$unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount
```

---

### C. TOTAL USED AMOUNT (Total Penggunaan Budget)

```php
$categoryUsedAmount = $paidAmount + $unpaidAmount
```

**Komponen:**
- **Paid:** NFP status 'paid' + RNF status 'approved'
- **Unpaid:** PR unpaid + PO unpaid + NFP unpaid (status pending/approved)

---

## 🎯 Perhitungan per Jenis Budget

### 1. GLOBAL BUDGET

```php
// Budget Limit
$categoryBudget = $category->budget_limit

// Total Used
$categoryUsedAmount = $paidAmount + $unpaidAmount

// Dengan Current Input
$totalWithCurrent = $categoryUsedAmount + $currentAmount

// Sisa Budget
$categoryRemainingAmount = $categoryBudget - $categoryUsedAmount
$remainingAfterCurrent = $categoryBudget - $totalWithCurrent

// Exceeds Budget?
$exceedsBudget = $totalWithCurrent > $categoryBudget
```

**Response:**
```json
{
  "budget_type": "GLOBAL",
  "category_budget": 10000000,
  "category_used_amount": 5000000,
  "current_amount": 1000000,
  "total_with_current": 6000000,
  "category_remaining_amount": 5000000,
  "remaining_after_current": 4000000,
  "exceeds_budget": false
}
```

---

### 2. PER_OUTLET BUDGET

```php
// Budget Limit (dari PurchaseRequisitionOutletBudget)
$outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
  ->where('outlet_id', $outletId)
  ->first()
  ->allocated_budget

// Total Used (untuk outlet ini)
$outletUsedAmount = $paidAmount + $unpaidAmount

// Dengan Current Input
$totalWithCurrent = $outletUsedAmount + $currentAmount

// Sisa Budget
$outletRemainingAmount = $outletBudget->allocated_budget - $outletUsedAmount
$remainingAfterCurrent = $outletBudget->allocated_budget - $totalWithCurrent

// Exceeds Budget?
$exceedsBudget = $totalWithCurrent > $outletBudget->allocated_budget
```

**Response:**
```json
{
  "budget_type": "PER_OUTLET",
  "category_budget": 10000000,
  "outlet_budget": 2000000,
  "outlet_used_amount": 1500000,
  "current_amount": 500000,
  "total_with_current": 2000000,
  "outlet_remaining_amount": 500000,
  "remaining_after_current": 0,
  "exceeds_budget": false,
  "outlet_info": {
    "id": 1,
    "name": "Outlet A"
  }
}
```

---

## 📈 Field yang Ditampilkan di Frontend

### 1. Category Budget / Outlet Budget
- **GLOBAL:** `category_budget` (budget limit kategori)
- **PER_OUTLET:** `outlet_budget` (budget alokasi outlet)

### 2. Used This Month
- **GLOBAL:** `category_used_amount`
- **PER_OUTLET:** `outlet_used_amount`

### 3. Current Input
- `current_amount` (jumlah yang sedang diinput user)

### 4. Total After Input
- `total_with_current` = `used_amount` + `current_amount`

### 5. Sisa Budget (Sebelum Input)
- **GLOBAL:** `category_remaining_amount` = `category_budget` - `category_used_amount`
- **PER_OUTLET:** `outlet_remaining_amount` = `outlet_budget` - `outlet_used_amount`

### 6. Sisa Budget (Setelah Input)
- `remaining_after_current` = `budget` - `total_with_current`

---

## ⚠️ Warning & Validation

### 1. Budget Exceeded
```javascript
if (budgetInfo.exceeds_budget) {
  // Tampilkan warning merah
  // "Budget Exceeded! Total melebihi budget yang tersedia."
}
```

### 2. Budget Warning (Low Budget)
```javascript
if (remaining_after_current < (budget * 0.1)) {
  // Tampilkan warning kuning
  // "Budget Warning! Hanya tersisa X setelah input ini."
}
```

---

## 🔍 Catatan Penting

### 1. Struktur Data
- **Old Structure:** Category/Outlet di level PR (`purchase_requisitions.category_id`, `purchase_requisitions.outlet_id`)
- **New Structure:** Category/Outlet di level Items (`purchase_requisition_items.category_id`, `purchase_requisition_items.outlet_id`)
- **Kode mendukung kedua struktur** dengan query `OR` condition

### 2. Status Filter
- **Paid:** Hanya NFP dengan status `'paid'` (bukan `'approved'`)
- **Unpaid PR:** Status `SUBMITTED` atau `APPROVED`
- **Unpaid PO:** Status `submitted` atau `approved`
- **Unpaid NFP:** Status `pending` atau `approved` (belum `paid`)

### 3. Exclude Held PRs
- Semua perhitungan **exclude PR dengan `is_held = true`**

### 4. Monthly Budget
- Budget dihitung **per bulan** berdasarkan:
  - `created_at` untuk PR
  - `payment_date` untuk NFP
  - `transaction_date` untuk RNF

### 5. Matching PO Items dengan PR Items
- Menggunakan `pr_ops_item_id` atau `item_name` untuk matching
- Support backward compatibility dengan old structure

---

## 📝 Contoh Perhitungan

### Scenario: GLOBAL BUDGET

**Setup:**
- Category Budget: Rp 10,000,000
- Bulan: Januari 2024

**Data:**
1. PR #1 (Jan): Rp 2,000,000 (status: APPROVED, belum jadi PO)
2. PR #2 (Jan): Rp 1,500,000 (status: APPROVED, sudah jadi PO, belum dibayar)
3. PO #1 dari PR #2: Rp 1,500,000 (status: approved, belum jadi NFP)
4. NFP #1 (Jan): Rp 500,000 (status: paid, dari PO lain)
5. RNF (Jan): Rp 1,000,000 (status: approved)
6. Current Input: Rp 500,000

**Perhitungan:**
```
Paid Amount:
  - NFP Paid: Rp 500,000
  - RNF Approved: Rp 1,000,000
  Total Paid: Rp 1,500,000

Unpaid Amount:
  - PR Unpaid (PR #1): Rp 2,000,000
  - PO Unpaid (PO #1): Rp 1,500,000
  - NFP Unpaid: Rp 0
  Total Unpaid: Rp 3,500,000

Category Used Amount: Rp 1,500,000 + Rp 3,500,000 = Rp 5,000,000
Total With Current: Rp 5,000,000 + Rp 500,000 = Rp 5,500,000
Remaining After Current: Rp 10,000,000 - Rp 5,500,000 = Rp 4,500,000
```

---

## 🔗 Endpoint

**Route:** `GET /purchase-requisitions/budget-info`

**Controller:** `PurchaseRequisitionController@getBudgetInfo`

**Parameters:**
- `category_id` (required)
- `outlet_id` (required untuk PER_OUTLET)
- `current_amount` (optional)
- `year` (optional, default: current year)
- `month` (optional, default: current month)

---

## 📚 File Terkait

1. **Backend:**
   - `app/Http/Controllers/PurchaseRequisitionController.php` (method `getBudgetInfo`)
   - `app/Models/PurchaseRequisitionCategory.php`
   - `app/Models/PurchaseRequisitionOutletBudget.php`

2. **Frontend:**
   - `resources/js/Pages/PurchaseRequisition/Create.vue`
   - Method: `loadBudgetInfo()`, `loadBudgetInfoForCategory()`

---

## 🎨 UI Display

Budget Info ditampilkan dalam box hijau dengan informasi:
- **Header:** Budget Information (GLOBAL / Per Outlet)
- **4 Kolom:** Category/Outlet Budget, Used This Month, Current Input, Total After Input
- **2 Baris Sisa Budget:** Sebelum Input dan Setelah Input
- **Warning:** Merah jika exceeds, Kuning jika < 10% sisa

---

*Dokumentasi ini menjelaskan logika perhitungan budget info yang digunakan di Purchase Requisition Ops. Untuk pertanyaan atau perubahan, silakan hubungi tim development.*

