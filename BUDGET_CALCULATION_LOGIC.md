# Budget Calculation Logic - PR-PO-NFP Relationship

## Skenario Kompleks

### 1. 1 PR → Beberapa PO ✅
**Skenario**: Satu PR bisa dibuat menjadi beberapa PO
**Solusi**: 
- Loop semua PO yang terkait dengan PR untuk outlet/category ini
- Filter PO berdasarkan PR created_at month (budget adalah monthly)
- **Status**: ✅ Sudah benar

### 2. 1 PO → Gabungan Beberapa PR dan Outlet ✅
**Skenario**: Satu PO bisa berisi items dari beberapa PR dan outlet yang berbeda
**Solusi**: 
- Hitung proporsi PO items untuk outlet/category ini:
  ```
  proportion = (total PO items untuk outlet/category ini) / (total semua PO items di PO)
  allocated_amount = payment_amount * proportion
  ```
- Contoh: PO total Rp 3.150.000, items untuk outlet ini Rp 450.000 → proportion = 14.29%
- Payment Rp 3.150.000 → allocated untuk outlet ini = Rp 450.000
- **Status**: ✅ Sudah benar

### 3. 1 NFP → 1 PO (Struktur Saat Ini) ✅
**Skenario**: Satu NFP biasanya membayar 1 PO (via `purchase_order_ops_id`)
**Solusi**: 
- Loop per PO, hitung proporsi per payment
- Jika 1 NFP membayar 1 PO: gunakan logika proporsi per PO (sudah benar)
- **Status**: ✅ Sudah benar

### 4. 1 NFP → Beberapa PO (Jika Ada) ⚠️
**Skenario**: Jika ada struktur di mana 1 NFP bisa membayar beberapa PO
**Solusi**: 
- Perlu struktur tambahan (misalnya pivot table `nfp_po` atau field `po_ids` di NFP)
- Distribusi proporsi berdasarkan PO items untuk setiap PO
- **Status**: ⚠️ Perlu verifikasi struktur database (saat ini 1 NFP = 1 PO)

## Logika Perhitungan Saat Ini

### PR Unpaid
**Kondisi**:
- PR dengan status SUBMITTED/APPROVED
- Belum dibuat PO (`whereNull('poo.id')`)
- Belum dibuat NFP langsung (`whereNull('nfp.id')`)
- Filter: PR created_at dalam bulan yang sesuai (budget adalah monthly)

**Perhitungan**:
- Untuk PR Ops: hitung berdasarkan items di outlet/category ini
- Untuk mode lain: gunakan PR amount

### PO Unpaid
**Kondisi**:
- PO dengan status SUBMITTED/APPROVED
- Belum dibuat NFP (`whereNull('nfp.id')`)
- Filter: PO items untuk outlet/category ini (sudah proporsional)

**Perhitungan**:
- Query sudah menghitung hanya PO items untuk outlet/category ini
- `SUM(poi.total)` sudah benar karena sudah di-filter by outlet_id dan category_id

### NFP Paid
**Kondisi**:
- NFP dengan status 'paid'
- Filter: payment_date dalam rentang tanggal
- PO status harus 'approved' (tidak deleted)

**Perhitungan Proporsi**:
```
1. Get PO items untuk outlet/category ini
2. Get total semua PO items di PO tersebut
3. Hitung proportion = (outlet items) / (total items)
4. Alokasikan: allocated_amount = payment_amount * proportion
```

**Contoh**:
- PO #252: Total items Rp 3.150.000, items untuk outlet ini Rp 450.000
- Proportion = 450.000 / 3.150.000 = 14.29%
- Payment Rp 3.150.000 → Allocated untuk outlet ini = Rp 450.000

## Catatan Penting

1. **Budget adalah Monthly**: 
   - Filter berdasarkan PR created_at month, bukan payment_date
   - PO IDs query harus filter by PR created_at month (untuk menghindari PR dari bulan lain)

2. **Proporsi**: 
   - Selalu hitung proporsi untuk PO yang gabungan dari beberapa outlet
   - Formula: `proportion = (outlet items) / (total PO items)`
   - Alokasikan payment: `allocated = payment * proportion`

3. **No Double Counting**: 
   - Pastikan tidak menghitung payment yang sama dua kali
   - Setiap PO hanya dihitung sekali per outlet/category
   - Setiap NFP hanya dihitung sekali per PO

4. **Filter Konsisten**:
   - PR Unpaid: Filter by PR created_at month
   - PO Unpaid: Filter by PR created_at month (karena PO berasal dari PR)
   - NFP Paid: Filter by payment_date (untuk tracking payment bulan ini)

## Implementasi

Semua logika sudah diimplementasikan di:
- `OpexReportController.php` - Method `getAllCategoriesWithBudget()`
- `PurchaseRequisitionController.php` - Method `getBudgetInfo()`

Kedua method menggunakan logika yang sama untuk konsistensi.

