# 📊 Grand Total Breakdown Feature

## 🎯 Tujuan
Menambahkan grand total breakdown yang menampilkan detail per tipe transaksi (GR dan RWS) dengan informasi lengkap termasuk total, rata-rata, dan persentase.

## ✨ Fitur Breakdown

### 📈 **Informasi yang Ditampilkan**

#### **Header Summary**
- ✅ **Grand Total Amount** - Total keseluruhan dalam format rupiah
- ✅ **Total Transaksi** - Jumlah semua transaksi

#### **GR Breakdown**
- ✅ **Total GR** - Total amount untuk semua transaksi GR
- ✅ **Jumlah GR** - Banyaknya transaksi GR
- ✅ **Rata-rata GR** - Rata-rata per transaksi GR
- ✅ **Persentase GR** - Persentase dari total keseluruhan

#### **RWS Breakdown**
- ✅ **Total RWS** - Total amount untuk semua transaksi RWS
- ✅ **Jumlah RWS** - Banyaknya transaksi RWS
- ✅ **Rata-rata RWS** - Rata-rata per transaksi RWS
- ✅ **Persentase RWS** - Persentase dari total keseluruhan

## 🎨 **UI Design**

### **Layout Structure**
```
┌─────────────────────────────────────────────────────────┐
│ Grand Total Summary                    Rp 45.750.000     │
│                                        Total 20 transaksi│
├─────────────────────────────────────────────────────────┤
│ ┌─────────────────┐  ┌─────────────────┐                │
│ │ GR (Good Receive)│  │ RWS (Sales)     │                │
│ │ 12 transaksi    │  │ 8 transaksi     │                │
│ │ Rp 30.000.000   │  │ Rp 15.750.000   │                │
│ │ Rata-rata:      │  │ Rata-rata:      │                │
│ │ Rp 2.500.000    │  │ Rp 1.968.750    │                │
│ └─────────────────┘  └─────────────────┘                │
├─────────────────────────────────────────────────────────┤
│ Persentase: GR: 66%  RWS: 34%                           │
└─────────────────────────────────────────────────────────┘
```

### **Color Coding**
- 🟢 **GR Section**: Border hijau, badge hijau, text hijau
- 🔵 **RWS Section**: Border biru, badge biru, text biru
- ⚪ **Background**: Abu-abu terang untuk kontras
- ⚫ **Total**: Hitam untuk grand total yang menonjol

## 🔧 **Computed Properties**

### **GR Calculations**
```javascript
// Total GR
const grTotal = computed(() => {
  return props.data
    .filter(row => row.transaction_type === 'GR')
    .reduce((total, row) => total + (parseFloat(row.payment_total) || 0), 0);
});

// Rata-rata GR
const grAverage = computed(() => {
  return grCount.value > 0 ? grTotal.value / grCount.value : 0;
});

// Persentase GR
const grPercentage = computed(() => {
  return grandTotal.value > 0 ? Math.round((grTotal.value / grandTotal.value) * 100) : 0;
});
```

### **RWS Calculations**
```javascript
// Total RWS
const rwsTotal = computed(() => {
  return props.data
    .filter(row => row.transaction_type === 'RWS')
    .reduce((total, row) => total + (parseFloat(row.payment_total) || 0), 0);
});

// Rata-rata RWS
const rwsAverage = computed(() => {
  return rwsCount.value > 0 ? rwsTotal.value / rwsCount.value : 0;
});

// Persentase RWS
const rwsPercentage = computed(() => {
  return grandTotal.value > 0 ? Math.round((rwsTotal.value / grandTotal.value) * 100) : 0;
});
```

## 📱 **Responsive Design**

### **Desktop View (md:grid-cols-2)**
```
┌─────────────────────────────────────────────────────────┐
│ Grand Total Summary                    Rp 45.750.000     │
│                                        Total 20 transaksi│
├─────────────────────────────────────────────────────────┤
│ ┌─────────────────┐  ┌─────────────────┐                │
│ │ GR Breakdown    │  │ RWS Breakdown   │                │
│ │ 12 transaksi    │  │ 8 transaksi     │                │
│ │ Rp 30.000.000   │  │ Rp 15.750.000   │                │
│ │ Rata-rata:      │  │ Rata-rata:      │                │
│ │ Rp 2.500.000    │  │ Rp 1.968.750    │                │
│ └─────────────────┘  └─────────────────┘                │
├─────────────────────────────────────────────────────────┤
│ Persentase: GR: 66%  RWS: 34%                           │
└─────────────────────────────────────────────────────────┘
```

