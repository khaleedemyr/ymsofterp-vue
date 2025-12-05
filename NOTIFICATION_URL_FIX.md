# Perbaikan URL Notifikasi - Maintenance Order

## Masalah yang Ditemukan
Sebelumnya, notifikasi yang disimpan ke tabel `notifications` hanya menyimpan URL relatif seperti `/maintenance-order/121`, yang menyebabkan:
1. **URL tidak lengkap** - tidak ada domain lengkap
2. **Tidak bisa direct ke halaman** - user harus copy-paste URL manual
3. **User experience buruk** - notifikasi tidak fungsional

## Solusi yang Diimplementasikan

### 1. Perbaikan Backend - URL Lengkap dengan APP_URL

#### File yang Diperbaiki:
- `app/Http/Controllers/MaintenanceCommentController.php`
- `app/Http/Controllers/MaintenanceOrderController.php`
- `app/Http/Controllers/MaintenanceTaskController.php`
- `app/Http/Controllers/MaintenancePurchaseRequisitionController.php`
- `app/Http/Controllers/Api/MaintenancePurchaseOrderController.php`
- `app/Http/Controllers/MaintenancePurchaseOrderReceiveController.php`

#### Perubahan:
```php
// SEBELUM (URL relatif):
'url' => '/maintenance-order/' . $taskId,

// SESUDAH (URL lengkap):
'url' => config('app.url') . '/maintenance-order/' . $taskId,
```

#### Hasil:
- URL notifikasi sekarang lengkap: `https://ymsofterp.com/maintenance-order/121`
- Menggunakan `config('app.url')` yang mengambil dari `APP_URL` di file `.env`

### 2. Perbaikan Frontend - Redirect Otomatis

#### File yang Diperbaiki:
- `resources/js/Layouts/AppLayout.vue`

#### Perubahan:
```javascript
function handleNotifClick(notif) {
    markAsRead(notif.id);
    
    // Redirect ke URL notifikasi jika ada
    if (notif.url) {
        // Jika URL adalah external (full URL), gunakan window.location.href
        if (notif.url.startsWith('http://') || notif.url.startsWith('https://')) {
            window.location.href = notif.url;
        } else {
            // Jika URL relatif, gunakan Inertia router
            window.location.href = notif.url;
        }
    }
    
    // ... rest of the function
}
```

#### Fitur Tambahan:
- **URL Display**: URL notifikasi ditampilkan di bawah pesan untuk debugging
- **Auto Redirect**: Klik notifikasi langsung redirect ke halaman yang sesuai
- **Smart URL Handling**: Support untuk URL relatif dan absolut

## Cara Kerja

### 1. Saat Comment Ditambah:
1. User menambah comment di maintenance order
2. System generate notifikasi dengan URL lengkap
3. URL disimpan: `https://ymsofterp.com/maintenance-order/121`

### 2. Saat Notifikasi Diklik:
1. User klik notifikasi di dropdown
2. System mark notifikasi sebagai dibaca
3. System redirect otomatis ke URL notifikasi
4. User langsung dibawa ke halaman maintenance order yang sesuai

## Testing

### 1. Test URL Notifikasi:
1. Buka maintenance order list
2. Tambah comment pada task tertentu
3. Cek notifikasi di dropdown (kanan atas)
4. Pastikan URL lengkap ditampilkan
5. Klik notifikasi - harus redirect ke halaman task

### 2. Expected Result:
- ✅ URL notifikasi lengkap dengan domain
- ✅ Klik notifikasi langsung redirect
- ✅ Halaman task terbuka dengan benar
- ✅ Notifikasi marked as read

## Konfigurasi Environment

### File `.env`:
```env
APP_URL=https://ymsofterp.com
# atau untuk development:
APP_URL=http://localhost:8000
```

### File `config/app.php`:
```php
'url' => env('APP_URL', 'http://localhost'),
```

## Manfaat

1. **User Experience Lebih Baik**: Notifikasi langsung bisa diklik
2. **Workflow Lebih Efisien**: User tidak perlu copy-paste URL
3. **Maintenance Lebih Mudah**: URL otomatis menggunakan domain yang benar
4. **Consistency**: Semua notifikasi menggunakan format yang sama

## Troubleshooting

### Jika URL Masih Relatif:
1. Cek `APP_URL` di file `.env`
2. Clear config cache: `php artisan config:clear`
3. Restart server

### Jika Redirect Tidak Bekerja:
1. Cek console browser untuk error
2. Pastikan URL valid dan bisa diakses
3. Cek apakah ada CORS issue

## Future Enhancement

1. **Deep Linking**: Support untuk deep link ke section tertentu
2. **URL Validation**: Validasi URL sebelum redirect
3. **Fallback URL**: URL alternatif jika primary URL gagal
4. **Analytics**: Track notifikasi click rate
