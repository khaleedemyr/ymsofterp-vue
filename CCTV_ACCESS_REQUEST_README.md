# CCTV Access Request - Dokumentasi

## Instalasi

### 1. Jalankan Query SQL

Jalankan query SQL yang ada di file `database/sql/create_cctv_access_requests_table.sql` untuk membuat tabel di database.

```sql
-- Jalankan query ini di database Anda
-- File: database/sql/create_cctv_access_requests_table.sql
```

### 2. Konfigurasi IT Manager

Edit file `app/Http/Controllers/CctvAccessRequestController.php` dan sesuaikan method `isITManager()` dan `getITManagers()` sesuai dengan struktur sistem Anda:

**Opsi yang tersedia:**
1. **By division_id**: Jika IT division memiliki ID tertentu
2. **By id_jabatan**: Jika IT Manager memiliki position ID tertentu  
3. **By id_role**: Jika IT Manager memiliki role ID tertentu
4. **By jabatan name**: Otomatis mencari jabatan yang mengandung "IT Manager"
5. **By divisi name**: Otomatis mencari divisi yang mengandung "IT"

**Contoh konfigurasi:**

```php
// Jika IT division memiliki ID = 5
private function isITManager($user)
{
    if (!$user || $user->status !== 'A') {
        return false;
    }
    
    // Opsi 1: Check by division_id
    if ($user->division_id === 5) {
        return true;
    }
    
    // Opsi 2: Check by id_jabatan
    if ($user->id_jabatan === 123) {
        return true;
    }
    
    return false;
}
```

### 3. Konfigurasi IT Team untuk Playback

Edit method `canRequestPlayback()` di file `app/Models/CctvAccessRequest.php` untuk menentukan siapa saja yang boleh request akses playback.

## API Endpoints

### List Requests
```
GET /api/cctv-access-requests
Query Parameters:
  - status: pending|approved|rejected|revoked|all (default: all)
  - access_type: live_view|playback|all (default: all)
  - search: string (search by reason or user name)
  - per_page: number (default: 15)
```

### Pending Approvals (IT Manager only)
```
GET /api/cctv-access-requests/pending-approvals
Query Parameters:
  - access_type: live_view|playback|all (default: all)
  - per_page: number (default: 15)
```

### My Requests
```
GET /api/cctv-access-requests/my-requests
Query Parameters:
  - status: pending|approved|rejected|revoked|all (default: all)
  - access_type: live_view|playback|all (default: all)
  - per_page: number (default: 15)
```

### Statistics
```
GET /api/cctv-access-requests/stats
```

### Create Request

**Untuk Live View:**
```
POST /api/cctv-access-requests
Body:
{
  "access_type": "live_view",
  "reason": "string (required, max 1000)",
  "outlet_ids": [1, 2, 3] (required, array of outlet IDs),
  "email": "user@example.com" (required, valid email)
}
```

**Untuk Playback:**
```
POST /api/cctv-access-requests
Body:
{
  "access_type": "playback",
  "reason": "string (required, max 1000)",
  "outlet_ids": [1, 2, 3] (required, array of outlet IDs),
  "area": "string (required, max 255)",
  "time_from": "HH:mm" (required, format: 14:30),
  "time_to": "HH:mm" (required, must be after time_from),
  "incident_description": "string (required, max 1000)"
}
```

### Get Request Details
```
GET /api/cctv-access-requests/{id}
```

### Update Request (only pending)
```
PUT /api/cctv-access-requests/{id}
Body: (same as create request based on access_type)
```

### Cancel Request (only pending)
```
DELETE /api/cctv-access-requests/{id}
```

### Approve Request (IT Manager only)
```
POST /api/cctv-access-requests/{id}/approve
Body:
{
  "approval_notes": "string (optional, max 500)"
}
```

### Reject Request (IT Manager only)
```
POST /api/cctv-access-requests/{id}/reject
Body:
{
  "approval_notes": "string (required, max 500)"
}
```

### Revoke Access (IT Manager only)
```
POST /api/cctv-access-requests/{id}/revoke
Body:
{
  "revocation_reason": "string (required, max 500)"
}
```

## Fitur

1. **Live View Request**: 
   - Semua user dapat request akses live view
   - Harus pilih outlet dan cantumkan email
2. **Playback Request**: 
   - Hanya tim IT yang dapat request akses playback
   - Harus cantumkan outlet, area, jam (time_from & time_to), dan deskripsi kejadian
3. **Approval Flow**: Semua request harus disetujui oleh IT Manager
4. **Revocation**: IT Manager dapat mencabut akses kapan saja
5. **Notifications**: Notifikasi otomatis untuk requester dan IT Manager

## Status Request

- **pending**: Menunggu approval IT Manager
- **approved**: Disetujui dan aktif
- **rejected**: Ditolak oleh IT Manager
- **revoked**: Akses dicabut oleh IT Manager

## Notifikasi

Sistem akan mengirim notifikasi untuk:
- Request baru (ke IT Manager)
- Request disetujui (ke requester)
- Request ditolak (ke requester)
- Akses dicabut (ke requester)

## Catatan Penting

1. **Pastikan untuk mengkonfigurasi method `isITManager()` dan `getITManagers()`** sesuai dengan struktur database Anda
2. **Pastikan untuk mengkonfigurasi method `canRequestPlayback()`** untuk menentukan siapa yang boleh request playback
3. Semua endpoint memerlukan authentication (`auth` middleware)
4. Hanya IT Manager yang dapat approve/reject/revoke request
5. User hanya dapat melihat dan mengelola request mereka sendiri (kecuali IT Manager)

