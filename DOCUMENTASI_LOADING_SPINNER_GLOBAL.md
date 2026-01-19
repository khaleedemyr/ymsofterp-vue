# Dokumentasi Loading Spinner Global

## Overview
Komponen loading spinner global yang bisa digunakan di semua halaman dengan menggunakan logo-icon.png.

## File yang Dibuat

### 1. Komponen LoadingSpinner.vue
**Path:** `resources/js/Components/LoadingSpinner.vue`

**Fitur:**
- Overlay fullscreen dengan backdrop
- Spinner menggunakan logo-icon.png
- Animasi spin smooth
- Custom message dan sub message
- Auto hide/show berdasarkan loading state

### 2. Composable useLoading.js
**Path:** `resources/js/Composables/useLoading.js`

**Fungsi:**
- `useLoading()` - Hook untuk menggunakan loading state
- `provideLoading()` - Provide loading state untuk child components

**Methods:**
- `showLoading(message, subMessage)` - Tampilkan loading spinner
- `hideLoading()` - Sembunyikan loading spinner
- `setLoading(value, message, subMessage)` - Set loading state manual

## Cara Penggunaan

### 1. Di AppLayout (Sudah di-setup)
Loading spinner sudah di-include di AppLayout, jadi otomatis tersedia di semua halaman.

### 2. Di Halaman/Component

```vue
<script setup>
import { useLoading } from '@/Composables/useLoading';

const { showLoading, hideLoading } = useLoading();

// Contoh: Saat submit form
function submitForm() {
  showLoading('Menyimpan data...', 'Mohon tunggu sebentar');
  
  router.post('/endpoint', formData, {
    onSuccess: () => {
      hideLoading();
      // Handle success
    },
    onError: () => {
      hideLoading();
      // Handle error
    },
    onFinish: () => {
      hideLoading(); // Safety net
    }
  });
}

// Contoh: Saat load data
function loadData() {
  showLoading('Memuat data...');
  
  router.get('/endpoint', {}, {
    onFinish: () => {
      hideLoading();
    }
  });
}
</script>
```

### 3. Custom Message

```javascript
// Dengan custom message
showLoading('Menyimpan Non Food Payment...', 'Mohon tunggu sebentar');

// Dengan message saja
showLoading('Memuat Data...');

// Default message
showLoading(); // Akan menggunakan "Memuat Data..."
```

## Contoh Implementasi

### Non Food Payment - Index
```javascript
function loadData() {
  showLoading('Memuat Data Non Food Payment...', 'Mohon tunggu sebentar');
  router.get('/non-food-payments', { ...filters.value, load_data: true }, {
    onFinish: () => {
      hideLoading();
    }
  });
}
```

### Non Food Payment - Create
```javascript
function confirmSubmit() {
  showLoading('Menyimpan Non Food Payment...', 'Mohon tunggu sebentar');
  router.post('/non-food-payments', formData, {
    onFinish: () => {
      hideLoading();
    }
  });
}
```

### Non Food Payment - Edit
```javascript
function submitForm() {
  showLoading('Menyimpan perubahan Non Food Payment...', 'Mohon tunggu sebentar');
  router.put(`/non-food-payments/${id}`, form, {
    onFinish: () => {
      hideLoading();
    }
  });
}
```

## Fitur

✅ **Global State** - Bisa digunakan di semua halaman
✅ **Custom Message** - Bisa set custom message
✅ **Auto Hide** - Auto hide setelah request selesai
✅ **Error Handling** - Auto hide jika ada error
✅ **Smooth Animation** - Fade in/out transition
✅ **Logo Icon** - Menggunakan logo-icon.png
✅ **Z-index High** - z-[9999] untuk overlay di atas semua

## Styling

- **Backdrop:** Black dengan opacity 50%
- **Spinner Box:** White rounded dengan shadow
- **Spinner Size:** 64x64px (w-16 h-16)
- **Animation:** 2s linear infinite spin
- **Z-index:** 9999 (di atas semua)

## Best Practices

1. **Always hide loading di onFinish** - Pastikan hideLoading() dipanggil di onFinish untuk safety
2. **Custom message yang jelas** - Berikan message yang informatif
3. **Handle error** - Pastikan hideLoading() juga dipanggil di onError
4. **Jangan double show** - Pastikan hideLoading() dipanggil sebelum showLoading() lagi

## Troubleshooting

### Loading tidak muncul?
- Pastikan `provideLoading()` dipanggil di AppLayout
- Pastikan `LoadingSpinner` component di-include di AppLayout
- Cek console untuk error

### Loading tidak hilang?
- Pastikan `hideLoading()` dipanggil di onFinish atau onError
- Cek apakah ada error yang tidak di-handle

### Loading muncul di semua halaman?
- Pastikan `hideLoading()` dipanggil setelah request selesai
- Cek apakah ada request yang tidak complete

---

**Status:** ✅ **READY TO USE**
