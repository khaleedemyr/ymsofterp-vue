# Validasi Budget di Modal Approval PR Ops

## Status Saat Ini

**TIDAK ADA validasi budget yang mencegah approve** di modal approval PR Ops di `Home.vue`.

### Yang Ada Saat Ini:
1. ✅ **Budget info ditampilkan** di modal approval (informasi saja)
2. ✅ **Warning message** jika budget exceeded (hanya peringatan visual)
3. ❌ **TIDAK ADA validasi** yang mencegah approve jika melebihi budget
4. ❌ **TIDAK ADA validasi** di backend method `approve()` di `PurchaseOrderOpsController`

## Lokasi Kode

### Frontend (Home.vue)
- **File**: `resources/js/Pages/Home.vue`
- **Fungsi approve**: `approvePoOps(poId)` (line ~2206)
- **Budget info**: `poOpsApprovalBudgetInfo` (ref, line ~102)
- **Button approve**: Line ~7258
- **Warning message**: Line ~7022-7031

### Backend (PurchaseOrderOpsController)
- **File**: `app/Http/Controllers/PurchaseOrderOpsController.php`
- **Method approve**: `approve(Request $request, $id)` (line ~1166)
- **Status**: Tidak ada validasi budget di method ini

## Perhitungan Budget (Logika yang Sama dengan PR)

Perhitungan budget menggunakan logika yang sama dengan Purchase Requisition. Method `validateBudgetLimit()` di `PurchaseRequisitionController` (line ~4484) menjelaskan perhitungannya:

### Untuk GLOBAL BUDGET (budget_type = 'GLOBAL'):

```
Used Amount = Paid + Unpaid PR + Retail Non Food

Dimana:
1. Paid = 
   - Non Food Payments (status: paid/approved, bukan cancelled)
   - Retail Non Food Approved
   
2. Unpaid PR = 
   - PR yang sudah dibuat PO (PO status: approved)
   - Total PO per PR - Total Paid per PR
   - Hanya PR yang tidak di-hold (is_held = false)
   
3. Retail Non Food Pending = 
   - Retail Non Food dengan status pending

Total Used = Paid + Unpaid PR + Retail Non Food Pending
Total With Current = Total Used + Amount dari PR/PO yang akan di-approve

Validasi: Total With Current > Category Budget Limit → EXCEEDED
```

### Untuk PER_OUTLET BUDGET (budget_type = 'PER_OUTLET'):

```
Used Amount = Paid + Unpaid PR + Retail Non Food (per outlet)

Logika sama dengan GLOBAL, tapi:
- Hanya menghitung untuk outlet tertentu
- Membandingkan dengan Outlet Budget Allocation (allocated_budget)
- Retail Non Food juga difilter per outlet

Validasi: Total With Current > Outlet Allocated Budget → EXCEEDED
```

### Komponen Breakdown Budget:

1. **PR Unpaid**: PR Submitted & Approved yang belum dibuat PO
2. **PO Unpaid**: PO Submitted & Approved yang belum dibuat NFP
3. **NFP Submitted**: Non Food Payment dengan status submitted
4. **NFP Approved**: Non Food Payment dengan status approved
5. **NFP Paid**: Non Food Payment dengan status paid
6. **Retail Non Food**: Retail Non Food dengan status approved

## Cara Menambahkan Validasi Budget

### Opsi 1: Validasi di Frontend (Sebelum Approve)

Tambahkan validasi di fungsi `approvePoOps()` di `Home.vue`:

```javascript
async function approvePoOps(poId) {
    // Cek budget sebelum approve
    if (poOpsApprovalBudgetInfo.value) {
        const remaining = getPoOpsRemainingAmount();
        const poTotal = selectedPoOpsApproval.value?.grand_total || 0;
        
        if (remaining < poTotal) {
            Swal.fire({
                icon: 'error',
                title: 'Budget Exceeded',
                text: `Tidak dapat approve karena melebihi budget. Sisa budget: Rp ${new Intl.NumberFormat('id-ID').format(remaining)}`,
                confirmButtonText: 'OK'
            });
            return;
        }
    }
    
    // Lanjutkan approve...
    try {
        const response = await axios.post(`/po-ops/${poId}/approve`, {
            approved: true,
            comments: ''
        });
        // ... rest of code
    } catch (error) {
        // ... error handling
    }
}
```

### Opsi 2: Validasi di Backend (Recommended)

Tambahkan validasi di method `approve()` di `PurchaseOrderOpsController.php`:

```php
public function approve(Request $request, $id)
{
    $po = PurchaseOrderOps::findOrFail($id);
    
    // Validasi budget jika approve
    if ($request->approved) {
        // Get source PR untuk mendapatkan category_id dan outlet_id
        if ($po->source_type === 'purchase_requisition_ops' && $po->source_id) {
            $sourcePr = PurchaseRequisition::find($po->source_id);
            
            if ($sourcePr) {
                // Gunakan method validateBudgetLimit dari PurchaseRequisitionController
                $prController = new PurchaseRequisitionController();
                $budgetValidation = $prController->validateBudgetLimit(
                    $sourcePr->category_id,
                    $sourcePr->outlet_id,
                    $po->grand_total,
                    null // excludeId jika perlu
                );
                
                if (!$budgetValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $budgetValidation['message']
                    ], 400);
                }
            }
        }
    }
    
    // Lanjutkan proses approve...
    // ... rest of code
}
```

**Catatan**: Method `validateBudgetLimit()` adalah private, jadi perlu:
1. Buat method public baru di `PurchaseRequisitionController` untuk validasi budget
2. Atau pindahkan logika validasi ke Service/Helper class
3. Atau buat method baru di `PurchaseOrderOpsController` yang menggunakan logika yang sama

### Opsi 3: Validasi di Keduanya (Frontend + Backend)

- Frontend: Validasi untuk UX (memberikan feedback cepat)
- Backend: Validasi untuk security (mencegah bypass dari frontend)

## Rekomendasi

**Gunakan Opsi 2 (Backend Validation)** karena:
1. ✅ Security: Tidak bisa di-bypass dari frontend
2. ✅ Konsisten: Menggunakan logika yang sama dengan PR
3. ✅ Reliable: Data selalu up-to-date dari database

## Testing

Setelah menambahkan validasi:

1. **Test Case 1**: Approve PO yang tidak melebihi budget
   - Expected: Approve berhasil

2. **Test Case 2**: Approve PO yang melebihi budget
   - Expected: Approve gagal dengan error message

3. **Test Case 3**: Approve PO dengan budget tepat di limit
   - Expected: Approve berhasil (tidak melebihi)

4. **Test Case 4**: Approve PO dengan budget type GLOBAL
   - Expected: Validasi menggunakan category budget

5. **Test Case 5**: Approve PO dengan budget type PER_OUTLET
   - Expected: Validasi menggunakan outlet budget

## Catatan Penting

- Budget dihitung per bulan (current month & year)
- Budget calculation menggunakan:
  - Non Food Payments (paid/approved)
  - PO yang sudah approved
  - Retail Non Food (approved + pending)
  - PR yang belum dibuat PO (unpaid)
- PR yang di-hold (`is_held = true`) tidak dihitung dalam unpaid amount
- PO total digunakan jika ada, jika tidak menggunakan PR amount

