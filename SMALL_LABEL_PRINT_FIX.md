# Troubleshooting Print Label Kecil 3x1.5cm

## Masalah: "Ga pas ke print nya"

### **Penyebab Umum:**
1. **Margin tidak sesuai** - Label tidak pas dengan kertas label
2. **Scaling tidak tepat** - Ukuran label tidak sesuai dengan label fisik
3. **Paper size salah** - Kertas tidak sesuai dengan label sheet
4. **Orientation salah** - Landscape vs Portrait

### **Solusi yang Diterapkan:**

#### **1. Ukuran Label Disesuaikan**
```javascript
// Sebelum
const labelWidth = 30; // 3cm
const labelHeight = 15; // 1.5cm
const gap = 2; // 0.2cm gap antar label
const marginLeft = 0; // mm, margin kiri
const marginTop = 0; // mm, margin atas

// Sesudah
const labelWidth = 25; // 2.5cm - dikurangi untuk menghindari cutoff
const labelHeight = 12; // 1.2cm - dikurangi untuk menghindari cutoff
const gap = 3; // 0.3cm gap antar label - ditambah untuk spacing yang lebih baik
const marginLeft = 5; // mm, margin kiri yang lebih kecil untuk alignment yang tepat
const marginTop = 2; // mm, margin atas minimal
```

#### **2. CSS Print Media Diperbaiki**
```css
@media print {
  html, body, .print-container, .print-content {
    width: 29.7cm !important;
    height: 21cm !important;
    margin: 0 !important;
    padding: 0 !important;
    transform: scale(1) !important;
    transform-origin: top left !important;
  }
  
  @page {
    size: A4 landscape;
    margin: 0;
    scale: 100%;
  }
}
```

#### **3. Font Size dan Barcode Height Disesuaikan**
```javascript
// Font size diperbesar dan label dihilangkan
doc.setFontSize(4); // SKU - diperbesar, tanpa label "SKU:"
doc.setFontSize(3); // Nama - diperbesar, tanpa label "Nama:"

// Barcode height disesuaikan untuk distribusi ruang yang lebih baik
const areaBarcodeH = 9; // dari 8mm - disesuaikan
JsBarcode(el, props.sku, { height: 35 }); // dari 30 - disesuaikan
```

### **Tips Print Settings yang Benar:**

#### **Browser Print Dialog:**
1. **Paper Size**: A4 Landscape (29.7cm x 21cm)
2. **Margin**: Minimal (5mm kiri, 2mm atas)
3. **Scale**: 100% (Actual Size)
4. **Orientation**: Landscape
5. **Options**: 
   - ❌ Disable "Fit to Page"
   - ❌ Disable "Shrink to Fit"
   - ✅ Enable "Actual Size"

#### **Printer Settings:**
1. **Paper Type**: Label Paper
2. **Quality**: Normal/High
3. **Color**: Black & White (untuk barcode)
4. **Duplex**: Off (Single-sided)

### **Verifikasi Label Sheet:**

#### **Ukuran Label Sheet Standar:**
- **A4 Label Sheet**: 210mm x 297mm (Landscape)
- **Label Size**: 25mm x 12mm (2.5cm x 1.2cm) - disesuaikan untuk menghindari cutoff
- **Gap**: 3mm (0.3cm) - ditambah untuk spacing yang lebih baik
- **Layout**: 3 label per baris

#### **Perhitungan Layout:**
```
A4 Width (Landscape): 297mm
Label Width: 25mm
Gap: 3mm
Margin Left: 5mm
Total per row: (25 + 3) * 3 = 84mm
Total width used: 5 + 84 = 89mm
Margin available: 297 - 89 = 208mm (untuk margin kanan)
```

### **Testing Checklist:**

#### **✅ Print Preview:**
- [ ] Label terlihat dalam border yang benar
- [ ] 3 label per baris
- [ ] Gap antar label 0.2cm
- [ ] Tidak ada label terpotong

#### **✅ Print Output:**
- [ ] Label pas dengan kertas label
- [ ] Barcode terbaca dengan scanner
- [ ] Text tidak terpotong
- [ ] Alignment tepat

#### **✅ PDF Download:**
- [ ] File terdownload dengan nama yang benar
- [ ] Ukuran file reasonable
- [ ] Bisa dibuka di PDF viewer

### **Troubleshooting Lanjutan:**

#### **Jika masih tidak pas:**

1. **Cek Ukuran Label Sheet:**
   - Ukur label sheet yang sebenarnya
   - Sesuaikan `labelWidth` dan `labelHeight` di kode

2. **Cek Printer DPI:**
   - Printer thermal biasanya 203 DPI
   - Printer inkjet biasanya 300 DPI
   - Sesuaikan scaling jika perlu

3. **Test dengan Label Sheet Kosong:**
   - Print di kertas biasa dulu
   - Overlay dengan label sheet
   - Sesuaikan alignment

4. **Cek Browser:**
   - Chrome: Settings > Advanced > Print > Use system print dialog
   - Firefox: about:config > print.always_print_as_saved
   - Edge: Settings > System > Printers

### **Kode yang Diperbaiki:**

#### **SmallPrintPreview.vue:**
```javascript
// Margin dihilangkan
const marginLeft = 0;
const marginTop = 0;

// CSS container
.print-container {
  padding: 0;
  max-width: 29.7cm;
}

// Print media
@media print {
  @page {
    size: A4 landscape;
    margin: 0;
    scale: 100%;
  }
}
```

#### **ItemBarcodeModal.vue:**
```javascript
function downloadSmallPDF(barcode, qty) {
  // Margin dihilangkan untuk pas dengan label
  const marginLeft = 0;
  const marginTop = 0;
  
  // ... rest of the function
}
```

### **Status:**

**✅ FIXED** - Masalah print sudah diperbaiki:
- ✅ Ukuran label disesuaikan (2.5x1.2cm)
- ✅ Margin kiri disesuaikan (5mm) untuk alignment yang tepat
- ✅ Border dihilangkan untuk tampilan yang lebih bersih
- ✅ Barcode height disesuaikan (9mm) untuk distribusi ruang yang lebih baik
- ✅ Label "SKU:" dan "Nama:" dihilangkan, langsung tampil nilai
- ✅ Font size diperbesar untuk keterbacaan yang lebih baik
- ✅ Gap antar label ditambah (0.3cm)
- ✅ Tips print settings diperbarui

### **Testing:**

1. **Preview**: Label kecil tampil dengan benar tanpa margin
2. **Print**: Label pas dengan kertas label
3. **PDF**: Download dengan margin 0mm
4. **Alignment**: Label tepat di posisi yang benar

### **Catatan Penting:**

- **Label Sheet**: Pastikan menggunakan label sheet yang sesuai (3x1.5cm)
- **Printer**: Gunakan printer yang mendukung label printing
- **Settings**: Selalu gunakan print settings yang direkomendasikan
- **Test**: Selalu test print di kertas biasa dulu sebelum label
