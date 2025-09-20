# Master Report Setup Guide

## Overview
Menu Master Report telah berhasil dibuat dengan fitur:
- **Departemen**: Manajemen data departemen dengan kode, nama, dan deskripsi
- **Area**: Manajemen data area yang terhubung dengan departemen
- **Layout**: Menggunakan layout yang mirip dengan menu master data karyawan
- **Modal**: Create dan edit menggunakan modal, bukan halaman baru
- **Database**: Menggunakan query SQL langsung tanpa migration

## Files Created

### Backend
1. **Models**:
   - `app/Models/Departemen.php` - Model untuk departemen
   - `app/Models/Area.php` - Model untuk area

2. **Controller**:
   - `app/Http/Controllers/MasterReportController.php` - Controller dengan CRUD operations

3. **Routes**:
   - Routes ditambahkan di `routes/web.php`

### Frontend
1. **Pages**:
   - `resources/js/Pages/MasterReport/Index.vue` - Halaman utama dengan layout mirip data karyawan
   - `resources/js/Pages/MasterReport/MasterReportFormModal.vue` - Modal form untuk create/edit

2. **Layout**:
   - Menu ditambahkan di `resources/js/Layouts/AppLayout.vue`

### Database
1. **SQL File**:
   - `database/sql/create_master_report_tables.sql` - Query untuk membuat tabel dan sample data

## Database Setup

### Step 1: Jalankan SQL Query
Jalankan file SQL berikut di database Anda:

```sql
-- File: database/sql/create_master_report_tables.sql
-- Jalankan query ini di MySQL/database management tool Anda
```

### Step 2: Verifikasi Tabel
Pastikan tabel berikut telah dibuat:
- `departemens` - Tabel master departemen
- `areas` - Tabel master area

## Features

### 1. Toggle Type
- User dapat beralih antara tampilan Departemen dan Area
- Statistics cards berubah sesuai dengan tipe yang dipilih

### 2. Statistics Cards
- **Total**: Menampilkan total data
- **Aktif**: Menampilkan data dengan status aktif
- **Non-Aktif**: Menampilkan data dengan status non-aktif
- Cards dapat diklik untuk filter status

### 3. Search & Filter
- Search berdasarkan nama, kode, dan deskripsi
- Filter berdasarkan status (Aktif, Non-Aktif, Semua)
- Real-time search dengan debounce

### 4. CRUD Operations
- **Create**: Tambah data baru melalui modal
- **Read**: Tampilkan data dalam tabel dengan pagination
- **Update**: Edit data melalui modal
- **Delete**: Hapus data dengan konfirmasi
- **Toggle Status**: Ubah status aktif/non-aktif

### 5. Validation
- Validasi form dengan error handling
- Unique constraint untuk kode departemen/area
- Required field validation

## Menu Access
Menu Master Report dapat diakses melalui:
- **Sidebar**: Master Data > Master Report
- **Route**: `/master-report`
- **Code**: `master_report`

## Sample Data
SQL file sudah include sample data:
- 5 departemen (HR, Finance, Operations, Marketing, IT Support)
- 10 area yang terhubung dengan departemen

## Notes
- Layout mengikuti desain menu master data karyawan
- Menggunakan modal untuk create/edit sesuai permintaan
- Database menggunakan query SQL langsung tanpa migration
- Responsive design dengan Tailwind CSS
- Menggunakan Inertia.js untuk SPA experience
