# Master Daily Report Menu Setup

## Overview
Dokumentasi untuk menambahkan menu Master Daily Report ke sistem permission management.

## Database Tables

### 1. erp_menu
Tabel untuk menyimpan data menu sistem.

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | varchar | Nama menu |
| code | varchar | Kode unik menu |
| parent_id | int | ID parent menu (untuk submenu) |
| route | varchar | Route/URL menu |
| icon | varchar | Icon FontAwesome |
| created_at | datetime | Tanggal dibuat |
| updated_at | datetime | Tanggal diupdate |

### 2. erp_permission
Tabel untuk menyimpan permission/izin akses menu.

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| menu_id | int | Foreign key ke erp_menu |
| action | enum | Tipe aksi ('view','create','update','delete') |
| code | varchar | Kode permission |
| created_at | datetime | Tanggal dibuat |
| updated_at | datetime | Tanggal diupdate |

## SQL Files

### 1. insert_master_daily_report_menu.sql
File SQL untuk insert menu Master Daily Report saja (tanpa parent group).

**Isi:**
- Insert menu Master Daily Report
- Insert 4 permissions (view, create, update, delete)

### 2. insert_ops_management_menu.sql
File SQL untuk insert menu group Ops Management dan Master Daily Report.

**Isi:**
- Insert menu group "Ops Management"
- Insert menu "Master Daily Report" dengan parent_id ke Ops Management
- Insert permissions untuk Master Daily Report
- Insert permission untuk Ops Management group

## Cara Penggunaan

### Option 1: Menu Standalone
```sql
-- Jalankan file: database/sql/insert_master_daily_report_menu.sql
-- Menu akan ditambahkan tanpa parent group
```

### Option 2: Menu dengan Group (Recommended)
```sql
-- Jalankan file: database/sql/insert_ops_management_menu.sql
-- Menu akan ditambahkan dengan parent group "Ops Management"
```

## Permissions yang Dibuat

### Master Daily Report
- `master_report_view` - Izin untuk melihat data
- `master_report_create` - Izin untuk membuat data baru
- `master_report_update` - Izin untuk mengupdate data
- `master_report_delete` - Izin untuk menghapus data

### Ops Management (jika menggunakan option 2)
- `ops_management_view` - Izin untuk mengakses group menu

## Verifikasi

Setelah menjalankan SQL, verifikasi dengan query berikut:

```sql
-- Cek menu yang telah dibuat
SELECT * FROM erp_menu WHERE code IN ('ops_management', 'master_report');

-- Cek permissions yang telah dibuat
SELECT p.*, m.name as menu_name 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE m.code IN ('ops_management', 'master_report');
```

## Notes

1. **Parent ID**: Jika menggunakan option 2, Master Daily Report akan memiliki parent_id ke Ops Management
2. **Route**: Master Daily Report menggunakan route `/master-report`
3. **Icon**: Menggunakan FontAwesome icons (`fa-solid fa-cogs` untuk group, `fa-solid fa-chart-line` untuk menu)
4. **Code**: Menggunakan kode yang sama dengan yang ada di AppLayout.vue untuk konsistensi

## Integration dengan User Roles

Setelah menu dan permission dibuat, admin perlu:
1. Assign permissions ke role yang sesuai
2. Assign role ke user yang membutuhkan akses
3. Pastikan user memiliki permission `master_report_view` minimal untuk mengakses menu
