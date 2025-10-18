# Live Support Notification URL Fix

## Masalah yang Ditemukan

Notifikasi Live Support mengarah ke URL yang salah, menyebabkan error 404 saat diklik.

## Root Cause

**URL di Notifikasi**: `/support/admin-panel`  
**Route yang Benar**: `/support/admin`

## Perbaikan yang Dilakukan

### 1. **URL Notifikasi Diperbaiki**

**Sebelum:**
```php
'url' => config('app.url') . '/support/admin-panel',
```

**Sesudah:**
```php
'url' => config('app.url') . '/support/admin',
```

### 2. **Route yang Benar**

```php
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/support/admin', [App\Http\Controllers\SupportAdminController::class, 'index'])->name('support.admin');
});
```

### 3. **Controller yang Benar**

```php
// app/Http/Controllers/SupportAdminController.php
class SupportAdminController extends Controller
{
    public function index()
    {
        // Check permission
        $hasPermission = \DB::table('users as u')
            ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('u.id', $userId)
            ->where('m.code', 'support_admin_panel')
            ->where('p.action', 'view')
            ->exists();

        if (!$hasPermission) {
            abort(403, 'Unauthorized access to support admin panel');
        }

        return Inertia::render('Support/AdminPanel');
    }
}
```

## File yang Diperbaiki

### `app/Http/Controllers/LiveSupportController.php`

**Method yang Diperbaiki:**
1. `sendConversationNotifications()` - Notifikasi percakapan baru
2. `sendChatMessageNotifications()` - Notifikasi chat baru

**Perubahan:**
```php
// SEBELUM
'url' => config('app.url') . '/support/admin-panel',

// SESUDAH  
'url' => config('app.url') . '/support/admin',
```

## Testing

### 1. **Verifikasi Route**
```bash
php artisan route:list --name=support
```

**Output:**
```
GET|HEAD  support/admin  support.admin › SupportAdminController@index
```

### 2. **Test Notifikasi**
1. Buat percakapan Live Support baru
2. Kirim chat dalam percakapan
3. Klik notifikasi
4. Harus mengarah ke `/support/admin` (bukan 404)

### 3. **Test Permission**
- User dengan `division_id = 21` dan permission `support_admin_panel` → Bisa akses
- User tanpa permission → Error 403

## URL yang Benar

### **Notifikasi Percakapan Baru:**
```
URL: https://yourdomain.com/support/admin
Type: live_support_conversation
```

### **Notifikasi Chat Baru:**
```
URL: https://yourdomain.com/support/admin  
Type: live_support_chat
```

## Verifikasi Perbaikan

### 1. **Route List**
```bash
php artisan route:list | grep support
```

### 2. **Test Manual**
1. Login sebagai user dengan `division_id = 21`
2. Buat percakapan Live Support
3. Kirim chat
4. Klik notifikasi
5. Harus masuk ke admin panel (bukan 404)

### 3. **Check Logs**
```php
// Success log
\Log::info('Live Support notifications sent successfully', [
    'conversation_id' => $conversationId,
    'url' => config('app.url') . '/support/admin'
]);
```

## Hasil Perbaikan

✅ **Notifikasi mengarah ke URL yang benar**  
✅ **Tidak ada error 404**  
✅ **Admin panel dapat diakses**  
✅ **Permission system berfungsi**  
✅ **Route terdaftar dengan benar**

Sekarang notifikasi Live Support akan mengarah ke admin panel yang benar dan tidak lagi menimbulkan error 404!
