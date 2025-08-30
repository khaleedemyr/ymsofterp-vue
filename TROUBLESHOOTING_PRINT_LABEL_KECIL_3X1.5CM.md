# Troubleshooting Print Label Kecil 3x1.5cm

## Masalah
Label kecil 3x1.5cm "ga pas ke print nya" (tidak pas saat dicetak) dan margin yang tidak konsisten.

## Solusi yang Diterapkan

### 1. Penyesuaian Ukuran Label
- **Label Width**: 3cm → 2.5cm (dikurangi untuk menghindari cutoff)
- **Label Height**: 1.5cm → 1.2cm (dikurangi untuk menghindari cutoff)
- **Gap antar label**: 0.2cm → 0.3cm (ditambah untuk spacing yang lebih baik)

### 2. Penyesuaian Margin (Iteratif)
- **Margin Kiri**: 
  - Awal: 0mm (terlalu ke kiri)
  - Percobaan 1: 2mm (masih terlalu ke kiri)
  - Percobaan 2: 5mm (masih kurang)
  - Percobaan 3: 10mm (masih kurang)
  - **Final: 15mm** (alignment yang tepat)
- **Margin Atas**: 2mm (tetap minimal)

### 3. Penyesuaian Font dan Barcode
- **Barcode Height**: 8mm → 9mm (disesuaikan)
- **Font SKU**: 4pt (tanpa label "SKU:")
- **Font Nama**: 3pt (tanpa label "Nama:")
- **Border**: Dihapus untuk tampilan yang lebih bersih

### 4. File yang Diperbarui
- `resources/js/Pages/Items/ItemBarcodeModal.vue` - Fungsi `downloadSmallPDF`
- `resources/js/Pages/Items/SmallPrintPreview.vue` - Fungsi `downloadPDF` dan tips print

### 5. Tips Print Terbaru
```
Paper Size: A4 Landscape (29.7cm x 21cm)
Margin: Minimal (15mm kiri, 2mm atas)
Scale: 100% (Actual Size)
Orientation: Landscape
Options: Disable "Fit to Page" dan "Shrink to Fit"
```

## Status
✅ **SELESAI** - Margin sudah disesuaikan ke 15mm untuk alignment yang tepat

## Catatan
- Margin 15mm memberikan ruang yang cukup untuk label tidak terlalu ke kiri
- Konsisten di kedua file (ItemBarcodeModal.vue dan SmallPrintPreview.vue)
- Tips print sudah diperbarui sesuai margin terbaru
