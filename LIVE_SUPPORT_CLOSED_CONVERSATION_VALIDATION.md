# Live Support Closed Conversation Validation

## Fitur Validasi Conversation Closed

Sistem Live Support telah diperluas dengan validasi untuk mencegah user mengirim chat pada percakapan yang sudah di-close oleh admin.

## Perilaku Sistem

### **User (Customer)**
- ❌ **Tidak bisa mengirim chat** jika conversation status = "closed"
- ✅ **Harus membuat conversation baru** untuk bantuan lebih lanjut
- ✅ **Mendapat notifikasi jelas** tentang status conversation

### **Admin (Support Team)**
- ✅ **Bisa membalas** conversation yang sudah closed
- ✅ **Otomatis membuka kembali** conversation saat admin membalas
- ✅ **Logging** ketika conversation dibuka kembali

## Implementasi Backend

### 1. **Validasi di `sendMessage()` (User)**

```php
// Check if conversation is closed
if ($conversation->status === 'closed') {
    return response()->json([
        'error' => 'This conversation has been closed by support team. Please create a new conversation if you need further assistance.',
        'conversation_closed' => true,
        'status' => 'closed'
    ], 403);
}
```

### 2. **Auto-Reopen di `adminReply()` (Admin)**

```php
// If conversation is closed, admin can reply and it will be reopened
$wasClosed = $conversation->status === 'closed';

// Update conversation status to open
DB::table('support_conversations')
    ->where('id', $conversationId)
    ->update([
        'updated_at' => now(),
        'status' => 'open'
    ]);

// Log if conversation was reopened
if ($wasClosed) {
    \Log::info('Support conversation reopened by admin', [
        'conversation_id' => $conversationId,
        'admin_id' => $userId,
        'previous_status' => 'closed',
        'new_status' => 'open'
    ]);
}
```

## Implementasi Frontend

### 1. **Error Handling di `sendMessage()`**

```javascript
} catch (error) {
    console.error('Error sending message:', error);
    
    // Handle conversation closed error
    if (error.response?.data?.conversation_closed) {
        alert('This conversation has been closed by support team. Please create a new conversation if you need further assistance.');
        // Optionally refresh conversations to show updated status
        await fetchConversations();
    } else if (error.response?.data?.error) {
        alert(error.response.data.error);
    }
}
```

### 2. **Visual Status Indicator**

```vue
<span :class="getStatusColor(conversation.status)" 
      class="text-xs px-2 py-1 rounded-full">
    {{ conversation.status }}
</span>
```

**Status Colors:**
- `open`: Green (bg-green-100 text-green-800)
- `closed`: Gray (bg-gray-100 text-gray-800)  
- `pending`: Yellow (bg-yellow-100 text-yellow-800)

## Flow Diagram

```
User mengirim chat
        ↓
Cek status conversation
        ↓
┌─────────────────┬─────────────────┐
│ Status = "open" │ Status = "closed"│
│       ↓         │       ↓         │
│ Chat berhasil   │ Error 403       │
│ dikirim         │                 │
│                 │ Pesan:           │
│                 │ "Conversation    │
│                 │ closed. Create   │
│                 │ new one."        │
└─────────────────┴─────────────────┘
```

## Admin Flow

```
Admin membalas chat
        ↓
Cek status conversation
        ↓
┌─────────────────┬─────────────────┐
│ Status = "open" │ Status = "closed"│
│       ↓         │       ↓         │
│ Reply berhasil  │ Reply berhasil  │
│ dikirim         │ dikirim         │
│                 │ + Auto-reopen   │
│                 │ + Log activity  │
└─────────────────┴─────────────────┘
```

## Response Format

### **Error Response (User)**
```json
{
    "error": "This conversation has been closed by support team. Please create a new conversation if you need further assistance.",
    "conversation_closed": true,
    "status": "closed"
}
```

### **Success Response (Admin)**
```json
{
    "id": 123,
    "message": "Admin reply message",
    "message_type": "text",
    "sender_type": "admin",
    "created_at": "2025-01-20 10:30:00",
    "sender_name": "Admin Name"
}
```

## Logging

### **Conversation Reopened Log**
```php
\Log::info('Support conversation reopened by admin', [
    'conversation_id' => $conversationId,
    'admin_id' => $userId,
    'previous_status' => 'closed',
    'new_status' => 'open'
]);
```

## Testing Scenarios

### 1. **User Scenario**
1. Admin close conversation
2. User coba kirim chat
3. ❌ Error: "Conversation closed"
4. User buat conversation baru
5. ✅ Chat berhasil dikirim

### 2. **Admin Scenario**
1. Admin close conversation
2. Admin balas chat
3. ✅ Chat berhasil dikirim
4. ✅ Conversation status = "open"
5. ✅ Log: "Conversation reopened"

### 3. **Visual Indicator**
1. Conversation list tampilkan status
2. Closed conversation tampil dengan warna gray
3. Open conversation tampil dengan warna green

## Keuntungan Fitur Ini

1. ✅ **Mencegah spam**: User tidak bisa spam chat di conversation closed
2. ✅ **Clear workflow**: User tahu harus buat conversation baru
3. ✅ **Admin control**: Admin bisa buka kembali conversation jika perlu
4. ✅ **Audit trail**: Logging untuk tracking conversation lifecycle
5. ✅ **Better UX**: Visual indicator status conversation
6. ✅ **Data integrity**: Conversation status konsisten

## Database Impact

### **Tabel `support_conversations`**
- Status field: `open`, `closed`, `pending`
- Updated_at: Timestamp terakhir diupdate
- Logging: Activity log untuk audit

### **Tabel `support_messages`**
- Sender_type: `user`, `admin`, `system`
- Created_at: Timestamp pesan
- Message: Konten pesan

Sistem sekarang memastikan bahwa conversation yang sudah closed tidak bisa diisi chat baru oleh user, sambil tetap memberikan fleksibilitas untuk admin membuka kembali conversation jika diperlukan!
