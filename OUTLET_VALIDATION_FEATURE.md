# 🔐 Outlet Validation Feature

## 🎯 Tujuan
Menambahkan validasi `id_outlet` user yang login untuk membatasi akses data berdasarkan outlet yang bersangkutan.

## 🔧 Perubahan yang Dibuat

### 1. **Superuser (id_outlet = 1)**
- ✅ **Bisa pilih outlet** - Dropdown outlet tersedia
- ✅ **Akses semua outlet** - Bisa melihat data dari outlet manapun
- ✅ **Filter outlet** - Bisa memilih "Semua" atau outlet tertentu

### 2. **Non-Superuser (id_outlet ≠ 1)**
- ❌ **Tidak bisa pilih outlet** - Dropdown outlet tidak tersedia
- ✅ **Otomatis filter outlet** - Data otomatis difilter berdasarkan outlet mereka
- ✅ **Tampilkan outlet aktif** - Menampilkan nama outlet yang sedang aktif
- ✅ **Info outlet** - Box informasi outlet yang sedang aktif

## 🎨 UI Changes

### **Superuser Interface**
```vue
<!-- Dropdown outlet tersedia -->
<div v-if="isSuperuser" class="flex items-center gap-2">
  <label class="text-sm">Outlet</label>
  <select v-model="filterOutlet">
    <option value="">Semua</option>
    <option v-for="o in props.outlets" :key="o.id" :value="o.id">
      {{ o.name }}
    </option>
  </select>
</div>
```

### **Non-Superuser Interface**
```vue
<!-- Tampilkan outlet yang bersangkutan -->
<div v-else class="flex items-center gap-2">
  <label class="text-sm font-semibold text-gray-700">Outlet:</label>
  <span class="px-3 py-2 bg-blue-100 text-blue-800 rounded-lg font-medium">
    {{ currentOutletName }}
  </span>
</div>
```

### **Info Box untuk Non-Superuser**
```vue
<!-- Info outlet aktif -->
<div v-if="!isSuperuser && !props.hasFilters" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
  <div class="flex items-start">
    <i class="fas fa-building text-green-500 mt-1 mr-3"></i>
    <div>
      <h3 class="text-green-800 font-semibold mb-1">Outlet Aktif:</h3>
      <p class="text-green-700 text-sm">
        Anda sedang melihat data untuk outlet: <strong>{{ currentOutletName }}</strong>
      </p>
    </div>
  </div>
</div>
```

## 🔧 Backend Logic

### **Controller Filter Logic**
```php
// Filter outlet untuk GR query
if ($user->id_outlet != 1) {
    // Non-superuser: otomatis filter berdasarkan outlet mereka
    $grQuery->where('gr.outlet_id', $user->id_outlet);
} elseif ($request->filled('outlet_id')) {
    // Superuser: filter berdasarkan outlet yang dipilih
    $grQuery->where('gr.outlet_id', $request->outlet_id);
}

// Filter outlet untuk RWS query
if ($user->id_outlet != 1) {
    // Non-superuser: otomatis filter berdasarkan outlet mereka
    $rwsQuery->where('c.id_outlet', $user->id_outlet);
} elseif ($request->filled('outlet_id')) {
    // Superuser: filter berdasarkan outlet yang dipilih
    $rwsQuery->where('c.id_outlet', $request->outlet_id);
}
```

### **Frontend Logic**
```javascript
// Computed property untuk mendapatkan nama outlet
const currentOutletName = computed(() => {
  if (isSuperuser.value) return '';
  const currentOutlet = props.outlets.find(o => o.id == props.user_id_outlet);
  return currentOutlet ? currentOutlet.name : 'Outlet Tidak Ditemukan';
});

// Apply filter dengan outlet validation
function applyFilter() {
  router.get(route('report-invoice-outlet'), {
    outlet_id: isSuperuser.value ? filterOutlet.value : props.user_id_outlet,
    search: filterSearch.value || undefined,
    from: filterFrom.value || undefined,
    to: filterTo.value || undefined,
  });
}
```

## 🎯 User Experience

### **Superuser Experience**
1. **Login** → `id_outlet = 1`
2. **Akses Report** → Dropdown outlet tersedia
3. **Pilih Outlet** → Bisa pilih "Semua" atau outlet tertentu
4. **Load Data** → Data sesuai dengan outlet yang dipilih

### **Non-Superuser Experience**
1. **Login** → `id_outlet = 2, 3, 4, dst`
2. **Akses Report** → Outlet otomatis ditampilkan
3. **Info Outlet** → Box hijau menampilkan outlet aktif
4. **Load Data** → Data otomatis difilter berdasarkan outlet mereka

## 🔒 Security Features

- ✅ **Automatic Filtering** - Non-superuser tidak bisa mengakses data outlet lain
- ✅ **UI Restriction** - Dropdown outlet tidak tersedia untuk non-superuser
- ✅ **Backend Validation** - Controller otomatis filter berdasarkan `user_id_outlet`
- ✅ **Clear Indication** - User tahu outlet mana yang sedang aktif

## 📱 Responsive Design

- ✅ **Mobile Friendly** - Layout responsive untuk semua device
- ✅ **Clear Labels** - Label outlet jelas dan mudah dibaca
- ✅ **Color Coding** - Warna biru untuk superuser, hijau untuk non-superuser
- ✅ **Icon Support** - Icon building untuk outlet info
