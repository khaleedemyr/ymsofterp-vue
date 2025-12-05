# Menu Member - Dokumentasi Lengkap

## Overview
Menu Member adalah fitur untuk mengelola data member/customer yang tersimpan di database kedua (`mysql_second`). Menu ini menggunakan tabel `costumers` dan terhubung ke server database yang berbeda dari database utama aplikasi.

## Fitur Utama

### 1. Dashboard Member
- **Statistik Real-time**: Total member, member aktif, member diblokir, member eksklusif
- **Filter Multi-level**: Status aktif, status block, member eksklusif
- **Pencarian Cepat**: Berdasarkan ID, NIK, nama, email, telepon
- **Tampilan Responsif**: Grid dan list view

### 2. Manajemen Member
- **CRUD Lengkap**: Create, Read, Update, Delete member
- **Toggle Status**: Aktif/nonaktif member dengan satu klik
- **Toggle Block**: Block/unblock member dengan konfirmasi
- **Validasi Data**: Form validation untuk data yang akurat

### 3. Informasi Member
- **Data Pribadi**: NIK, nama, email, telepon, alamat
- **Data Keamanan**: Password, PIN, barcode, hint
- **Status Keanggotaan**: Aktif, block, eksklusif
- **Riwayat Sistem**: Tanggal register, last login, device

## Struktur Database

### Tabel: `costumers` (Database Kedua)
```sql
CREATE TABLE costumers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    costumers_id VARCHAR(50) UNIQUE,
    nik VARCHAR(20),
    name VARCHAR(255),
    nama_panggilan VARCHAR(100),
    email VARCHAR(255),
    alamat TEXT,
    telepon VARCHAR(20),
    tanggal_lahir DATE,
    jenis_kelamin VARCHAR(1), -- L/P
    pekerjaan VARCHAR(100),
    valid_until VARCHAR(50),
    status_aktif VARCHAR(1), -- Y/N
    password2 VARCHAR(255),
    android_password VARCHAR(255),
    hint VARCHAR(255),
    barcode VARCHAR(255),
    pin VARCHAR(10),
    tanggal_aktif DATE,
    status_block VARCHAR(1), -- Y/N
    last_logged TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    tanggal_register DATE,
    device VARCHAR(255),
    exclusive_member VARCHAR(1) -- Y/N
);
```

## File yang Dibuat

### 1. Model
- **File**: `app/Models/Customer.php`
- **Connection**: `mysql_second`
- **Fitur**: 
  - Scope untuk filtering (active, blocked, exclusive, search)
  - Accessor untuk readable text (status, gender, exclusive)
  - Method untuk test connection dan sync data

### 2. Controller
- **File**: `app/Http/Controllers/MemberController.php`
- **Fitur**:
  - CRUD operations dengan transaction
  - Filtering dan pagination
  - Toggle status dan block
  - Export data
  - Statistics calculation

### 3. Routes
- **File**: `routes/web.php`
- **Routes**:
  ```php
  Route::resource('members', MemberController::class);
  Route::patch('members/{member}/toggle-status', [MemberController::class, 'toggleStatus']);
  Route::patch('members/{member}/toggle-block', [MemberController::class, 'toggleBlock']);
  Route::get('members/export', [MemberController::class, 'export']);
  ```

### 4. Vue Components
- **Index**: `resources/js/Pages/Members/Index.vue`
- **Create**: `resources/js/Pages/Members/Create.vue`
- **Show**: `resources/js/Pages/Members/Show.vue`
- **Edit**: `resources/js/Pages/Members/Edit.vue`

## Cara Penggunaan

### 1. Akses Menu
```
URL: /members
Method: GET
Middleware: auth
```

### 2. Tambah Member Baru
```
URL: /members/create
Method: GET
Action: Menampilkan form tambah member
```

### 3. Simpan Member
```
URL: /members
Method: POST
Validation: costumers_id (unique), nik, name (required)
```

### 4. Lihat Detail Member
```
URL: /members/{id}
Method: GET
Action: Menampilkan detail lengkap member
```

### 5. Edit Member
```
URL: /members/{id}/edit
Method: GET
Action: Menampilkan form edit member
```

### 6. Update Member
```
URL: /members/{id}
Method: PUT
Action: Update data member
```

### 7. Hapus Member
```
URL: /members/{id}
Method: DELETE
Action: Hapus member dengan konfirmasi
```

