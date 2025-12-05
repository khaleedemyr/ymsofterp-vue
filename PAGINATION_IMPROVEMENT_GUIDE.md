# Pagination Improvement Guide

## Overview
Fitur pagination telah diperbaiki untuk mengatasi masalah data kosong saat pindah halaman dan ditambahkan fitur per page untuk fleksibilitas tampilan data.

## Masalah yang Diperbaiki

### 1. **Data Kosong Saat Pindah Halaman**
- **Penyebab**: Parameter filter tidak dipertahankan saat pindah halaman
- **Solusi**: Menambahkan `withQueryString()` dan memperbaiki URL handling

### 2. **Tidak Ada Opsi Per Page**
- **Penyebab**: Pagination menggunakan jumlah tetap (15)
- **Solusi**: Menambahkan dropdown per page dengan opsi 10, 15, 25, 50, 100

## Perubahan yang Dibuat

### 1. **Backend (MasterReportController.php)**

#### **Parameter Per Page**
```php
$perPage = $request->get('per_page', 15);
```

#### **Pagination dengan Query String**
```php
// Sebelum
$data = $query->orderBy('nama_departemen')->paginate(15);

// Sesudah
$data = $query->orderBy('nama_departemen')->paginate($perPage)->withQueryString();
```

#### **Filter Parameter**
```php
'filters' => [
    'search' => $search,
    'type' => $type,
    'status' => $status,
    'per_page' => $perPage, // Ditambahkan
],
```

### 2. **Frontend (Index.vue)**

#### **State Management**
```javascript
const perPage = ref(props.filters?.per_page || 15);
```

#### **Debounced Search dengan Per Page**
```javascript
const debouncedSearch = debounce(() => {
  router.get('/master-report', {
    search: search.value,
    type: type.value,
    status: status.value,
    per_page: perPage.value, // Ditambahkan
  }, { preserveState: true, replace: true });
}, 400);
```

#### **Pagination URL Handling**
```javascript
function goToPage(url) {
  if (url) {
    // Parse URL untuk menambahkan parameter filter yang hilang
    const urlObj = new URL(url);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('type', type.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}
```

#### **Watcher untuk Per Page**
```javascript
watch([type, status, perPage], () => {
  router.get('/master-report', {
    search: search.value,
    type: type.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
```

### 3. **UI Components**

#### **Dropdown Per Page**
```vue
<select v-model="perPage" class="form-input rounded-xl">
  <option value="10">10 per halaman</option>
  <option value="15">15 per halaman</option>
  <option value="25">25 per halaman</option>
  <option value="50">50 per halaman</option>
  <option value="100">100 per halaman</option>
</select>
```

#### **Pagination Info**
```vue
<div class="text-sm text-gray-600">
  Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} 
  {{ type === 'departemen' ? 'departemen' : 'area' }}
</div>
```

#### **Improved Pagination Navigation**
```vue
<button 
  v-if="link.url" 
  @click="goToPage(link.url)" 
  :class="[
    'px-3 py-2 text-sm border border-gray-300 transition-colors',
    link.active 
      ? 'bg-blue-600 text-white border-blue-600' 
      : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
  ]" 
  v-html="link.label"
></button>
```

## Fitur Baru

### 1. **Per Page Options**
- ✅ **10 per halaman**: Untuk data sedikit
- ✅ **15 per halaman**: Default (sebelumnya)
- ✅ **25 per halaman**: Untuk data sedang
- ✅ **50 per halaman**: Untuk data banyak
- ✅ **100 per halaman**: Untuk data sangat banyak

### 2. **Pagination Info**
- ✅ **Menampilkan X sampai Y dari Z**: Info lengkap data
- ✅ **Dynamic text**: Berubah sesuai type (departemen/area)
- ✅ **Responsive**: Layout yang baik di mobile dan desktop

### 3. **Improved Navigation**
- ✅ **Better styling**: Hover effects dan active states
- ✅ **Consistent spacing**: Padding dan margin yang konsisten
- ✅ **Accessibility**: Better contrast dan focus states

## Cara Kerja

### 1. **Filter Preservation**
```
URL: /master-report?search=test&type=area&status=A&per_page=25&page=2
```
- Semua parameter filter dipertahankan saat pindah halaman
- `withQueryString()` memastikan parameter tidak hilang

### 2. **Per Page Change**
```
User pilih "50 per halaman" → 
Router reload dengan per_page=50 → 
Backend paginate(50) → 
Frontend update tampilan
```

### 3. **Search dengan Pagination**
```
User ketik "kitchen" → 
Debounced search → 
Router reload dengan search=kitchen&per_page=25 → 
Backend filter + paginate → 
Frontend update hasil
```

## Keuntungan

### 1. **User Experience**
- ✅ **Tidak ada data kosong**: Filter selalu dipertahankan
- ✅ **Fleksibel tampilan**: User bisa pilih jumlah data per halaman
- ✅ **Info lengkap**: User tahu posisi data dan total data
- ✅ **Navigation smooth**: Pindah halaman tanpa kehilangan filter

### 2. **Performance**
- ✅ **Efficient pagination**: Hanya load data yang diperlukan
- ✅ **Query optimization**: Backend hanya query data yang ditampilkan
- ✅ **Memory efficient**: Tidak load semua data sekaligus

### 3. **Maintainability**
- ✅ **Clean code**: Separation of concerns yang jelas
- ✅ **Reusable**: Pattern bisa digunakan di halaman lain
- ✅ **Testable**: Logic terpisah dan mudah di-test

## Testing Scenarios

### 1. **Pagination Navigation**
- [ ] Klik "Next" → Data tetap ada, filter dipertahankan
- [ ] Klik "Previous" → Data tetap ada, filter dipertahankan
- [ ] Klik nomor halaman → Data tetap ada, filter dipertahankan

### 2. **Per Page Change**
- [ ] Pilih "10 per halaman" → Data berubah, filter dipertahankan
- [ ] Pilih "50 per halaman" → Data berubah, filter dipertahankan
- [ ] Pilih "100 per halaman" → Data berubah, filter dipertahankan

### 3. **Search dengan Pagination**
- [ ] Ketik search → Data terfilter, pagination update
- [ ] Pindah halaman setelah search → Data tetap terfilter
- [ ] Clear search → Data kembali normal, pagination update

### 4. **Filter dengan Pagination**
- [ ] Pilih status "Aktif" → Data terfilter, pagination update
- [ ] Pindah halaman → Data tetap terfilter
- [ ] Pilih status "Semua" → Data kembali normal, pagination update

## Notes

- **Default per page**: 15 (sama seperti sebelumnya)
- **URL structure**: Semua parameter filter dipertahankan di URL
- **Backward compatibility**: Tidak ada breaking changes
- **Performance**: Pagination tetap efficient dengan query optimization
- **Responsive**: Layout bekerja baik di mobile dan desktop
