# Menu Data Outlet

Menu Data Outlet telah dibuat dengan struktur yang sama seperti menu Jabatan. Menu ini memungkinkan pengguna untuk mengelola data outlet dengan fitur CRUD lengkap.

## Struktur Tabel

Tabel `tbl_data_outlet` memiliki field-field berikut:

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id_outlet` | int | Primary key, auto increment |
| `nama_outlet` | varchar(100) | Nama outlet |
| `lokasi` | text | Alamat lengkap outlet |
| `qr_code` | varchar(255) | Kode QR outlet (auto-generate jika kosong) |
| `lat` | varchar(50) | Latitude koordinat |
| `long` | varchar(50) | Longitude koordinat |
| `keterangan` | text | Keterangan tambahan |
| `region_id` | int | Foreign key ke tabel regions |
| `status` | varchar(1) | Status outlet ('A' = Active, 'N' = Inactive) |
| `url_places` | text | URL Google Places |
| `created_at` | datetime | Timestamp pembuatan |
| `updated_at` | datetime | Timestamp update |

## Fitur yang Tersedia

### 1. Halaman Index (`/outlets`)
- **Tampilan**: Tabel dengan kolom Nama Outlet, Lokasi, Region, QR Code, Koordinat, Status, dan Aksi
- **Fitur**:
  - Pencarian berdasarkan nama outlet, lokasi, atau QR code
  - Filter status (Active/Inactive)
  - Pagination
  - Toggle status outlet
  - Edit dan hapus outlet
  - Debug database (untuk troubleshooting)

### 2. Form Modal
- **Tambah Outlet Baru**: Modal dengan form lengkap
- **Edit Outlet**: Modal dengan data yang sudah terisi
- **Field yang tersedia**:
  - Nama Outlet (required)
  - Lokasi (required, textarea)
  - Region (required, dropdown)
  - QR Code (optional, auto-generate)
  - Latitude & Longitude (optional)
  - URL Google Places (optional)
  - Keterangan (optional, textarea)
  - Status (Active/Inactive)

### 3. API Endpoints
- `GET /outlets` - Halaman index
- `POST /outlets` - Tambah outlet baru
- `PUT /outlets/{id}` - Update outlet
- `DELETE /outlets/{id}` - Hapus outlet (soft delete)
- `PATCH /outlets/{id}/toggle-status` - Toggle status
- `GET /outlets/dropdown-data` - Data dropdown regions
- `GET /outlets/debug-database` - Debug database
- `GET /outlets/{id}/download-qr` - Download QR code
- `GET /api/outlets` - API list outlet

## File yang Dibuat/Dimodifikasi

### Backend
1. **Model**: `app/Models/Outlet.php`
   - Updated dengan field baru
   - Relationship dengan Region
   - Accessor dan scope

2. **Controller**: `app/Http/Controllers/OutletController.php`
   - CRUD operations
   - Validation
   - Activity logging
   - Dropdown data
   - Debug functionality

3. **Migration**: `database/migrations/2024_12_20_000000_update_outlets_table_add_missing_fields.php`
   - Menambahkan field yang diperlukan
   - Foreign key constraint

4. **Routes**: `routes/web.php`
   - Menambahkan routes untuk dropdown dan debug

### Frontend
1. **Index Page**: `resources/js/Pages/Outlets/Index.vue`
   - Tabel dengan semua kolom
   - Search dan filter
   - Pagination
   - Debug button

2. **Form Modal**: `resources/js/Pages/Outlets/OutletFormModal.vue`
   - Form lengkap dengan semua field
   - Loading state
   - Validation

## Cara Penggunaan

### 1. Menambah Outlet Baru
1. Klik tombol "+ Buat Outlet Baru"
2. Isi form dengan data outlet
3. Klik "Simpan"

### 2. Edit Outlet
1. Klik tombol "Edit" pada baris outlet
2. Modifikasi data yang diperlukan
3. Klik "Update"

### 3. Hapus Outlet
1. Klik tombol "Hapus" pada baris outlet
2. Konfirmasi penghapusan
3. Outlet akan dinonaktifkan (soft delete)

### 4. Toggle Status
1. Klik tombol status (Active/Inactive)
2. Status akan berubah secara otomatis

### 5. Debug Database
1. Klik tombol "Debug DB"
2. Lihat informasi database dan tabel

## Validasi

### Backend Validation
- `nama_outlet`: required, string, max 100 karakter
- `lokasi`: required, string
- `qr_code`: optional, string, max 255 karakter
- `lat`: optional, string, max 50 karakter
- `long`: optional, string, max 50 karakter
- `keterangan`: optional, string
- `region_id`: required, exists in regions table
- `status`: required, in ['A', 'N']
- `url_places`: optional, string

### Frontend Validation
- Form validation dengan error messages
- Required field indicators
- Input type validation (URL untuk url_places)

## Activity Logging

Semua operasi CRUD akan dicatat dalam `activity_logs` table dengan informasi:
- User ID
- Activity type (create, update, delete)
- Module (outlets)
- Description
- IP address
- User agent
- Old data dan new data

## Dependencies

- Laravel 10+
- Inertia.js
- Vue.js 3
- Tailwind CSS
- SweetAlert2
- Axios

## Troubleshooting

Jika ada masalah dengan menu outlet:

1. **Debug Database**: Klik tombol "Debug DB" untuk melihat status database
2. **Check Console**: Lihat browser console untuk error JavaScript
3. **Check Logs**: Lihat Laravel logs di `storage/logs/laravel.log`
4. **Migration**: Pastikan migration sudah dijalankan dengan `php artisan migrate`

## Catatan

- QR Code akan auto-generate jika tidak diisi manual
- Status default untuk outlet baru adalah 'A' (Active)
- Soft delete digunakan untuk penghapusan outlet
- Koordinat dapat digunakan untuk integrasi dengan Google Maps
- URL Places dapat digunakan untuk link ke Google Places 