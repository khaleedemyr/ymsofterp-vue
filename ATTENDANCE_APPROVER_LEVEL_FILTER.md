# Attendance Approver Level Filter Implementation

## Masalah yang Diatasi

User dengan level tertentu (id_level = 7, 8, 13) masih muncul di pilihan atasan pada modal pengajuan izin/cuti, padahal seharusnya tidak boleh dipilih sebagai atasan.

## Solusi yang Diterapkan

### 1. **Modifikasi Query di AttendanceController**

**File:** `app/Http/Controllers/AttendanceController.php`
**Fungsi:** `getApprovers()` (line 951-998)

**Perubahan:**
```php
// SEBELUM
$query = DB::table('users as u')
    ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
    ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
    ->where('u.id_outlet', $user->id_outlet) // Same outlet
    ->where('u.id', '!=', $user->id) // Not the current user
    ->where('u.status', 'A'); // Active users only

// SESUDAH
$query = DB::table('users as u')
    ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
    ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
    ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
    ->where('u.id_outlet', $user->id_outlet) // Same outlet
    ->where('u.id', '!=', $user->id) // Not the current user
    ->where('u.status', 'A') // Active users only
    ->whereNotIn('j.id_level', [7, 8, 13]); // Exclude specific levels
```

### 2. **Relasi Database yang Digunakan**

```
users
├── id_jabatan → tbl_data_jabatan.id_jabatan
│   └── id_level → tbl_data_level.id
└── division_id → tbl_data_divisi.id
```

**Filter yang Diterapkan:**
- `j.id_level NOT IN (7, 8, 13)` - Mengecualikan level 7, 8, dan 13

## Testing yang Diperlukan

### 1. **Test Case 1: User dengan Level yang Diizinkan**
- User dengan level selain 7, 8, 13
- Expected: Muncul di pilihan atasan

### 2. **Test Case 2: User dengan Level yang Dikecualikan**
- User dengan level 7, 8, atau 13
- Expected: Tidak muncul di pilihan atasan

### 3. **Test Case 3: Search Functionality**
- Cari user dengan level yang dikecualikan
- Expected: Tidak muncul dalam hasil pencarian

### 4. **Test Case 4: Same Outlet Filter**
- User dari outlet berbeda
- Expected: Tidak muncul di pilihan atasan (filter outlet tetap berlaku)

## Monitoring

Setelah deploy, monitor:
1. **Log Query** - Pastikan query menggunakan filter level yang benar
2. **User Experience** - Pastikan user dengan level 7, 8, 13 tidak muncul di pilihan
3. **Performance** - Pastikan join ke tbl_data_level tidak memperlambat query

## Catatan Penting

- Filter ini hanya berlaku untuk **pilihan atasan** di modal pengajuan izin/cuti
- Filter **tidak mempengaruhi** user lain yang menggunakan sistem
- Level yang dikecualikan: **7, 8, 13** (dapat disesuaikan jika diperlukan)
- Filter outlet dan status aktif tetap berlaku

## File yang Dimodifikasi

1. **app/Http/Controllers/AttendanceController.php**
   - Line 957-964: Menambahkan join ke tbl_data_level dan filter level
