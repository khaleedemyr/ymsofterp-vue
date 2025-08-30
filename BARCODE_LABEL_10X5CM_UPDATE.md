# Update Barcode Label - Ukuran 10x5cm dengan Informasi Lengkap

## Permintaan User
User meminta untuk mengubah ukuran stiker barcode dari 3x1.5cm menjadi 10x5cm dengan informasi yang lebih lengkap, termasuk:
- Barcode
- Kode/SKU
- Nama item
- Warehouse division
- Categories dan sub categories

## Perubahan yang Diterapkan

### 1. Ukuran Label Baru
- **Sebelum**: 30mm x 15mm (3cm x 1.5cm)
- **Sesudah**: 100mm x 50mm (10cm x 5cm)
- **Layout**: 2 label per baris (sebelumnya 3 label per baris)
- **Paper**: A4 Portrait (210mm x 297mm)

### 2. Informasi yang Ditampilkan

#### **A. PDF Output**
```
┌─────────────────────────────────────────────────────────┐
│ [Barcode Image - 90mm x 25mm]                          │
│                                                         │
│ SKU: [Barcode Number]                                   │
│ Nama: [Item Name]                                       │
│ Warehouse: [Warehouse Division Name]                    │
│ Category: [Category Name]                               │
│ Sub Category: [Sub Category Name]                       │
│ Code: [Item Code/SKU]                                   │
└─────────────────────────────────────────────────────────┘
```

#### **B. ZPL Output**
```
- Barcode Code128 (height: 60 dots)
- SKU/Barcode (font: 15pt)
- Nama Item (font: 12pt, max 35 chars per line)
- Warehouse Division (font: 10pt)
- Category (font: 10pt)
- Sub Category (font: 10pt)
- Item Code (font: 10pt)
```

### 3. File yang Dimodifikasi

#### **A. `resources/js/Pages/Items/ItemBarcodeModal.vue`**

**Fungsi `downloadPDF()`:**
```javascript
// Ukuran baru
const labelWidth = 100; // 10cm
const labelHeight = 50; // 5cm
const gap = 5; // 0.5cm gap antar label
const numRows = Math.ceil(numLabels / 2); // 2 label per baris

// Informasi lengkap
doc.text(`SKU: ${sku}`, x + 5, currentY);
doc.text(`Nama: ${itemName}`, x + 5, currentY);
doc.text(`Warehouse: ${warehouseDivision}`, x + 5, currentY);
doc.text(`Category: ${category}`, x + 5, currentY);
doc.text(`Sub Category: ${subCategory}`, x + 5, currentY);
doc.text(`Code: ${itemCode}`, x + 5, currentY);
```

**Fungsi `generateZplBarcode()`:**
```javascript
// Ukuran baru
const labelWidth = 100; // 10cm = 100mm
const labelHeight = 50; // 5cm = 50mm

// Barcode lebih besar
zpl += `^BCN,60,Y,N,N\n`; // height 60 dots

// Informasi lengkap
zpl += `^FO20,${currentY}^A0N,15,15^FD${sku}^FS\n`;
zpl += `^FO20,${currentY}^A1N,12,12^FD${itemName}^FS\n`;
zpl += `^FO20,${currentY}^A1N,10,10^FDWarehouse: ${warehouseDivision}^FS\n`;
zpl += `^FO20,${currentY}^A1N,10,10^FDCategory: ${category}^FS\n`;
zpl += `^FO20,${currentY}^A1N,10,10^FDSub: ${subCategory}^FS\n`;
zpl += `^FO20,${currentY}^A1N,10,10^FDCode: ${itemCode}^FS\n`;
```

#### **B. `resources/js/Pages/Items/PrintPreview.vue`**

**Layout Baru:**
```css
.barcode-row {
  height: 5cm; /* Sebelumnya 1.5cm */
}

.barcode-label {
  width: 10cm; /* Sebelumnya 3cm */
  height: 5cm; /* Sebelumnya 1.5cm */
  border: 1px solid #000;
  justify-content: flex-start;
  align-items: flex-start;
}
```

