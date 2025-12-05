# Implementasi Struktur Organisasi Per Outlet

## Overview
Sistem struktur organisasi yang memungkinkan menampilkan hierarki jabatan berdasarkan outlet. Setiap outlet dapat memiliki struktur organisasi yang berbeda.

## Struktur Database

### Tabel `tbl_data_jabatan`
```sql
CREATE TABLE tbl_data_jabatan (
    id_jabatan INT PRIMARY KEY,
    nama_jabatan VARCHAR(255),
    id_atasan VARCHAR(255), -- Merujuk ke id_jabatan lain
    id_divisi VARCHAR(255),
    id_sub_divisi VARCHAR(255),
    id_level VARCHAR(255),
    status VARCHAR(1) DEFAULT 'A'
);
```

### Relasi Hierarkis
- `id_atasan` merujuk ke `id_jabatan` lain dalam tabel yang sama
- Membentuk struktur tree/hierarki organisasi
- Root jabatan memiliki `id_atasan = NULL`

## Implementasi

### 1. Model Jabatan (`app/Models/Jabatan.php`)

#### Relationship Methods
```php
// Relationship untuk atasan
public function atasan()
{
    return $this->belongsTo(Jabatan::class, 'id_atasan', 'id_jabatan');
}

// Relationship untuk bawahan
public function bawahan()
{
    return $this->hasMany(Jabatan::class, 'id_atasan', 'id_jabatan');
}

// Relationship untuk users
public function users()
{
    return $this->hasMany(User::class, 'id_jabatan', 'id_jabatan');
}
```

#### Static Methods untuk Struktur Organisasi
```php
// Mendapatkan struktur organisasi per outlet
public static function getOrganizationStructure($outletId = null)

// Build tree structure
public static function buildOrganizationTree($outletId = null)

// Mendapatkan root jabatan
public static function getRootJabatans($outletId = null)
```

### 2. Controller (`app/Http/Controllers/OrganizationChartController.php`)

#### Endpoints
- `GET /organization-chart` - Halaman utama
- `GET /api/organization-chart` - Data organisasi dengan parameter outlet_id
- `GET /api/organization-chart/outlets` - Daftar outlet
- `GET /api/organization-chart/outlet/{outletId}` - Struktur organisasi per outlet
- `GET /api/organization-chart/debug` - Debug data

#### Methods
```php
// Mendapatkan data organisasi
public function getOrganizationData(Request $request)

// Mendapatkan daftar outlet
public function getOutlets()

// Mendapatkan struktur organisasi per outlet
public function getOrganizationByOutlet($outletId)

// Build complete tree structure
private function buildCompleteTree($jabatans, $outletId)
```

### 3. Frontend Components

#### Main Page (`resources/js/Pages/OrganizationChart/Index.vue`)
- Dropdown selector untuk outlet
- Toggle antara Tree View dan List View
- Loading dan error states
- Responsive design

#### Tree Node Component (`resources/js/Components/OrganizationTreeNode.vue`)
- Menampilkan jabatan dengan informasi lengkap
- Toggle untuk menampilkan/sembunyikan karyawan
- Toggle untuk menampilkan/sembunyikan bawahan
- Visual connection lines antar level

#### List Items Component (`resources/js/Components/OrganizationListItems.vue`)
- Menampilkan struktur dalam bentuk list
- Level indicator dengan visual dots
- Expandable/collapsible sections
- Employee cards dengan avatar

## API Response Format

### Struktur Organisasi Response
```json
{
    "success": true,
    "data": [
        {
            "id_jabatan": 1,
            "nama_jabatan": "CEO",
            "id_atasan": null,
            "level": {
                "id": 1,
                "nama_level": "Executive",
                "nilai_level": 10
            },
            "divisi": {
                "id": 1,
                "nama_divisi": "Management"
            },
            "employees": [
                {
                    "id": 1,
                    "nama_lengkap": "John Doe",
                    "avatar": "avatars/john.jpg",
                    "email": "john@company.com"
                }
            ],
            "employee_count": 1,
            "children": [
                {
                    "id_jabatan": 2,
                    "nama_jabatan": "Manager",
                    "id_atasan": 1,
                    "employees": [],
                    "employee_count": 0,
                    "children": []
                }
            ]
        }
    ],
    "outlet": {
        "id_outlet": 1,
        "nama_outlet": "Outlet Pusat",
        "lokasi": "Jakarta"
    }
}
```

## Fitur Utama

### 1. Struktur Per Outlet
- Setiap outlet memiliki struktur organisasi yang berbeda
- Filter karyawan berdasarkan outlet
- Informasi outlet ditampilkan di header

### 2. Hierarki Jabatan
- Tree structure berdasarkan relasi `id_atasan`
- Visual connection lines antar level
- Root jabatan (tidak memiliki atasan)

### 3. Informasi Karyawan
- Daftar karyawan per jabatan
- Avatar dan informasi kontak
- Count total karyawan per jabatan

### 4. View Modes
- **Tree View**: Hierarki visual dengan connection lines
- **List View**: Daftar terstruktur dengan level indicator

### 5. Interactive Features
- Expand/collapse untuk menampilkan karyawan
- Expand/collapse untuk menampilkan bawahan
- Responsive design untuk mobile dan desktop

## Permission System
- Middleware `permission:organization_chart` untuk akses
- Menu item di sidebar dengan permission check

## Routes
```php
// Web routes
Route::get('organization-chart', [OrganizationChartController::class, 'index']);

// API routes
Route::get('api/organization-chart', [OrganizationChartController::class, 'getOrganizationData']);
Route::get('api/organization-chart/outlets', [OrganizationChartController::class, 'getOutlets']);
Route::get('api/organization-chart/outlet/{outletId}', [OrganizationChartController::class, 'getOrganizationByOutlet']);
```

## Usage

### 1. Akses Menu
- Buka menu "Human Resource" > "Struktur Organisasi"
- Atau langsung ke `/organization-chart`

### 2. Pilih Outlet
- Gunakan dropdown untuk memilih outlet
- Struktur organisasi akan dimuat otomatis

### 3. Navigasi
- **Tree View**: Klik tombol untuk expand/collapse
- **List View**: Gunakan tombol untuk menampilkan karyawan/bawahan

### 4. Informasi Detail
- Hover pada kartu jabatan untuk informasi lengkap
- Klik tombol untuk melihat daftar karyawan
- Visual indicator untuk level hierarki

## Technical Notes

### Performance Considerations
- Lazy loading untuk data besar
- Caching untuk outlet data
- Optimized queries dengan eager loading

### Security
- Permission-based access control
- Input validation untuk outlet selection
- SQL injection protection

### Scalability
- Support untuk struktur organisasi kompleks
- Multiple outlet support
- Extensible untuk fitur tambahan

## Future Enhancements
1. Export to PDF/Excel
2. Print-friendly view
3. Search functionality
4. Drag & drop untuk reorganisasi
5. History tracking untuk perubahan struktur
6. Integration dengan HR system
7. Mobile app support
