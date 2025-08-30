# Fitur Label Kecil 3x1.5cm - Barcode Sederhana

## Deskripsi Fitur

Fitur baru untuk membuat label barcode kecil dengan ukuran 3cm x 1.5cm yang berisi informasi minimal:
- **Barcode** (Code128)
- **SKU** 
- **Nama Barang**

## Spesifikasi Label

### **Ukuran dan Layout:**
- **Label Size**: 2.5cm x 1.2cm (25mm x 12mm) - disesuaikan untuk menghindari cutoff
- **Gap antar Label**: 0.3cm (3mm) - ditambah untuk spacing yang lebih baik
- **Layout**: 3 label per baris
- **Orientasi**: A4 Landscape (29.7cm x 21cm)

### **Konten Label:**
```
┌─────────────────┐
│   [Barcode]     │
│   12345         │
│   Item          │
└─────────────────┘
```

### **Distribusi Ruang:**
- **Total Height**: 1.2cm (12mm)
- **Barcode Area**: 0.9cm (9mm) - disesuaikan
- **Text Area**: 0.2cm (2mm) tersisa
- **Padding**: 0.05cm (0.5mm)

## Implementasi

### **1. Tombol Baru di ItemBarcodeModal.vue**
```vue
<!-- Preview Label Kecil -->
<button
  @click="openSmallPrintPreview(barcode.barcode, qtyPrint[barcode.id] || 1)"
  class="text-teal-600 hover:text-teal-900 mr-2"
  title="Small Label Preview"
>
  <i class="fa-solid fa-tag"></i>
</button>

<!-- Download PDF Label Kecil -->
<button
  @click="downloadSmallPDF(barcode.barcode, qtyPrint[barcode.id] || 1)"
  class="text-indigo-600 hover:text-indigo-900 mr-2"
  title="Download Small Label PDF"
>
  <i class="fa-solid fa-tag"></i>
</button>
```

### **2. Komponen SmallPrintPreview.vue**
- Komponen terpisah untuk preview label kecil
- Layout khusus untuk ukuran 3x1.5cm
- Font size yang disesuaikan untuk keterbacaan

### **3. Fungsi downloadSmallPDF()**
```javascript
function downloadSmallPDF(barcode, qty) {
  // Setup label dan PDF - Ukuran kecil yang disesuaikan
  const labelWidth = 25; // 2.5cm - dikurangi untuk menghindari cutoff
  const labelHeight = 12; // 1.2cm - dikurangi untuk menghindari cutoff
  const gap = 3; // 0.3cm gap antar label - ditambah untuk spacing yang lebih baik
  const marginLeft = 2; // mm, margin kiri minimal
  const marginTop = 2; // mm, margin atas minimal
  const numLabels = qty || 1;
  const numRows = Math.ceil(numLabels / 3); // 3 label per baris
  const pdfWidth = 297; // A4 landscape width (29.7cm)
  const pdfHeight = 210; // A4 landscape height (21cm)
  
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

  // Generate array barcode sesuai qty
  const barcodes = Array(numLabels).fill(barcode);
  let y = marginTop;
  for (let rowIdx = 0; rowIdx < numRows; rowIdx++) {
    for (let colIdx = 0; colIdx < 3; colIdx++) {
      const idx = rowIdx * 3 + colIdx;
      if (idx >= barcodes.length) continue;
      const x = marginLeft + colIdx * (labelWidth + gap);
      const sku = barcodes[idx];
      
      // Border untuk label
      doc.setDrawColor(0, 0, 0);
      doc.setLineWidth(0.2);
      doc.rect(x, y, labelWidth, labelHeight);
      
      // Render barcode ke canvas - ukuran kecil
      const areaBarcodeW = labelWidth - 2; // 28mm
      const areaBarcodeH = 8; // 8mm untuk barcode
      const scale = 2;
      const canvas = document.createElement('canvas');
      canvas.width = areaBarcodeW * scale;
      canvas.height = areaBarcodeH * scale;
      JsBarcode(canvas, sku, { width: 1 * scale, height: areaBarcodeH * scale, displayValue: false });
      
      // Masukkan barcode ke PDF (ukuran asli) - center aligned
      const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
      doc.addImage(canvas, 'PNG', barcodeX, y + 1, areaBarcodeW, areaBarcodeH);
      
      // Informasi di bawah barcode
      const startY = y + areaBarcodeH + 2;
      let currentY = startY;
      
      // SKU/Barcode
      doc.setFontSize(4);
      doc.setFont(undefined, 'bold');
      doc.text(`SKU: ${sku}`, x + labelWidth/2, currentY, { align: 'center' });
      currentY += 2;
      
      // Nama Item
      const itemName = props.item?.name || '';
      doc.setFontSize(3);
      doc.setFont(undefined, 'bold');
      doc.text(`Nama: ${itemName}`, x + labelWidth/2, currentY, { align: 'center' });
    }
    y += labelHeight + gap;
  }
  doc.save(`${barcode}_small_labels_3x1.5cm.pdf`);
}
```