**Informasi Lengkap:**
```vue
<div class="barcode-info">
  <div class="barcode-text"><strong>SKU: {{ sku }}</strong></div>
  <div class="barcode-name"><strong>Nama: {{ props.name }}</strong></div>
  <div v-if="props.warehouseDivision" class="barcode-detail">Warehouse: {{ props.warehouseDivision }}</div>
  <div v-if="props.category" class="barcode-detail">Category: {{ props.category }}</div>
  <div v-if="props.subCategory" class="barcode-detail">Sub Category: {{ props.subCategory }}</div>
  <div v-if="props.itemCode" class="barcode-detail">Code: {{ props.itemCode }}</div>
</div>
```

### 4. Fitur Baru

#### **A. Print Preview dengan Informasi Lengkap**
- Tombol "Print Preview" (icon eye) ditambahkan
- Preview menampilkan informasi lengkap sesuai ukuran 10x5cm
- Border label untuk memudahkan cutting

#### **B. Data Mapping**
```javascript
// Item data yang digunakan
props.item?.warehouse_division?.name
props.item?.category?.name
props.item?.sub_category?.name
props.item?.sku || props.item?.code
```

### 5. Konfigurasi Print

#### **A. PDF Settings**
- **Orientation**: Portrait
- **Paper Size**: A4 (210mm x 297mm)
- **Label Size**: 100mm x 50mm
- **Gap**: 5mm antar label
- **Margin**: 5mm kiri dan atas

#### **B. ZPL Settings**
- **Label Size**: 100mm x 50mm
- **DPI**: 203 (Zebra ZD220)
- **Barcode Height**: 60 dots
- **Font Sizes**: 15pt, 12pt, 10pt

### 6. User Experience

#### **A. Workflow Baru**
1. User klik tombol barcode di item list
2. Modal barcode terbuka
3. User input quantity untuk setiap barcode
4. User pilih action:
   - **Print Preview**: Preview sebelum print
   - **Download PDF**: Download PDF 10x5cm
   - **Print ZPL**: Print langsung ke printer
   - **Download ZPL**: Download file ZPL

#### **B. Tips Print**
```
Tips: Untuk mencetak X label ukuran 10x5cm, atur paper size di print dialog 
ke 21cm x Ycm (width x height), lalu set margin ke None dan scale ke 100% 
agar hasil presisi. Setiap label berukuran 10cm x 5cm.
```

### 7. Output Format

#### **A. PDF Filename**
```
{barcode}_labels_10x5cm.pdf
```

#### **B. ZPL Filename**
```
{barcode}.zpl
```

### 8. Kompatibilitas

#### **A. Printer Support**
- **Zebra ZD220**: Full support dengan ZPL commands
- **Thermal Printers**: Support dengan ZPL format
- **Laser/Inkjet**: Support dengan PDF format

#### **B. Browser Support**
- **Chrome/Edge**: Full support Web USB API
- **Firefox/Safari**: PDF download support
- **Mobile**: PDF download support

## Status

**✅ COMPLETED** - Fitur barcode label 10x5cm dengan informasi lengkap sudah selesai:
- Ukuran label diubah dari 3x1.5cm menjadi 10x5cm
- Informasi lengkap ditambahkan (barcode, SKU, nama, warehouse, category, sub category, code)
- Layout 2 label per baris untuk ukuran baru
- Print preview dengan informasi lengkap
- PDF dan ZPL output sudah disesuaikan
- Tips print sudah diperbarui

## Langkah Selanjutnya

1. Test fitur dengan berbagai item yang memiliki data lengkap
2. Verifikasi ukuran label sesuai dengan printer yang digunakan
3. Test print preview dan download PDF/ZPL
4. Pastikan informasi warehouse, category, dan sub category muncul dengan benar
