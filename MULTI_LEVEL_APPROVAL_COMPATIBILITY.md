# Multi-Level Approval - Compatibility & HRD Approval

## ✅ HRD Approval Tetap Dipertahankan

**Ya, HRD approval tetap ada dan berfungsi normal!**

Alur lengkap:
1. User submit → Multiple approvers (Level 1, 2, 3, dst)
2. Approver Level 1 approve → Notifikasi ke Approver Level 2
3. Approver Level 2 approve → Notifikasi ke Approver Level 3 (jika ada)
4. ... (berlanjut sesuai jumlah approver)
5. **Setelah SEMUA approver approve** → **Baru muncul di approval HRD** ✅
6. HRD approve → Selesai ✅

## 📋 Query SQL (Tanpa Migration)

Jalankan query ini langsung di database:

```sql
-- File: database/sql/create_absent_request_approval_flows_table.sql

CREATE TABLE IF NOT EXISTS `absent_request_approval_flows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `absent_request_id` BIGINT UNSIGNED NOT NULL,
  `approver_id` BIGINT UNSIGNED NOT NULL,
  `approval_level` INT NOT NULL COMMENT 'Level 1 = pertama, level terakhir = tertinggi',
  `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  `notes` TEXT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `rejected_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  INDEX `idx_absent_request_level` (`absent_request_id`, `approval_level`),
  INDEX `idx_approver_status` (`approver_id`, `status`),
  FOREIGN KEY (`absent_request_id`) REFERENCES `absent_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## ✅ Backward Compatibility - Data Lama Tetap Compatible

### 1. Data Lama (Tanpa Approval Flows)
- **Status:** ✅ **FULLY COMPATIBLE**
- Data lama yang tidak punya record di `absent_request_approval_flows` akan menggunakan **old flow**
- Flow: Approver approve → Langsung ke HRD (seperti sebelumnya)
- Tidak ada perubahan pada data lama

### 2. Data Baru (Dengan Approval Flows)
- Jika submit dengan `approvers` (array) → Akan membuat record di `absent_request_approval_flows`
- Jika submit dengan `approver_id` (single) → Akan membuat 1 record di `absent_request_approval_flows` (Level 1)
- Setelah semua approve → Baru ke HRD

### 3. Mixed Data (Lama + Baru)
- Sistem otomatis detect:
  - Jika ada record di `absent_request_approval_flows` → Gunakan new flow
  - Jika tidak ada → Gunakan old flow (backward compatible)

## 🔄 Alur Approval

### Old Flow (Data Lama - Tetap Bekerja):
```
User Submit → Approver Approve → HRD Approve → Done
```

### New Flow (Data Baru):
```
User Submit → Approver 1 Approve → Approver 2 Approve → ... → Approver N Approve → HRD Approve → Done
```

## 📝 API Usage

### Untuk Multi-Level (Baru):
```json
POST /api/attendance/absent-request
{
  "leave_type_id": 1,
  "date_from": "2024-12-20",
  "date_to": "2024-12-22",
  "reason": "Cuti tahunan",
  "approvers": [10, 15, 20]  // Array - multiple approvers berjenjang
}
```

### Untuk Single Approver (Backward Compatible):
```json
POST /api/attendance/absent-request
{
  "leave_type_id": 1,
  "date_from": "2024-12-20",
  "date_to": "2024-12-22",
  "reason": "Cuti tahunan",
  "approver_id": 10  // Single - tetap bisa digunakan
}
```

## ⚠️ Important Notes

1. **Table `absent_request_approval_flows` adalah OPTIONAL**
   - Jika table tidak ada, sistem tetap berjalan dengan old flow
   - Data lama tidak terpengaruh

2. **HRD Approval Tetap Ada**
   - Setelah semua approver approve, status = `supervisor_approved`
   - HRD akan melihat di `getPendingHrdApprovals()`
   - HRD approve menggunakan method `approveHrd()` yang sudah ada

3. **Data Lama Tidak Perlu Di-migrate**
   - Data lama tetap bisa diproses dengan old flow
   - Tidak perlu membuat approval flows untuk data lama
   - Hanya data baru yang akan menggunakan new flow

4. **Reject Behavior**
   - Jika salah satu approver reject → Request langsung rejected
   - Tidak lanjut ke approver berikutnya
   - Tidak lanjut ke HRD

## 🧪 Testing Checklist

- [x] Data lama (tanpa approval flows) tetap bisa di-approve
- [x] Data baru (dengan approval flows) sequential approval bekerja
- [x] HRD approval tetap muncul setelah semua approver approve
- [x] Reject di level manapun langsung reject request
- [x] Backward compatibility dengan `approver_id` (single)