### **Mobile View (grid-cols-1)**
```
┌─────────────────────────────────┐
│ Grand Total Summary             │
│ Rp 45.750.000                   │
│ Total 20 transaksi              │
├─────────────────────────────────┤
│ ┌─────────────────┐             │
│ │ GR Breakdown    │             │
│ │ 12 transaksi    │             │
│ │ Rp 30.000.000   │             │
│ │ Rata-rata:      │             │
│ │ Rp 2.500.000    │             │
│ └─────────────────┘             │
├─────────────────────────────────┤
│ ┌─────────────────┐             │
│ │ RWS Breakdown   │             │
│ │ 8 transaksi     │             │
│ │ Rp 15.750.000   │             │
│ │ Rata-rata:      │             │
│ │ Rp 1.968.750    │             │
│ └─────────────────┘             │
├─────────────────────────────────┤
│ Persentase: GR: 66%  RWS: 34%   │
└─────────────────────────────────┘
```

## 🎨 **Styling Features**

### **GR Section**
```css
.bg-white.rounded-lg.p-4.border-l-4.border-green-500
.text-2xl.font-bold.text-green-700
.bg-green-100.text-green-800
```

### **RWS Section**
```css
.bg-white.rounded-lg.p-4.border-l-4.border-blue-500
.text-2xl.font-bold.text-blue-700
.bg-blue-100.text-blue-800
```

### **Typography Hierarchy**
- **Grand Total**: `text-3xl font-bold` - Paling menonjol
- **Section Total**: `text-2xl font-bold` - Menonjol per section
- **Count**: `text-sm text-gray-600` - Informasi jumlah
- **Average**: `text-sm text-gray-500` - Informasi rata-rata
- **Percentage**: `text-sm font-semibold` - Persentase

## 🔄 **Real-time Updates**

### **Automatic Recalculation**
- ✅ **Filter Changes** - Breakdown update otomatis saat filter berubah
- ✅ **Data Changes** - Breakdown update otomatis saat data berubah
- ✅ **Reactive** - Menggunakan Vue computed properties untuk reaktivitas

### **Performance**
- ✅ **Efficient Calculation** - Menggunakan `filter()` dan `reduce()` untuk performa optimal
- ✅ **Conditional Rendering** - Hanya render saat ada data
- ✅ **Memory Efficient** - Computed properties di-cache oleh Vue

## 📊 **Contoh Output**

### **Dengan Data**
```
Grand Total Summary                    Rp 45.750.000
                                        Total 20 transaksi

┌─────────────────┐  ┌─────────────────┐
│ GR (Good Receive)│  │ RWS (Sales)     │
│ 12 transaksi    │  │ 8 transaksi     │
│ Rp 30.000.000   │  │ Rp 15.750.000   │
│ Rata-rata:      │  │ Rata-rata:      │
│ Rp 2.500.000    │  │ Rp 1.968.750    │
└─────────────────┘  └─────────────────┘

Persentase: GR: 66%  RWS: 34%
```

### **Tanpa Data**
```
(Tidak ada grand total section)
```

## 🎯 **User Experience**

### **Benefits**
- ✅ **Detailed Analysis** - User bisa analisis per tipe transaksi
- ✅ **Quick Comparison** - Mudah membandingkan GR vs RWS
- ✅ **Visual Appeal** - Design yang menarik dan informatif
- ✅ **Responsive** - Tampil baik di semua device
- ✅ **Percentage Insight** - User tahu proporsi per tipe transaksi

### **Use Cases**
- 📊 **Management Report** - Untuk laporan ke management
- 📈 **Financial Analysis** - Analisis keuangan per tipe transaksi
- 🔍 **Data Comparison** - Membandingkan performa GR vs RWS
- 📱 **Mobile Viewing** - Mudah dilihat di mobile
- 📋 **Summary Report** - Ringkasan yang komprehensif
