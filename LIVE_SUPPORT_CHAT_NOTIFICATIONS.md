# Live Support Chat Notifications

## Fitur Notifikasi Chat Baru

Sistem notifikasi Live Support telah diperluas untuk mengirim notifikasi tidak hanya saat percakapan baru dibuat, tetapi juga saat ada chat baru yang dikirim dalam percakapan yang sudah ada.

## Jenis Notifikasi

### 1. **Notifikasi Percakapan Baru** (`live_support_conversation`)
- **Trigger**: Saat user membuat percakapan Live Support baru
- **Penerima**: Semua user dengan `division_id = 21` dan `status = 'A'`
- **Konten**: Informasi lengkap tentang percakapan baru

### 2. **Notifikasi Chat Baru** (`live_support_chat`) - **BARU**
- **Trigger**: Saat ada pesan baru dikirim dalam percakapan yang sudah ada
- **Penerima**: Semua user dengan `division_id = 21` dan `status = 'A'`
- **Konten**: Preview pesan dan informasi pengirim

## Implementasi

### Method yang Ditambahkan:

#### `sendChatMessageNotifications($conversationId, $message, $userId)`
```php
private function sendChatMessageNotifications($conversationId, $message, $userId)
{
    // Get support users (division_id=21)
    $supportUsers = DB::table('users')
        ->where('division_id', 21)
        ->where('status', 'A')
        ->pluck('id');

    // Get user details
    $user = DB::table('users as u')
        ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
        ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
        ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
        ->where('u.id', $userId)
        ->select('u.nama_lengkap', 'u.email', 'o.nama_outlet', 'd.nama_divisi', 'j.nama_jabatan')
        ->first();

    // Create notification message
    $notificationMessage = "Live Support: Chat baru diterima\n\n";
    $notificationMessage .= "Percakapan: " . $conversation->subject . "\n";
    $notificationMessage .= "Dari: {$user->nama_lengkap}\n";
    $notificationMessage .= "Email: {$user->email}\n";
    // ... detail lainnya
    $notificationMessage .= "\nPesan: " . substr($message, 0, 100) . "...";

    // Send to all support users
    foreach ($supportUsers as $supportUserId) {
        DB::table('notifications')->insert([
            'user_id' => $supportUserId,
            'task_id' => $conversationId,
            'type' => 'live_support_chat',
            'message' => $notificationMessage,
            'url' => config('app.url') . '/support/admin-panel',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
```

### Method yang Dimodifikasi:

#### 1. `sendMessage()` - User mengirim chat
```php
// Setelah message disimpan
$this->sendChatMessageNotifications($conversationId, $messageData['message'], $userId);
```

#### 2. `sendReply()` - Admin mengirim balasan
```php
// Setelah reply disimpan
$this->sendChatMessageNotifications($conversationId, $messageData['message'], $userId);
```

## Format Notifikasi

### Notifikasi Percakapan Baru:
```
Live Support: Percakapan baru telah dibuat

Subjek: [Subject]
Dari: [User Name]
Email: [User Email]
Outlet: [Outlet Name]
Divisi: [Division Name]
Jabatan: [Job Title]

Silakan segera tanggapi percakapan ini melalui Live Support Admin Panel.
```

### Notifikasi Chat Baru:
```
Live Support: Chat baru diterima

Percakapan: [Conversation Subject]
Dari: [User Name]
Email: [User Email]
Outlet: [Outlet Name]
Divisi: [Division Name]
Jabatan: [Job Title]

Pesan: [Message Preview (max 100 chars)...]

Silakan segera tanggapi chat ini melalui Live Support Admin Panel.
```

## Database Schema

### Tabel `notifications`:
```sql
- user_id: ID user yang menerima notifikasi
- task_id: ID percakapan (conversation_id)
- type: 'live_support_conversation' atau 'live_support_chat'
- message: Konten notifikasi
- url: Link ke admin panel
- is_read: Status baca (0 = belum dibaca)
```

## Logging

### Success Log:
```php
\Log::info('Live Support chat notifications sent successfully', [
    'conversation_id' => $conversationId,
    'message_preview' => substr($message, 0, 50) . '...',
    'user_id' => $userId,
    'support_users_count' => $supportUsers->count()
]);
```

### Error Log:
```php
\Log::error('Error sending Live Support chat notifications', [
    'conversation_id' => $conversationId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);
```

## Keuntungan Fitur Ini:

1. **Real-time Notification**: Support team langsung tahu ada chat baru
2. **Context Information**: Informasi lengkap tentang pengirim dan percakapan
3. **Message Preview**: Preview pesan untuk konteks cepat
4. **Consistent Experience**: Notifikasi yang konsisten untuk semua jenis interaksi
5. **Better Response Time**: Support team dapat merespons lebih cepat

## Testing:

### Skenario Test:
1. **User membuat percakapan baru** → Notifikasi `live_support_conversation`
2. **User mengirim chat dalam percakapan** → Notifikasi `live_support_chat`
3. **Admin mengirim balasan** → Notifikasi `live_support_chat`
4. **User mengirim file/attachment** → Notifikasi `live_support_chat`

### Verifikasi:
- Notifikasi muncul di dashboard user dengan `division_id = 21`
- URL mengarah ke `/support/admin-panel`
- Message preview terbatas 100 karakter
- Logging berfungsi dengan baik
