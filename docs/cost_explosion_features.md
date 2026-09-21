# Fitur yang Membuat Cost Inventory Meledak

Dokumen ini mencatat akar masalah cost `food_inventory_stocks` yang tidak wajar (jutaan–triliunan), berdasarkan investigasi Kimchi / MK Production / Main Store (Sep 2026).

## Ringkasan

Ada **2 akar utama** + beberapa efek samping:

| # | Akar masalah | Dampak utama | Status code |
|---|---|---|---|
| 1 | MK Production bagi cost dengan `qty_jadi` (Pack), bukan `qty_small` | Cost hasil produksi × conversion (×150–×4000) | **Fixed** `MKProductionController` |
| 2 | MAC memakai `value` yatim saat `qty ≤ 0` | Cost Main Store / GR / Retail meledak saat stok masuk lagi | **Fixed** Retail + Good Receive + Transfer |
| 3 | Konsumsi bahan tidak mengurangi `value` (hanya qty) | Menambah value yatim → memicu #2 | **Fixed** di MK Production; cek fitur OUT lain |
| 4 | DO/Transfer membawa cost rusak dari MK | Outlet & gudang lain ikut terkontaminasi | Efek samping (perlu repair data + #1) |

---

## 1. MK Production (akar #1 — paling parah)

**File:** `app/Http/Controllers/MKProductionController.php`  
**Fitur UI:** Buat MK Production (web + mobile)

**Bug lama:**
```text
total_bom_cost = Σ (qty_bom_unit × last_cost_small)   ← qty kadang bukan small
last_cost_small_FG = total_bom_cost / qty_jadi         ← qty_jadi = Pack/Bottle
value_in           = qty_small × last_cost_small_FG    ← qty_small = Gram/ml
```

Jika `qty_jadi = 21 Pack` dan `qty_small = 21000 Gram` (conv 1000):
→ value hasil produksi **terlipat ×1000**.

**Item terdampak (contoh):** Kimchi, Simple Syrup, Curry/BBQ/Mushroom/Blackpepper Sauce, Dressing Salad, Beef Sei, Chicken Sei, Galbi, dll. di **MK1 Hot Kitchen** & **MK2 Cold Kitchen**.

**Perbaikan:**
- Total BOM = `Σ (qty_small × last_cost_small)`
- Cost FG = `total_bom_cost / qty_small`
- Medium/large = MAC × conversion
- Value bahan baku ikut berkurang saat konsumsi
- Fallback `unit_jadi` → `unit_id`

**Referensi benar:** pola di `OutletWIPController` (`$total_cost / $qty_small`).

---

## 2. Value yatim + MAC (akar #2 — Main Store)

**File / fitur:**

| Fitur | Controller |
|---|---|
| Retail Warehouse Food | `RetailWarehouseFoodController` |
| Food Good Receive | `FoodGoodReceiveController` (store + update path) |
| Warehouse Transfer (IN tujuan) | `WarehouseTransferController` |
| Internal Warehouse Transfer | `InternalWarehouseTransferController` |
| Food Inventory Adjustment | `FoodInventoryAdjustmentController` |
| Outlet Rejection (pustaka pusat) | `OutletRejectionController` |

**Bug:**
```text
nilai_lama = stock.value          ← tetap besar walau qty sudah 0
mac = (nilai_lama + nilai_baru) / qty_baru
```

Contoh: qty=0, value=65 juta, lalu retail masuk 80 pcs @28.500  
→ MAC ≈ **848.587** (harusnya 28.500).

**Gejala di kartu:**
- `value_in` = harga benar
- `cost_per_small` = MAC gila  
→ mismatch massal di `retail_warehouse_food` (ribuan card sejak Agu 2026).

**Perbaikan:**
```text
nilai_lama = (qty_lama > 0) ? stock.value : 0
last_cost_medium/large = mac × conversion  (bukan harga batch medium saja)
```

---

## 3. Konsumsi / OUT tidak jaga `value`

Saat stok keluar, banyak path hanya mengurangi `qty_*` tanpa menyesuaikan `value` → sisa value yatim.

| Fitur | Catatan |
|---|---|
| MK Production (bahan OUT) | Sudah diperbaiki |
| Delivery Order | Perlu audit — DO dari MK juga menyebarkan cost rusak |
| Warehouse Transfer OUT | Value sumber sering tidak diselaraskan |
| Retail Warehouse Sale | Pakai `qty × last_cost` di kartu; cek update stock.value |

---

## 4. Efek samping penyebaran

Setelah cost MK rusak, transaksi berikut **menyalin** cost itu:

- Delivery Order (MK → outlet)
- Warehouse Transfer (antar gudang)
- Produksi berikutnya yang memakai item rusak sebagai BOM (rantai infeksi: Simple Syrup → Kimchi)

---

## Warehouse terdampak (snapshot investigasi)

| Warehouse | Pola |
|---|---|
| MK1 Hot Kitchen | Puluhan item produksi cost milyar–triliun |
| MK2 Cold Kitchen | Sama (Kimchi, Dressing, Galbi, dll.) |
| Main Store | Cost_small tidak konsisten vs cost_medium; orphan value (~72 baris); MAC retail/GR |

---

## Checklist perbaikan

- [x] Fix kalkulasi cost MK Production
- [x] Fix orphan-value MAC di Retail Warehouse Food + Food Good Receive + Warehouse Transfer
- [x] Mass repair stock cost MK1 / MK2 / Main Store (script)
- [ ] Audit DO / Internal Transfer / Adjustment untuk update `value` saat OUT
- [ ] Rebuild kartu historis (opsional; stock aktif sudah di-repair)

---

## Script terkait

- `scripts/repair_kimchi_ss_cost.php` — repair awal Kimchi + Simple Syrup
- `scripts/repair_mass_inventory_costs.php` — mass repair pass 1 (MK1/MK2/Main Store)
- `scripts/repair_mass_inventory_costs_pass2.php` — pass 2 (orphan cost reset, sauce re-deflate, leftovers)

## Hasil mass repair (21 Sep 2026)

- Pass 1: ~63 item cost di-reset + ~102 orphan value dibersihkan
- Pass 2: orphan cost absurd di-nol/diisi referensi; sauce MK1 di-deflate ulang; Plastik Wrap & Gomatare di-fix
- Sisa value tinggi di Main Store (Fryall, Whole Beef Tenderloin) = qty besar × cost wajar, **bukan** bug

## Fitur monitoring

- Outlet: `/mac-anomaly-tracking` (existing)
- Warehouse (HO/MK): `/warehouse-mac-anomaly-tracking` — scan anomali MAC di `food_inventory_cost_histories` + stok `food_inventory_stocks`
