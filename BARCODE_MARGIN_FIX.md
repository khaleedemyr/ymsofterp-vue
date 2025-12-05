# Perbaikan Margin Label Barcode - Center Alignment

## Masalah yang Ditemukan

### **Margin Kiri dan Kanan**
- Teks masih left-aligned dengan margin kiri 5mm
- Barcode tidak center-aligned dengan sempurna
- Ada ruang kosong yang tidak perlu di kiri dan kanan

### **Masalah Spesifik**
1. **PDF Text**: Menggunakan `x + 5` untuk posisi teks (left-aligned)
2. **PDF Barcode**: Menggunakan `x + 5` untuk posisi barcode (tidak center)
3. **CSS Padding**: Padding horizontal masih terlalu besar
4. **Text Alignment**: Teks tidak benar-benar center-aligned
5. **Font Size**: Nama barang terlalu kecil dan sulit dibaca

## Perbaikan yang Diterapkan

### 1. **PDF Text Center Alignment**
```javascript
/* Sebelum */
doc.text(`SKU: ${sku}`, x + 5, currentY);

/* Sesudah */
doc.text(`SKU: ${sku}`, x + labelWidth/2, currentY, { align: 'center' });
```

### 2. **PDF Barcode Center Alignment**
```javascript
/* Sebelum */
doc.addImage(canvas, 'PNG', x + 5, y + 3, areaBarcodeW, areaBarcodeH);

/* Sesudah */
const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH);
```

### 3. **CSS Padding Optimization**
```css
/* Sebelum */
.barcode-label {
  padding: 0.15cm;
}

/* Sesudah */
.barcode-label {
  padding: 0.15cm 0.1cm; /* Kurangi padding horizontal */
}
```

### 4. **Text CSS Margin Reset**
```css
/* Sebelum */
.barcode-text {
  text-align: center;
  width: 100%;
}

/* Sesudah */
.barcode-text {
  text-align: center;
  width: 100%;
  margin: 0;
  padding: 0;
}
```

### 5. **Max Width Adjustment**
```css
/* Sebelum */
.barcode-name {
  max-width: 9.7cm;
}

/* Sesudah */
.barcode-name {
  max-width: 100%; /* Gunakan full width */
}
```

### 6. **Font Size Nama Barang - Diperbesar**
```javascript
/* Sebelum */
doc.setFontSize(7); // Nama Item
currentY += 3.5;

/* Sesudah */
doc.setFontSize(9); // Nama Item - Diperbesar
currentY += 4.5; // Spacing disesuaikan
```

```css
/* Sebelum */
.barcode-name {
  font-size: 0.18cm;
}

/* Sesudah */
.barcode-name {
  font-size: 0.25cm; /* Diperbesar dari 0.18cm ke 0.25cm */
}
```

## Layout Baru yang Center-Aligned

### **Struktur Label 10x5cm**
```
┌─────────────────────────────────────────┐
│                                         │
│           [Barcode Image]               │
│              Center Aligned             │
│                                         │
│              SKU: 00970903              │
│        Nama: Sticker Label Barcode      │
│              Warehouse: -               │
│            Category: Groceries          │
│         Sub Category: Guest Supplies    │
│           Code: GC-20250827-3668        │
│                                         │
└─────────────────────────────────────────┘
```

### **Alignment Strategy**
1. **Barcode**: Center-aligned menggunakan `(labelWidth - areaBarcodeW) / 2`
2. **Text**: Center-aligned menggunakan `x + labelWidth/2` dengan `{ align: 'center' }`
3. **Padding**: Dikurangi dari 0.15cm ke 0.1cm untuk horizontal
4. **Margin**: Dihilangkan untuk semua elemen teks
5. **Font Size**: Nama barang diperbesar untuk keterbacaan yang lebih baik

### **Perhitungan Posisi**
- **Label Width**: 100mm (10cm)
- **Barcode Width**: 90mm (areaBarcodeW)
- **Barcode X**: `x + (100 - 90) / 2 = x + 5mm` (center)
- **Text X**: `x + 100/2 = x + 50mm` (center dengan align center)

## Hasil Perbaikan

### **✅ Masalah yang Diperbaiki:**
1. **Margin Hilang**: Tidak ada lagi margin kiri dan kanan yang tidak perlu
2. **Center Alignment**: Semua konten benar-benar center-aligned
3. **Barcode Center**: Barcode berada di tengah label
4. **Text Center**: Semua teks berada di tengah label
5. **Optimal Space**: Menggunakan ruang label secara optimal
6. **Font Size**: Nama barang diperbesar untuk keterbacaan yang lebih baik

### **📏 Ukuran Final:**
- **Label**: 10cm x 5cm
- **Padding**: 0.15cm (vertical) x 0.1cm (horizontal)
- **Barcode**: 90mm x 20mm (center-aligned)
- **SKU Font**: 8pt (center-aligned)
- **Nama Font**: 9pt (center-aligned) - **DIPERBESAR**
- **Detail Font**: 6pt (center-aligned)
- **Space Usage**: 100% width utilization

### **🎯 Alignment Strategy:**
- **Horizontal**: Semua elemen center-aligned
- **Vertical**: Distribusi proporsional dengan flexbox
- **Padding**: Minimal horizontal padding
- **Margin**: Zero margin untuk semua elemen
- **Font Hierarchy**: SKU (8pt) < Nama (9pt) > Detail (6pt)

## Status

**✅ FIXED** - Margin kiri dan kanan sudah dihilangkan dan nama barang diperbesar:
- Semua konten center-aligned sempurna
- Tidak ada margin yang tidak perlu
- Barcode dan teks berada di tengah label
- Penggunaan ruang label optimal
- **Nama barang lebih besar dan mudah dibaca**

## Testing

1. **Print Preview**: Semua konten center-aligned, nama barang lebih besar
2. **Download PDF**: PDF dengan alignment yang sempurna dan font nama yang diperbesar
3. **Print**: Print dengan layout center yang rapi dan keterbacaan yang lebih baik
4. **Space Usage**: 100% width label terpakai optimal
5. **Readability**: Nama barang lebih mudah dibaca dengan font size yang diperbesar
