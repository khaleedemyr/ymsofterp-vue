# 🔧 Report Invoice Outlet - Bug Fixes

## 🐛 Masalah yang Diperbaiki

### 1. Bug Expand
**Masalah**: Ketika expand 1 row, semua row ikut expand
**Penyebab**: Menggunakan `row.payment_id` sebagai key, padahal data baru menggunakan `row.gr_id`
**Solusi**: Mengganti semua referensi dari `payment_id` ke `gr_id`

### 2. Kolom yang Dihapus
**Dihapus**:
- ❌ Kolom "No Invoice" 
- ❌ Kolom "Status"

**Dipertahankan**:
- ✅ Kolom "Tgl Invoice"
- ✅ Kolom "Outlet" 
- ✅ Kolom "Warehouse"
- ✅ Kolom "Tipe"
- ✅ Kolom "No GR/RWS"
- ✅ Kolom "Tgl GR/RWS"
- ✅ Kolom "Total"

## 🔧 Perubahan yang Dibuat

### 1. Header Table
```vue
<!-- SEBELUM -->
<th>No Invoice</th>
<th>Tgl Invoice</th>
<th>Outlet</th>
<th>Warehouse</th>
<th>Tipe</th>
<th>No GR/RWS</th>
<th>Tgl GR/RWS</th>
<th>Total</th>
<th>Status</th>

<!-- SESUDAH -->
<th>Tgl Invoice</th>
<th>Outlet</th>
<th>Warehouse</th>
<th>Tipe</th>
<th>No GR/RWS</th>
<th>Tgl GR/RWS</th>
<th>Total</th>
```

### 2. Key untuk Expand
```vue
<!-- SEBELUM -->
<template v-for="row in props.data" :key="row.payment_id">
  <button @click="toggleExpand(row.payment_id)">
  <tr v-if="expanded[row.payment_id]">

<!-- SESUDAH -->
<template v-for="row in props.data" :key="row.gr_id">
  <button @click="toggleExpand(row.gr_id)">
  <tr v-if="expanded[row.gr_id]">
```

### 3. Data yang Ditampilkan
```vue
<!-- SEBELUM -->
<td>{{ row.payment_number }}</td>
<td>{{ formatDate(row.invoice_date) }}</td>
<td>{{ row.outlet_name }}</td>
<td>{{ formatWarehouse(row) }}</td>
<td>{{ row.transaction_type }}</td>
<td>{{ row.gr_number }}</td>
<td>{{ formatDate(row.gr_date) }}</td>
<td>{{ formatRupiah(row.payment_total) }}</td>
<td>{{ row.payment_status }}</td>

<!-- SESUDAH -->
<td>{{ formatDate(row.invoice_date) }}</td>
<td>{{ row.outlet_name }}</td>
<td>{{ formatWarehouse(row) }}</td>
<td>{{ row.transaction_type }}</td>
<td>{{ row.gr_number }}</td>
<td>{{ formatDate(row.gr_receive_date) }}</td>
<td>{{ formatRupiah(row.payment_total) }}</td>
```

### 4. Colspan untuk Detail
```vue
<!-- SEBELUM -->
<td colspan="7" class="bg-gray-50 px-0 py-0">

<!-- SESUDAH -->
<td colspan="6" class="bg-gray-50 px-0 py-0">
```

## ✅ Hasil Akhir

- ✅ **Expand bekerja dengan benar** - hanya 1 row yang expand per klik
- ✅ **Kolom status dihapus** - tidak ada lagi kolom status payment
- ✅ **Kolom no invoice dihapus** - tidak ada lagi kolom payment number
- ✅ **UI lebih bersih** - hanya menampilkan data yang relevan
- ✅ **Data langsung dari GR/RWS** - tanpa relasi ke outlet_payments

## 🎯 Fitur yang Tetap Berfungsi

- ✅ Filter outlet, pencarian, dan tanggal
- ✅ Expand detail items
- ✅ Format rupiah dan tanggal
- ✅ Badge tipe GR/RWS
- ✅ Responsive design
