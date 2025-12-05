# Food Good Receive - Print Struk Notes Feature

Dokumentasi fitur untuk menampilkan notes pada print struk Food Good Receive.

## Overview

Fitur ini menambahkan notes ke dalam print struk Food Good Receive, memungkinkan user untuk melihat keterangan yang telah diinput saat melakukan good receive.

## Fitur Utama

### 1. **Notes Display in Print**
- Notes ditampilkan di struk PDF
- Format yang konsisten dengan design struk
- Auto-wrap untuk teks panjang
- Conditional display (hanya muncul jika ada notes)

### 2. **Enhanced Struk Layout**
- Notes ditempatkan setelah daftar items
- Sebelum garis footer
- Font size yang sesuai untuk readability
- Proper spacing dan alignment

### 3. **Dynamic Height Calculation**
- PDF height otomatis menyesuaikan dengan panjang notes
- Perhitungan yang akurat untuk multi-line notes
- Optimal paper usage

## Perubahan yang Dibuat

### 1. Backend Changes

#### **StrukData API Update**
```php
$gr = DB::table('food_good_receives as gr')
    ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
    ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
    ->select(
        'gr.gr_number as grNumber',
        'gr.receive_date as date',
        'gr.notes', // Added notes field
        's.name as supplier',
        'u.nama_lengkap as receivedByName',
        'po.number as poNumber'
    )
    ->where('gr.id', $id)
    ->first();
```

#### **Response Update**
```php
return response()->json([
    'grNumber' => $gr->grNumber,
    'date' => $gr->date,
    'supplier' => $gr->supplier,
    'receivedByName' => $gr->receivedByName,
    'poNumber' => $gr->poNumber,
    'notes' => $gr->notes, // Added notes to response
    'items' => $items
]);
```

### 2. Frontend Changes

#### **Function Signature Update**
```javascript
export async function generateStrukPDF({ 
  grNumber, 
  date, 
  supplier, 
  items, 
  receivedByName, 
  poNumber, 
  notes, // Added notes parameter
  showReprintLabel = false 
}) {
```

#### **Height Calculation Update**
```javascript
// Hitung tinggi untuk notes jika ada
if (notes && notes.trim()) {
  totalHeight += 4; // Spacing sebelum notes
  const notesLines = pdf.splitTextToSize(notes, 76).length;
  totalHeight += (notesLines * 3.5) + 4; // 3.5mm per line + 4mm spacing
}
```

#### **Notes Rendering**
```javascript
// Notes jika ada
if (notes && notes.trim()) {
  y += 2; // Spacing sebelum notes
  pdf.setFontSize(8);
  pdf.setFont(undefined, 'bold');
  pdf.text('Catatan:', 2, y); y += 3.5;
  pdf.setFont(undefined, 'normal');
  const notesLines = pdf.splitTextToSize(notes, 76);
  notesLines.forEach(line => {
    pdf.text(line, 2, y);
    y += 3.5;
  });
}
```

## Struk Layout

### **Before (Without Notes)**
```
┌─────────────────────────────────────────────────────────┐
│                    GOOD RECEIVE                         │
│                    JUSTUS GROUP                         │
│                                                         │
│ No: GR-20240101-0001                                   │
│ Tanggal: 2024-01-01                                    │
│ Supplier: Supplier ABC                                 │
│ PO: PO-20240101-0001                                   │
│ Petugas: John Doe                                       │
│ ─────────────────────────────────────────────────────── │
│ 2.5 kg Beras Premium                                   │
│ 1.0 ltr Minyak Goreng                                  │
│ 0.5 kg Gula Pasir                                      │
│ ─────────────────────────────────────────────────────── │
│ Terima kasih                                           │
└─────────────────────────────────────────────────────────┘
```

### **After (With Notes)**
```
┌─────────────────────────────────────────────────────────┐
│                    GOOD RECEIVE                         │
│                    JUSTUS GROUP                         │
│                                                         │
│ No: GR-20240101-0001                                   │
│ Tanggal: 2024-01-01                                    │
│ Supplier: Supplier ABC                                 │
│ PO: PO-20240101-0001                                   │
│ Petugas: John Doe                                       │
│ ─────────────────────────────────────────────────────── │
│ 2.5 kg Beras Premium                                   │
│ 1.0 ltr Minyak Goreng                                  │
│ 0.5 kg Gula Pasir                                      │
│                                                         │
│ Catatan:                                               │
│ Barang diterima dalam kondisi baik.                    │
│ Kemasan tidak rusak.                                   │
│ ─────────────────────────────────────────────────────── │
│ Terima kasih                                           │
└─────────────────────────────────────────────────────────┘
```

## Technical Implementation

### **1. PDF Generation Flow**

#### **Data Flow**
```
1. User clicks Reprint → 2. API call to strukData → 3. Backend returns data with notes → 4. generateStrukPDF called with notes → 5. PDF generated with notes
```

#### **Height Calculation**
```javascript
// Dynamic height calculation
let totalHeight = 0;
totalHeight += 15; // Header
totalHeight += 20; // Info
totalHeight += 8;  // Line
totalHeight += 10; // Item header
totalHeight += 8;  // Line

// Items height
if (items && items.length) {
  items.forEach(i => {
    const itemLines = i.name.length > 20 ? Math.ceil(i.name.length / 20) : 1;
    totalHeight += (itemLines * 3.5) + 4;
  });
}

// Notes height (NEW)
if (notes && notes.trim()) {
  totalHeight += 4; // Spacing
  const notesLines = pdf.splitTextToSize(notes, 76).length;
  totalHeight += (notesLines * 3.5) + 4;
}

totalHeight += 12; // Footer
totalHeight += 15; // Margin
```

