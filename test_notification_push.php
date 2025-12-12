<?php
/**
 * Test Script untuk Push Notification
 * 
 * Cara menjalankan:
 * 1. Via Tinker: php artisan tinker
 *    Lalu copy-paste kode di bawah
 * 
 * 2. Via Browser/Postman: Buat route test (temporary)
 * 
 * 3. Via Command: php artisan test:notification
 */

// Opsi 1: Via Tinker (Recommended)
// Jalankan: php artisan tinker
// Lalu copy-paste ini:

use App\Models\Notification;

$notification = Notification::create([
    'user_id' => 26,
    'title' => 'Test Push Notification',
    'message' => 'Ini adalah test notification untuk push notification ke mobile app. Jika Anda melihat ini di mobile app, berarti push notification berhasil!',
    'type' => 'test',
    'approval_id' => null,
    'task_id' => null,
    'url' => null,
    'is_read' => false,
]);

echo "Notification created with ID: " . $notification->id . "\n";
echo "Check mobile app for push notification!\n";
echo "Check logs at storage/logs/laravel.log for details\n";

// Opsi 2: Via Route (Temporary - untuk test via browser/Postman)
// Tambahkan di routes/web.php atau routes/api.php (HAPUS SETELAH TEST!)
/*
Route::get('/test-push-notification', function() {
    $notification = \App\Models\Notification::create([
        'user_id' => 26,
        'title' => 'Test Push Notification',
        'message' => 'Ini adalah test notification untuk push notification ke mobile app',
        'type' => 'test',
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Notification created. Check mobile app!',
        'notification_id' => $notification->id,
    ]);
})->middleware('auth'); // atau tanpa middleware untuk test
*/

