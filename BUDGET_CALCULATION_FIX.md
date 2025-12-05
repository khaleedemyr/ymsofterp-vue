# Fix: Konsistensi Perhitungan Budget Info antara Purchase Requisition Ops dan Opex Report

## 🐛 Masalah yang Ditemukan

Nilai budget info di **Purchase Requisition Ops** dan **Opex Report** berbeda, padahal seharusnya sama.

### Root Cause

1. **Opex Report (PER_OUTLET):**
   - Menggunakan field `prob.used_budget` dari tabel `purchase_requisition_outlet_budgets`
   - Field ini hanya menghitung `SUM(purchase_requisitions.amount)` tanpa memperhitungkan:
     - Paid amount dari `non_food_payments` (status 'paid')
     - Unpaid amount (PR unpaid + PO unpaid + NFP unpaid)
     - Retail Non Food approved
   - Hanya menambahkan RNF approved ke `used_budget`, tapi tidak menghitung unpaid amount

2. **Purchase Requisition Ops (getBudgetInfo):**
   - Menghitung ulang dari transaksi aktual dengan logika lengkap:
     - Paid = NFP status 'paid' + RNF status 'approved'
     - Unpaid = PR unpaid + PO unpaid + NFP unpaid
     - Total Used = Paid + Unpaid

### Contoh Perbedaan

- **Opex Report:** Used Budget = Rp 810.000 (dari `used_budget` + RNF)
- **Purchase Requisition Ops:** Used This Month = Rp 2.468.250 (dari perhitungan lengkap)

---

## ✅ Solusi

Mengubah **Opex Report** untuk PER_OUTLET budget agar menggunakan logika yang sama dengan `getBudgetInfo()` di `PurchaseRequisitionController`.

### Perubahan yang Dilakukan

1. **Menghitung ulang per outlet** (bukan menggunakan `used_budget`)
2. **Menghitung paid amount** dari:
   - `non_food_payments` dengan status 'paid'
   - `retail_non_food` dengan status 'approved'
3. **Menghitung unpaid amount** dari:
   - PR unpaid (PR SUBMITTED/APPROVED yang belum jadi PO dan belum jadi NFP)
   - PO unpaid (PO submitted/approved yang belum jadi NFP)
   - NFP unpaid (NFP pending/approved yang belum status 'paid')
4. **Total used** = Paid + Unpaid

### File yang Diubah

- `app/Http/Controllers/OpexReportController.php`
  - Method: `getAllCategoriesWithBudget()`
  - Bagian: PER_OUTLET budget calculation (line ~496-793)

---

## 📊 Logika Perhitungan (Setelah Fix)

### Untuk PER_OUTLET Budget:

```php
// Per Outlet
$paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
$unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
$outletUsedAmount = $paidAmount + $unpaidAmount;

// Response
[
    'outlet_id' => $outletId,
    'outlet_name' => $outletName,
    'budget_limit' => $allocatedBudget,
    'paid_amount' => $paidAmount,
    'unpaid_amount' => $unpaidAmount,
    'used_amount' => $outletUsedAmount, // Total used (paid + unpaid)
    'remaining_budget' => $allocatedBudget - $outletUsedAmount
]
```

### Komponen Paid Amount:
- **NFP Paid:** `non_food_payments` dengan status 'paid' (bukan 'approved')
- **RNF Approved:** `retail_non_food` dengan status 'approved'

### Komponen Unpaid Amount:
- **PR Unpaid:** PR SUBMITTED/APPROVED yang belum jadi PO dan belum jadi NFP
- **PO Unpaid:** PO submitted/approved yang belum jadi NFP
- **NFP Unpaid:** NFP pending/approved yang belum status 'paid'

---

## 🔍 Verifikasi

Setelah fix ini, nilai budget info di:
- ✅ **Purchase Requisition Ops** (Budget Information)
- ✅ **Opex Report** (Used Budget per outlet)

**Harus sama** karena menggunakan logika perhitungan yang identik.

---

## 📝 Catatan

1. **Field `used_budget`** di tabel `purchase_requisition_outlet_budgets` **tidak digunakan lagi** untuk perhitungan di Opex Report
2. Perhitungan dilakukan **real-time** dari transaksi aktual
3. Logika ini **konsisten** dengan `getBudgetInfo()` di `PurchaseRequisitionController`
4. Budget dihitung **per bulan** berdasarkan `created_at` (PR) dan `payment_date` (NFP)

---

## 🚀 Testing

1. Buka **Purchase Requisition Ops** → Pilih category dan outlet → Lihat "Used This Month"
2. Buka **Opex Report** → Pilih category yang sama → Lihat "Used Budget" untuk outlet yang sama
3. **Kedua nilai harus sama**

---

*Fix ini memastikan konsistensi perhitungan budget di seluruh aplikasi.*

