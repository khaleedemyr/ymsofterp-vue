# Analisis Perubahan Database untuk Multiple Sources Contra Bon

## Status: TIDAK ADA PERUBAHAN WAJIB

### 1. Tabel `food_contra_bons`
**Status**: ✅ TIDAK PERLU PERUBAHAN
- Kolom yang ada sudah cukup: `source_type`, `source_id`, `po_id`, `gr_id`
- Untuk multiple sources, kita menggunakan source pertama sebagai primary source (untuk backward compatibility)
- Data lama tetap aman dan bisa dibaca dengan benar

### 2. Tabel `food_contra_bon_items`
**Status**: ✅ TIDAK PERLU PERUBAHAN WAJIB (OPSIONAL untuk tracking lebih baik)

**Kolom yang sudah ada:**
- `contra_bon_id` - ID contra bon
- `item_id` - ID item (bisa null untuk retail food)
- `po_item_id` - ID PO item (untuk purchase order)
- `gr_item_id` - ID GR item (untuk purchase order) ✅ SUDAH ADA
- `quantity` - Quantity
- `unit_id` - ID unit (bisa null untuk retail food)
- `price` - Harga
- `total` - Total
- `notes` - Catatan

**Kolom yang OPSIONAL (untuk tracking lebih baik):**
- `retail_food_item_id` - ID item dari retail_food_items (OPSIONAL)
- `warehouse_retail_food_item_id` - ID item dari retail_warehouse_food_items (OPSIONAL)

### 3. Kompatibilitas Data Lama

✅ **DATA LAMA 100% AMAN** karena:
1. Tidak ada perubahan struktur tabel yang wajib
2. Backend masih membaca `source_type` dan `source_id` dari level `contra_bon`
3. Item-item dari data lama tetap bisa ditampilkan dengan benar
4. Form edit tetap bisa membaca data lama dengan benar

### 4. Cara Kerja Multiple Sources

**Tanpa kolom baru (cara saat ini):**
- Purchase Order: Track via `gr_item_id` dan `po_item_id` ✅
- Retail Food: Track via `source_id` di `contra_bon` + `item_name` (kurang akurat tapi bisa)
- Warehouse Retail Food: Track via `source_id` di `contra_bon` + `item_name` (kurang akurat tapi bisa)

**Dengan kolom baru (opsional, untuk tracking lebih baik):**
- Purchase Order: Track via `gr_item_id` dan `po_item_id` ✅
- Retail Food: Track via `retail_food_item_id` ✅ (lebih akurat)
- Warehouse Retail Food: Track via `warehouse_retail_food_item_id` ✅ (lebih akurat)

### 5. Rekomendasi

**OPSI 1: Tanpa perubahan database (REKOMENDASI untuk sekarang)**
- ✅ Data lama 100% aman
- ✅ Fitur multiple sources sudah berfungsi
- ⚠️ Tracking retail food kurang akurat (tapi masih bisa digunakan)

**OPSI 2: Dengan perubahan database (untuk tracking lebih baik)**
- ✅ Tracking lebih akurat untuk retail food
- ✅ Bisa mencegah duplikasi item dengan lebih baik
- ⚠️ Perlu update backend untuk menyimpan kolom baru
- ⚠️ Data lama tetap aman (kolom nullable)

## Kesimpulan

**TIDAK ADA PERUBAHAN DATABASE YANG WAJIB**
- Fitur multiple sources sudah berfungsi dengan struktur tabel yang ada
- Data lama 100% aman
- Query SQL untuk kolom tracking (retail_food_item_id, warehouse_retail_food_item_id) adalah OPSIONAL dan hanya untuk tracking yang lebih baik