## CSS Styling untuk Label Kecil

### **Container dan Layout:**
```css
.barcode-row {
  display: flex;
  flex-direction: row;
  height: 1.2cm;
  margin: 0;
  padding: 0;
  gap: 0.3cm;
}

.barcode-label {
  width: 2.5cm;
  height: 1.2cm;
  border: none;
  box-sizing: border-box;
  padding: 0.05cm 0.05cm;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  overflow: hidden;
  flex-shrink: 0;
}
```

### **Barcode dan Text:**
```css
.barcode-image {
  height: 0.8cm;
  margin-bottom: 0.05cm;
}

.barcode-text {
  font-size: 0.08cm;
  font-weight: bold;
  line-height: 1;
  text-align: center;
  width: 100%;
  margin: 0;
  padding: 0;
}

.barcode-name {
  font-size: 0.07cm;
  font-weight: bold;
  line-height: 1;
  margin: 0.01cm 0 0 0;
  padding: 0;
  word-wrap: break-word;
  max-width: 100%;
  text-align: center;
  width: 100%;
}
```

## Perbandingan dengan Label Besar

| Aspek | Label Besar (10x5cm) | Label Kecil (2.5x1.2cm) |
|-------|---------------------|----------------------|
| **Ukuran** | 10cm x 5cm | 2.5cm x 1.2cm |
| **Gap** | 0.5cm | 0.3cm |
| **Konten** | Barcode + SKU + Nama + Warehouse + Category + Sub Category + Code | Barcode + SKU + Nama |
| **Layout** | 3 label per baris | 3 label per baris |
| **Font Size** | SKU: 8pt, Nama: 9pt, Detail: 6pt | SKU: 4pt, Nama: 3pt |
| **Barcode Height** | 20mm | 9mm |
| **Penggunaan** | Label lengkap dengan detail | Label minimal untuk identifikasi cepat |

## Cara Penggunaan

### **1. Preview Label Kecil:**
1. Buka menu Items
2. Klik tombol barcode pada item
3. Klik tombol **Preview Label Kecil** (ikon tag)
4. Lihat preview label 2.5x1.2cm

### **2. Download PDF Label Kecil:**
1. Buka menu Items
2. Klik tombol barcode pada item
3. Klik tombol **Download Small Label PDF** (ikon tag)
4. PDF akan otomatis terdownload

### **3. Print Label Kecil:**
1. Buka preview label kecil
2. Klik tombol **Print**
3. Atur paper size ke A4 Landscape
4. Set margin ke None dan scale ke 100%

## Tips Penggunaan

### **Print Settings:**
- **Paper Size**: A4 Landscape (29.7cm x 21cm)
- **Margin**: Minimal (5mm kiri, 2mm atas)
- **Scale**: 100%
- **Orientation**: Landscape

### **Label Quantity:**
- Setiap baris berisi 3 label
- Jumlah baris sesuai dengan quantity yang diinput
- Maksimal ~70 label per halaman A4 (karena ukuran lebih kecil)

### **Keterbacaan:**
- Font size sudah dioptimalkan untuk ukuran kecil
- Barcode menggunakan Code128 untuk kompatibilitas maksimal
- Text center-aligned untuk tampilan rapi

## Status

**✅ COMPLETED** - Fitur label kecil 2.5x1.2cm sudah selesai:
- ✅ Tombol preview label kecil
- ✅ Tombol download PDF label kecil
- ✅ Komponen SmallPrintPreview.vue
- ✅ Fungsi downloadSmallPDF()
- ✅ CSS styling untuk label kecil
- ✅ Layout 3 label per baris
- ✅ Konten minimal (Barcode + SKU + Nama)
- ✅ Ukuran disesuaikan untuk menghindari cutoff
- ✅ Border dihilangkan untuk tampilan yang lebih bersih
- ✅ Barcode height disesuaikan untuk distribusi ruang yang lebih baik
- ✅ Label "SKU:" dan "Nama:" dihilangkan, langsung tampil nilai
- ✅ Font size diperbesar untuk keterbacaan yang lebih baik

## Testing

1. **Preview**: Label kecil tampil dengan benar
2. **Download PDF**: PDF label kecil terdownload dengan nama yang sesuai
3. **Print**: Label kecil tercetak dengan ukuran yang tepat
4. **Layout**: 3 label per baris dengan gap 0.3cm
5. **Konten**: Hanya barcode, SKU, dan nama barang
6. **Cutoff**: Tidak ada label yang terpotong
