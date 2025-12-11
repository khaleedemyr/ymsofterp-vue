# Fix Missing HRD Approvals

## Masalah
Setelah semua supervisor approve, approval tidak muncul di akun HRD karena:
- `approval_requests.status` sudah 'approved' tapi `hrd_status` masih NULL
- `hrd_approver_id` belum diisi

## Solusi
Script ini akan memperbaiki data approval yang terlewat dengan:
1. Update `hrd_status` menjadi 'pending'
2. Set `hrd_approver_id` ke user HRD
3. Update status `absent_requests` jika perlu
4. Buat notifikasi untuk HRD (hanya untuk approval yang baru diupdate)

## Cara Menjalankan

### Opsi 1: Menggunakan PHP Script (Recommended)
```bash
cd D:\Gawean\web\ymsofterp
php fix_missing_hrd_approvals.php
```

### Opsi 2: Menggunakan SQL Script
Jalankan file `fix_missing_hrd_approvals.sql` di database Anda melalui:
- phpMyAdmin
- MySQL Workbench
- Command line: `mysql -u username -p database_name < fix_missing_hrd_approvals.sql`

## Data yang Akan Diperbaiki

### New Flow (ada absent_request_approval_flows)
- `approval_requests.status` = 'approved'
- `approval_requests.hrd_status` IS NULL atau != 'pending'
- `approval_requests.hrd_approver_id` IS NULL
- Semua `absent_request_approval_flows` sudah APPROVED (tidak ada yang PENDING)
- `absent_requests.status` = 'supervisor_approved'

### Old Flow (tidak ada absent_request_approval_flows)
- `approval_requests.status` = 'approved'
- `approval_requests.hrd_status` IS NULL atau != 'pending'
- `approval_requests.hrd_approver_id` IS NULL
- Tidak ada `absent_request_approval_flows` terkait

## Verifikasi

Setelah menjalankan script, cek dengan query berikut:

```sql
-- Cek total pending HRD approvals
SELECT COUNT(*) as total_pending_hrd_approvals
FROM approval_requests
WHERE status = 'approved' AND hrd_status = 'pending';

-- Cek detail approval yang sudah diperbaiki
SELECT 
    ar.id,
    u.nama_lengkap as user_name,
    ar.status,
    ar.hrd_status,
    hrd_user.nama_lengkap as hrd_approver_name,
    ar.date_from,
    ar.date_to,
    ar.updated_at
FROM approval_requests ar
INNER JOIN users u ON ar.user_id = u.id
LEFT JOIN users hrd_user ON ar.hrd_approver_id = hrd_user.id
WHERE ar.status = 'approved' AND ar.hrd_status = 'pending'
ORDER BY ar.updated_at DESC
LIMIT 20;
```

## Catatan

- Script ini aman untuk dijalankan berkali-kali (idempotent)
- Hanya akan update data yang memang perlu diperbaiki
- Notifikasi hanya dibuat untuk approval yang baru diupdate (dalam 1 jam terakhir)
- Tidak akan membuat duplikat notifikasi

## Troubleshooting

### Error: "Tidak ada user HRD yang aktif ditemukan"
- Pastikan ada user dengan `division_id = 6` dan `status = 'A'`
- Cek dengan query: `SELECT * FROM users WHERE division_id = 6 AND status = 'A'`

### Approval masih tidak muncul di HRD
1. Cek apakah `approval_requests.status` = 'approved'
2. Cek apakah `approval_requests.hrd_status` = 'pending'
3. Cek apakah `approval_requests.hrd_approver_id` sudah diisi
4. Pastikan user HRD login dengan akun yang `division_id = 6`
5. Refresh halaman beranda HRD