### 8. Toggle Status
```
URL: /members/{id}/toggle-status
Method: PATCH
Action: Aktif/nonaktif member
```

### 9. Toggle Block
```
URL: /members/{id}/toggle-block
Method: PATCH
Action: Block/unblock member
```

## Filter dan Pencarian

### Filter yang Tersedia:
1. **Status Aktif**: Aktif / Tidak Aktif
2. **Status Block**: Diblokir / Tidak Diblokir
3. **Member Eksklusif**: Ya / Tidak
4. **Pencarian**: ID, NIK, nama, email, telepon

### Contoh Query:
```php
// Filter member aktif yang tidak diblokir
$members = Customer::active()->notBlocked()->get();

// Pencarian member
$members = Customer::search('john')->get();

// Member eksklusif
$members = Customer::exclusive()->get();
```

## Statistik Dashboard

### Metrics yang Ditampilkan:
1. **Total Member**: Semua member dalam sistem
2. **Member Aktif**: Member dengan status_aktif = 'Y'
3. **Member Diblokir**: Member dengan status_block = 'Y'
4. **Member Eksklusif**: Member dengan exclusive_member = 'Y'

### Perhitungan Real-time:
```php
$stats = [
    'total_members' => Customer::count(),
    'active_members' => Customer::where('status_aktif', 'Y')->count(),
    'blocked_members' => Customer::where('status_block', 'Y')->count(),
    'exclusive_members' => Customer::where('exclusive_member', 'Y')->count(),
];
```

## Validasi Data

### Required Fields:
- `costumers_id` (unique)
- `nik`
- `name`
- `status_aktif`
- `status_block`
- `exclusive_member`

### Validation Rules:
```php
'costumers_id' => 'required|string|max:50|unique:mysql_second.costumers,costumers_id',
'nik' => 'required|string|max:20',
'name' => 'required|string|max:255',
'email' => 'nullable|email|max:255',
'status_aktif' => 'required|in:Y,N',
'status_block' => 'required|in:Y,N',
'exclusive_member' => 'required|in:Y,N',
```

## Error Handling

### Database Connection Error:
- Try-catch untuk connection database kedua
- Rollback transaction jika terjadi error
- Log error untuk debugging

### Validation Error:
- Display error messages di form
- Highlight field yang error
- Prevent form submission jika ada error

## Security Features

### Data Protection:
- Password fields tidak ditampilkan (••••••••)
- PIN dan barcode di-mask
- Access control dengan middleware auth

### Transaction Safety:
- Database transaction untuk operasi critical
- Rollback otomatis jika terjadi error
- Data consistency guarantee

## Performance Optimization

### Database Optimization:
- Index pada kolom yang sering di-query
- Pagination untuk data besar
- Eager loading untuk relasi

### Frontend Optimization:
- Debounced search (400ms delay)
- Lazy loading untuk komponen
- Optimized re-rendering

## Troubleshooting

### Common Issues:

1. **Connection Error**
   ```
   Error: Connection refused
   Solution: Periksa konfigurasi database kedua di .env
   ```

2. **Table Not Found**
   ```
   Error: Table 'costumers' doesn't exist
   Solution: Pastikan tabel sudah dibuat di database kedua
   ```

3. **Permission Denied**
   ```
   Error: Access denied for user
   Solution: Periksa username/password database kedua
   ```

### Debug Commands:
```bash
# Test connection database kedua
php artisan tinker
DB::connection('mysql_second')->select('SELECT 1 as test');

# Check table structure
php artisan tinker
DB::connection('mysql_second')->select('DESCRIBE costumers');
```

## Future Enhancements

### Planned Features:
1. **Bulk Operations**: Import/export Excel
2. **Advanced Filtering**: Date range, age group
3. **Member Analytics**: Usage statistics, trends
4. **API Integration**: Mobile app integration
5. **Notification System**: Email/SMS notifications

### Technical Improvements:
1. **Caching**: Redis cache untuk statistik
2. **Queue**: Background jobs untuk bulk operations
3. **Audit Trail**: Log semua perubahan data
4. **Backup**: Automated backup system

## Support

### Contact:
- **Developer**: AI Assistant
- **Documentation**: File ini
- **Issues**: GitHub Issues (jika ada)

### Resources:
- Laravel Documentation
- Vue.js Documentation
- Inertia.js Documentation 