### **2. Text Wrapping**

#### **Auto-wrap Implementation**
```javascript
const notesLines = pdf.splitTextToSize(notes, 76);
// 76mm is the maximum width for text in the struk
```

#### **Multi-line Support**
```javascript
notesLines.forEach(line => {
  pdf.text(line, 2, y);
  y += 3.5; // Line height
});
```

### **3. Conditional Rendering**

#### **Notes Display Logic**
```javascript
if (notes && notes.trim()) {
  // Only display if notes exist and not empty
  // Render notes section
}
```

#### **Empty Notes Handling**
- Notes section tidak ditampilkan jika kosong
- Tidak ada placeholder atau "-" di struk
- Layout tetap konsisten

## Design Specifications

### **1. Typography**

#### **Notes Label**
- **Font Size**: 8pt
- **Font Weight**: Bold
- **Text**: "Catatan:"
- **Position**: Left aligned

#### **Notes Content**
- **Font Size**: 8pt
- **Font Weight**: Normal
- **Text**: Notes content
- **Position**: Left aligned
- **Width**: 76mm (max)

### **2. Spacing**

#### **Before Notes**
- **Spacing**: 2mm dari items terakhir
- **Purpose**: Visual separation

#### **After Notes**
- **Spacing**: 1.5mm ke garis footer
- **Purpose**: Visual separation

#### **Line Height**
- **Notes Label**: 3.5mm
- **Notes Content**: 3.5mm per line
- **Consistency**: Sama dengan items

### **3. Layout**

#### **Positioning**
- **X Position**: 2mm dari kiri
- **Y Position**: Dynamic berdasarkan items
- **Width**: 76mm (sama dengan items)

#### **Alignment**
- **Label**: Left aligned
- **Content**: Left aligned
- **Consistency**: Sama dengan items

## Performance Considerations

### **1. PDF Generation**
- **Dynamic Height**: PDF height menyesuaikan dengan content
- **Efficient Rendering**: Hanya render notes jika ada
- **Memory Usage**: Minimal overhead

### **2. Text Processing**
- **Auto-wrap**: Efficient text wrapping
- **Line Calculation**: Accurate line count
- **Font Optimization**: Consistent font usage

### **3. File Size**
- **Minimal Impact**: Notes tidak significantly increase file size
- **Efficient Encoding**: PDF compression
- **Optimized Layout**: No wasted space

## Browser Compatibility

- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support
- **jsPDF**: Cross-browser compatibility

## Security Considerations

### **1. Content Sanitization**
- **Backend**: Notes sudah di-validate saat input
- **Frontend**: Direct rendering dari backend data
- **XSS Protection**: PDF generation tidak vulnerable

### **2. Data Privacy**
- **Notes Visibility**: Notes visible di struk
- **Access Control**: Same access control dengan good receive
- **Audit Trail**: Notes tercatat di database

## Testing

### **Manual Testing**
1. **Notes Display**: Test struk dengan notes
2. **Empty Notes**: Test struk tanpa notes
3. **Long Notes**: Test dengan notes panjang
4. **Special Characters**: Test dengan karakter khusus
5. **Multi-line Notes**: Test dengan notes multi-line

### **Test Cases**

#### **Notes Display Test Cases**
- ✅ Struk dengan notes pendek - should display normally
- ✅ Struk dengan notes panjang - should wrap properly
- ✅ Struk dengan notes multi-line - should display all lines
- ✅ Struk tanpa notes - should not display notes section
- ✅ Struk dengan notes kosong - should not display notes section

#### **Layout Test Cases**
- ✅ PDF height adjustment - should adjust to content
- ✅ Text alignment - should be left aligned
- ✅ Spacing consistency - should match design
- ✅ Font consistency - should match other text

#### **Content Test Cases**
- ✅ Notes dengan karakter khusus - should display safely
- ✅ Notes dengan angka - should display normally
- ✅ Notes dengan simbol - should display normally
- ✅ Notes dengan emoji - should display normally

## Troubleshooting

### **Common Issues**
1. **Notes tidak muncul**: Check backend API response
2. **Layout rusak**: Check height calculation
3. **Text tidak wrap**: Check splitTextToSize implementation
4. **Font tidak konsisten**: Check font settings

### **Debug Tips**
```javascript
// Debug notes data
console.log('Notes data:', notes);

// Debug PDF generation
console.log('PDF height:', totalHeight);

// Debug text wrapping
console.log('Notes lines:', pdf.splitTextToSize(notes, 76));
```

## Future Enhancements

### **1. Rich Text Support**
- **Bold/Italic**: Support untuk formatting
- **Bullet Points**: Support untuk list
- **Colors**: Support untuk warna text

### **2. Notes Templates**
- **Predefined Notes**: Template notes yang dapat digunakan
- **Quick Insert**: Quick insert common notes
- **Custom Templates**: User-defined templates

### **3. Enhanced Layout**
- **Notes Box**: Box around notes
- **Background Color**: Different background for notes
- **Icon**: Icon untuk notes section

### **4. Export Options**
- **Multiple Formats**: PDF, PNG, JPG
- **Custom Size**: Custom paper size
- **Batch Print**: Print multiple struk

## Related Features

- **Food Good Receive Management**: Main functionality
- **Notes Input**: Form input untuk notes
- **Print Functionality**: Print struk feature
- **PDF Generation**: jsPDF integration
- **Struk Layout**: Struk design system

## Conclusion

Fitur ini meningkatkan functionality print struk dengan menampilkan notes yang telah diinput saat good receive. Implementasi yang robust dengan dynamic height calculation dan proper text wrapping memberikan pengalaman print yang optimal dan informatif.
