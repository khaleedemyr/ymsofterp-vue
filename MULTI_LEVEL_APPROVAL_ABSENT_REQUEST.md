# Multi-Level Approval untuk Absent Request

## Overview
Sistem approval untuk absent request sekarang mendukung **multiple approvers berjenjang**. Setelah semua approver approve, baru akan muncul di approval HRD.

## Alur Approval

### Sebelumnya (Single Approver):
1. User submit absent request → Pilih 1 approver
2. Approver approve → Langsung muncul di HRD
3. HRD approve → Selesai

### Sekarang (Multi-Level Approver):
1. User submit absent request → Pilih multiple approvers (berjenjang)
2. Approver Level 1 approve → Notifikasi ke Approver Level 2
3. Approver Level 2 approve → Notifikasi ke Approver Level 3 (jika ada)
4. ... (berlanjut sesuai jumlah approver)
5. **Setelah SEMUA approver approve** → Baru muncul di approval HRD
6. HRD approve → Selesai

## Database Changes

### Table Baru: `absent_request_approval_flows`
```sql
CREATE TABLE `absent_request_approval_flows` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `absent_request_id` BIGINT UNSIGNED NOT NULL,
  `approver_id` BIGINT UNSIGNED NOT NULL,
  `approval_level` INT NOT NULL, -- Level 1 = pertama, level terakhir = tertinggi
  `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
  `notes` TEXT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `rejected_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`absent_request_id`) REFERENCES `absent_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_absent_request_level` (`absent_request_id`, `approval_level`),
  INDEX `idx_approver_status` (`approver_id`, `status`)
);
```

## API Changes

### 1. Submit Absent Request
**Endpoint:** `POST /api/attendance/absent-request`

**Request Body (Baru):**
```json
{
  "leave_type_id": 1,
  "date_from": "2024-12-20",
  "date_to": "2024-12-22",
  "reason": "Cuti tahunan",
  "approvers": [10, 15, 20],  // Array of approver IDs (berjenjang)
  "document": null
}
```

**Request Body (Backward Compatibility):**
```json
{
  "leave_type_id": 1,
  "date_from": "2024-12-20",
  "date_to": "2024-12-22",
  "reason": "Cuti tahunan",
  "approver_id": 10,  // Single approver (masih bisa digunakan)
  "document": null
}
```

### 2. Approval Flow
- Approver hanya bisa approve jika dia adalah **next approver in line** (level terendah yang masih PENDING)
- Setelah approve, otomatis kirim notifikasi ke approver berikutnya
- Setelah semua approver approve, baru kirim notifikasi ke HRD

## Frontend Changes (TODO)

### Modal Absent Request
Perlu diupdate untuk:
1. Input multiple approvers (bisa tambah/hapus approver)
2. Tampilkan urutan approver (Level 1, Level 2, dst)
3. Validasi minimal 1 approver

**Contoh UI:**
```
Approvers (Berjenjang):
[+] Tambah Approver

1. [Dropdown Approver 1] [X]
2. [Dropdown Approver 2] [X]
3. [Dropdown Approver 3] [X]
```

## Migration

Jalankan migration:
```bash
php artisan migrate
```

Atau langsung jalankan SQL:
```sql
-- File: database/migrations/2024_12_20_000001_create_absent_request_approval_flows_table.php
```

## Backward Compatibility

Sistem tetap mendukung:
- Single approver (menggunakan `approver_id`)
- Old approval flow (tanpa approval flows table)

## Testing

1. **Test Multi-Level Approval:**
   - Submit absent request dengan 3 approvers
   - Approver 1 approve → Cek notifikasi ke Approver 2
   - Approver 2 approve → Cek notifikasi ke Approver 3
   - Approver 3 approve → Cek notifikasi ke HRD
   - HRD approve → Selesai

2. **Test Single Approver (Backward Compatibility):**
   - Submit absent request dengan `approver_id` (bukan `approvers`)
   - Approver approve → Langsung ke HRD (seperti sebelumnya)

3. **Test Reject:**
   - Approver reject → Request langsung rejected, tidak lanjut ke approver berikutnya

## Notes

- Approval level dimulai dari 1 (terendah) dan naik sesuai urutan
- Setelah semua approver approve, status `absent_requests.status` = `supervisor_approved` (menunggu HRD)
- HRD hanya akan melihat request setelah semua approver approve

