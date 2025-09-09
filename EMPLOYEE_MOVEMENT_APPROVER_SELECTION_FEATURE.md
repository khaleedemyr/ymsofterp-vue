# Employee Movement Approver Selection Feature

## Overview
Fitur ini memungkinkan user untuk memilih approver secara manual untuk setiap level approval (HOD, GM, GM HR, BOD) saat membuat atau mengedit Employee Movement, bukan menggunakan sistem otomatis berdasarkan role.

## Changes Made

### 1. Database Schema Updates
- **File**: `add_approver_user_ids_to_employee_movements.sql`
- **Changes**:
  - Menambahkan kolom `hod_approver_id`, `gm_approver_id`, `gm_hr_approver_id`, `bod_approver_id`
  - Menambahkan foreign key constraints ke tabel `users`
  - Menambahkan indexes untuk performa yang lebih baik

### 2. Model Updates
- **File**: `app/Models/EmployeeMovement.php`
- **Changes**:
  - Menambahkan field baru ke `$fillable`
  - Menambahkan relationship methods: `hodApprover()`, `gmApprover()`, `gmHrApprover()`, `bodApprover()`

### 3. Controller Updates
- **File**: `app/Http/Controllers/EmployeeMovementController.php`
- **Changes**:
  - Menambahkan validation untuk field approver baru di method `store()` dan `update()`
  - Menambahkan method `getApprovers()` untuk mengambil daftar user yang bisa menjadi approver
  - Update method `show()` untuk load relationship approver
  - Menambahkan route untuk `getApprovers()`

### 4. Frontend Updates

#### Create Form
- **File**: `resources/js/Pages/EmployeeMovement/Create.vue`
- **Changes**:
  - Menambahkan field approver ke form data
  - Menambahkan `approvers` array untuk dropdown data
  - Menambahkan function `fetchApprovers()`
  - Menambahkan section "Approval Assignment" dengan 4 dropdown (HOD, GM, GM HR, BOD)
  - Setiap dropdown menampilkan nama lengkap, NIK, dan jabatan

#### Edit Form
- **File**: `resources/js/Pages/EmployeeMovement/Edit.vue`
- **Changes**:
  - Sama seperti Create form
  - Pre-populate field approver dengan data existing

#### Show Page
- **File**: `resources/js/Pages/EmployeeMovement/Show.vue`
- **Changes**:
  - Menambahkan section "Approval Assignment" untuk menampilkan approver yang dipilih
  - Memisahkan section "Approval Status" untuk menampilkan status approval
  - Menampilkan nama lengkap dan NIK approver yang dipilih

### 5. Routes
- **File**: `routes/web.php`
- **Changes**:
  - Menambahkan route `employee-movements/approvers` untuk mengambil data approver

## How to Use

### 1. Run Migration
```bash
php run_approver_migration.php
```

### 2. Create Employee Movement
1. Buka halaman Create Employee Movement
2. Isi data employee dan movement details
3. Di section "Approval Assignment", pilih approver untuk setiap level:
   - HOD Approver
   - GM Approver  
   - GM HR Approver
   - BOD Approver
4. Setiap dropdown menampilkan semua user aktif dengan informasi nama, NIK, dan jabatan
5. Simpan form

### 3. View Employee Movement
1. Buka halaman detail Employee Movement
2. Lihat section "Approval Assignment" untuk melihat approver yang dipilih
3. Lihat section "Approval Status" untuk melihat status approval

## Features

### Dropdown Approver Selection
- **Searchable**: User bisa mencari approver berdasarkan nama
- **Rich Display**: Menampilkan nama lengkap, NIK, dan jabatan
- **Optional**: Semua field approver bersifat optional
- **Validation**: Memvalidasi bahwa user yang dipilih benar-benar ada di database

### Data Display
- **Approval Assignment**: Menampilkan siapa yang ditugaskan sebagai approver
- **Approval Status**: Menampilkan status approval (Not signed, Approved, etc.)
- **Clear Separation**: Memisahkan antara assignment dan status untuk clarity

## Database Structure

### New Columns
```sql
hod_approver_id BIGINT UNSIGNED NULL
gm_approver_id BIGINT UNSIGNED NULL  
gm_hr_approver_id BIGINT UNSIGNED NULL
bod_approver_id BIGINT UNSIGNED NULL
```

### Foreign Keys
- Semua kolom approver_id memiliki foreign key ke `users(id)`
- Menggunakan `ON DELETE SET NULL` untuk menjaga data integrity

### Indexes
- Index pada setiap kolom approver_id untuk performa query yang lebih baik

## API Endpoints

### GET /employee-movements/approvers
- **Purpose**: Mengambil daftar user yang bisa menjadi approver
- **Response**: 
```json
{
  "success": true,
  "approvers": [
    {
      "id": 1,
      "nama_lengkap": "John Doe",
      "nik": "EMP001",
      "jabatan": {
        "id_jabatan": 1,
        "nama_jabatan": "Manager"
      }
    }
  ]
}
```

## Benefits

1. **Flexibility**: User bisa memilih approver sesuai kebutuhan bisnis
2. **Transparency**: Jelas siapa yang bertanggung jawab untuk setiap level approval
3. **User Experience**: Interface yang mudah digunakan dengan dropdown searchable
4. **Data Integrity**: Foreign key constraints memastikan data konsisten
5. **Performance**: Indexes memastikan query cepat
6. **Backward Compatibility**: Field lama tetap ada untuk compatibility

## Future Enhancements

1. **Approval Workflow**: Implementasi sistem approval yang sebenarnya
2. **Notifications**: Notifikasi otomatis ke approver yang dipilih
3. **Approval History**: Tracking history approval
4. **Bulk Assignment**: Assign approver untuk multiple employee movements
5. **Approval Templates**: Template untuk assignment approver berdasarkan jenis movement
