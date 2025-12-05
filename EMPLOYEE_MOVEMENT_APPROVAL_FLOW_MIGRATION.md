# Employee Movement Approval Flow Migration Guide

## Status Implementasi

### ✅ Completed:
1. ✅ Migration SQL untuk tabel `employee_movement_approval_flows`
2. ✅ Model `EmployeeMovementApprovalFlow` 
3. ✅ Relationship `approvalFlows()` di model `EmployeeMovement`
4. ✅ Update method `store()` untuk create approval flows dari array `approvers`
5. ✅ Update method `show()` dan `edit()` untuk load approval flows
6. ✅ Backward compatibility: masih support kolom lama (hod_approver_id, dll)

### ⏳ Still Need to Do:
1. ⏳ Update method `update()` untuk handle approval flows (partially done)
2. ⏳ Update method `approve()` untuk menggunakan approval flows (seperti PR Ops)
3. ⏳ Update method `reject()` untuk menggunakan approval flows
4. ⏳ Update `getNextApprover()` dan helper methods untuk support approval flows
5. ⏳ Update `Create.vue` untuk input approval flows seperti PR Ops
6. ⏳ Update `Edit.vue` untuk input approval flows seperti PR Ops
7. ⏳ Update `Show.vue` untuk menampilkan approval flows seperti PR Ops
8. ⏳ Migration script untuk migrate data lama ke approval flows

## Cara Menjalankan Migration

1. Jalankan SQL migration:
```sql
-- Jalankan file: database/migrations/create_employee_movement_approval_flows_table.sql
```

2. Setelah migration, jalankan script untuk migrate data lama (akan dibuat kemudian)

## Struktur Approval Flow

Tabel `employee_movement_approval_flows` memiliki struktur:
- `id`: Primary key
- `employee_movement_id`: Foreign key ke `employee_movements`
- `approver_id`: Foreign key ke `users`
- `approval_level`: Integer (1 = terendah, semakin tinggi semakin tinggi levelnya)
- `status`: ENUM('PENDING', 'APPROVED', 'REJECTED')
- `approved_at`: Timestamp
- `rejected_at`: Timestamp
- `comments`: TEXT

## Backward Compatibility

Sistem masih mendukung kolom lama:
- `hod_approver_id`, `gm_approver_id`, `gm_hr_approver_id`, `bod_approver_id`
- `hod_approval`, `gm_approval`, `gm_hr_approval`, `bod_approval`

Jika data lama tidak memiliki approval flows, sistem akan:
1. Cek apakah ada approval flows
2. Jika tidak ada, gunakan kolom lama untuk backward compatibility
3. Jika ada, gunakan approval flows (prioritas)

## Next Steps

1. Update method `approve()` untuk menggunakan approval flows
2. Update Vue components (Create, Edit, Show)
3. Test dengan data baru dan data lama
4. Buat migration script untuk data lama

