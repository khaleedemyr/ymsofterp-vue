# Master Soal - Troubleshooting Guide

## Masalah: Tombol Tidak Bisa Diklik

### Kemungkinan Penyebab:
1. **Route Helper tidak tersedia** - `route()` function tidak terdefinisi
2. **Import yang salah** - `Link` component tidak diimport dengan benar
3. **JavaScript error** - Ada error di console browser
4. **Ziggy route helper tidak loaded** - Route helper tidak terkonfigurasi

### Solusi:

#### 1. Periksa Console Browser
Buka Developer Tools (F12) dan lihat apakah ada error di Console tab.

#### 2. Test Route Helper
Akses `/master-soal-test` untuk test route helper.

#### 3. Gunakan Hardcoded URL (Solusi Sementara)
File `IndexSimple.vue` sudah menggunakan hardcoded URL sebagai fallback:
- `/master-soal/create` untuk tambah soal
- `/master-soal/{id}` untuk detail
- `/master-soal/{id}/edit` untuk edit

#### 4. Periksa Konfigurasi Ziggy
Pastikan ZiggyVue sudah terkonfigurasi di `resources/js/app.js`:
```javascript
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
// ...
.use(ZiggyVue)
```

#### 5. Clear Cache
Jalankan perintah berikut:
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
npm run build
```

#### 6. Periksa Route List
Pastikan routes sudah terdaftar:
```bash
php artisan route:list --name=master-soal
```

### File yang Sudah Dibuat untuk Troubleshooting:

1. **`IndexSimple.vue`** - Versi sederhana dengan hardcoded URL
2. **`Test.vue`** - Test route helper
3. **`test_route_helper.html`** - Test manual route helper

### Langkah Debugging:

1. **Akses `/master-soal-test`** - Test route helper
2. **Periksa Console** - Lihat error JavaScript
3. **Gunakan IndexSimple** - Fallback dengan hardcoded URL
4. **Test Manual** - Buka `test_route_helper.html` di browser

### Solusi Permanen:

Jika route helper tidak berfungsi, gunakan hardcoded URL di semua file Vue:
- Ganti `route('master-soal.index')` dengan `'/master-soal'`
- Ganti `route('master-soal.create')` dengan `'/master-soal/create'`
- Ganti `route('master-soal.show', id)` dengan `'/master-soal/' + id`

### Status File:
- ✅ `IndexSimple.vue` - Sudah menggunakan hardcoded URL
- ✅ `Test.vue` - Test route helper
- ✅ Controller - Sudah diupdate ke IndexSimple
- ✅ Routes - Sudah terdaftar dengan benar

### Next Steps:
1. Test akses `/master-soal` - Harus bisa diklik sekarang
2. Jika masih error, periksa console browser
3. Gunakan IndexSimple sebagai solusi permanen jika route helper bermasalah
