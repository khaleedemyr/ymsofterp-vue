# 📊 Grand Total Feature

## 🎯 Tujuan
Menambahkan grand total untuk report invoice outlet yang menampilkan total keseluruhan, jumlah transaksi, dan breakdown per tipe transaksi.

## ✨ Fitur Grand Total

### 📈 **Informasi yang Ditampilkan**
- ✅ **Grand Total Amount** - Total keseluruhan dalam format rupiah
- ✅ **Jumlah Transaksi** - Total semua transaksi (GR + RWS)
- ✅ **Breakdown GR** - Jumlah transaksi GR dengan badge hijau
- ✅ **Breakdown RWS** - Jumlah transaksi RWS dengan badge biru
- ✅ **Responsive Design** - Layout yang responsif untuk semua device

### 🎨 **UI Design**
```vue
<!-- Grand Total Section -->
<div v-if="props.hasFilters && props.data.length > 0" class="mt-6 bg-gray-50 rounded-lg p-4">
  <div class="flex justify-between items-center">
    <div class="flex items-center gap-4">
      <h3 class="text-lg font-semibold text-gray-800">Grand Total</h3>
      <div class="flex items-center gap-2 text-sm text-gray-600">
        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold">
          GR: {{ grCount }} transaksi
        </span>
        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
          RWS: {{ rwsCount }} transaksi
        </span>
      </div>
    </div>
    <div class="text-right">
      <div class="text-2xl font-bold text-gray-900">{{ formatRupiah(grandTotal) }}</div>
      <div class="text-sm text-gray-500">Total {{ props.data.length }} transaksi</div>
    </div>
  </div>
</div>
```

## 🔧 **Computed Properties**

### **Grand Total Calculation**
```javascript
const grandTotal = computed(() => {
  return props.data.reduce((total, row) => {
    return total + (parseFloat(row.payment_total) || 0);
  }, 0);
});
```

### **GR Count**
```javascript
const grCount = computed(() => {
  return props.data.filter(row => row.transaction_type === 'GR').length;
});
```

### **RWS Count**
```javascript
const rwsCount = computed(() => {
  return props.data.filter(row => row.transaction_type === 'RWS').length;
});
```

## 🎯 **Kondisi Tampil**

### **Grand Total akan tampil jika:**
- ✅ `props.hasFilters = true` (ada filter yang diterapkan)
- ✅ `props.data.length > 0` (ada data yang ditampilkan)

### **Grand Total tidak akan tampil jika:**
- ❌ Belum ada filter yang diterapkan
- ❌ Tidak ada data yang sesuai dengan filter
- ❌ Data masih kosong

## 📱 **Responsive Layout**

### **Desktop View**
```
┌─────────────────────────────────────────────────────────┐
│ Grand Total                    GR: 5 transaksi          │
│                              RWS: 3 transaksi           │
│                                                          │
│                                          Rp 15.000.000   │
│                                        Total 8 transaksi│
└─────────────────────────────────────────────────────────┘
```

### **Mobile View**
```
┌─────────────────────────────────┐
│ Grand Total                     │
│ GR: 5 transaksi                 │
│ RWS: 3 transaksi                │
│                                 │
│ Rp 15.000.000                   │
│ Total 8 transaksi               │
└─────────────────────────────────┘
```

## 🎨 **Styling Features**

### **Color Coding**
- 🟢 **GR Badge**: `bg-green-100 text-green-800` - Hijau untuk GR
- 🔵 **RWS Badge**: `bg-blue-100 text-blue-800` - Biru untuk RWS
- ⚪ **Background**: `bg-gray-50` - Abu-abu terang untuk kontras
- ⚫ **Text**: `text-gray-900` - Hitam untuk total amount

### **Typography**
- **Title**: `text-lg font-semibold` - Judul grand total
- **Amount**: `text-2xl font-bold` - Total amount yang menonjol
- **Count**: `text-sm text-gray-500` - Informasi jumlah transaksi
- **Badge**: `text-xs font-semibold` - Badge untuk tipe transaksi

## 🔄 **Real-time Updates**

### **Automatic Recalculation**
- ✅ **Filter Changes** - Grand total update otomatis saat filter berubah
- ✅ **Data Changes** - Grand total update otomatis saat data berubah
- ✅ **Reactive** - Menggunakan Vue computed properties untuk reaktivitas

### **Performance**
- ✅ **Efficient Calculation** - Menggunakan `reduce()` untuk performa optimal
- ✅ **Conditional Rendering** - Hanya render saat ada data
- ✅ **Memory Efficient** - Computed properties di-cache oleh Vue

## 📊 **Contoh Output**

### **Dengan Data**
```
Grand Total                    GR: 12 transaksi
                              RWS: 8 transaksi

                              Rp 45.750.000
                              Total 20 transaksi
```

### **Tanpa Data**
```
(Tidak ada grand total section)
```

## 🎯 **User Experience**

### **Benefits**
- ✅ **Quick Overview** - User bisa lihat total keseluruhan dengan cepat
- ✅ **Transaction Breakdown** - User tahu berapa GR vs RWS
- ✅ **Visual Appeal** - Design yang menarik dan informatif
- ✅ **Responsive** - Tampil baik di semua device

### **Use Cases**
- 📊 **Management Report** - Untuk laporan ke management
- 📈 **Financial Summary** - Ringkasan keuangan outlet
- 🔍 **Data Analysis** - Analisis data transaksi
- 📱 **Mobile Viewing** - Mudah dilihat di mobile
