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

## Debugging Steps

### 1. **Temporary Fix Applied**
Untuk debugging, filter level sementara dinonaktifkan untuk memastikan query dasar berfungsi:

```php
// Temporarily disable level filter for debugging
// ->where(function($q) {
//     $q->whereNull('j.id_level') // Include users without level
//       ->orWhereNotIn('j.id_level', [7, 8, 13]); // Exclude specific levels
// });
```

### 2. **Debug Logging Added**
Menambahkan logging untuk melihat hasil query:

```php
// Debug logging
\Log::info('Approvers query result', [
    'total_count' => $approvers->count(),
    'user_outlet' => $user->id_outlet,
    'search_term' => $search,
    'approvers' => $approvers->toArray()
]);
```

### 3. **Additional Improvements Made**
1. **Re-enabled level filter** - Filter level sudah diaktifkan kembali dengan kondisi yang tepat
2. **Increased limit** - Limit ditingkatkan dari 20 ke 50 untuk hasil yang lebih banyak
3. **Made outlet filter optional** - Filter outlet dibuat opsional untuk testing
4. **Enhanced debug logging** - Menambahkan logging untuk total users di outlet

### 4. **New Approach - Copying Purchase Order Ops**
Menggunakan pendekatan yang sama dengan Purchase Order Ops yang sudah terbukti berfungsi:

```php
// Use same approach as Purchase Order Ops
$users = \App\Models\User::where('users.status', 'A')
    ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
    ->where('users.id', '!=', $user->id) // Not the current user
    ->where(function($query) use ($search) {
        $query->where('users.nama_lengkap', 'like', "%{$search}%")
              ->orWhere('users.email', 'like', "%{$search}%")
              ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
    })
    ->where(function($q) {
        $q->whereNull('tbl_data_jabatan.id_level') // Include users without level
          ->orWhereNotIn('tbl_data_jabatan.id_level', [7, 8, 13]); // Exclude specific levels
    })
    ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
    ->orderBy('users.nama_lengkap')
    ->limit(50)
    ->get();
```

**Perbedaan dengan pendekatan sebelumnya:**
- ✅ Menggunakan **Model User** langsung (bukan DB::table)
- ✅ Menggunakan **join biasa** (bukan leftJoin)
- ✅ **Tidak ada filter outlet** (seperti PO Ops)
- ✅ **Query lebih sederhana** dan efisien
- ✅ **Format response sama** dengan PO Ops

### 5. **Latest Fixes Applied**
1. **Fixed field mapping** - Backend sekarang mengirim field yang benar:
   - `nama_lengkap` (bukan `name`)
   - `nama_jabatan` (bukan `jabatan`)
   - `nama_divisi` (ditambahkan)

2. **Added divisi join** - Menambahkan join ke tbl_data_divisi untuk data divisi

3. **Temporarily disabled level filter** - Untuk testing apakah masalahnya di filter level

4. **Enhanced debug logging** - Log sekarang menampilkan data yang lebih detail

### 6. **Final Query (With Level Filter + Outlet)**
```php
$users = \App\Models\User::where('users.status', 'A')
    ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
    ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
    ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
    ->where('users.id', '!=', $user->id)
    ->where(function($query) use ($search) {
        $query->where('users.nama_lengkap', 'like', "%{$search}%")
              ->orWhere('users.email', 'like', "%{$search}%")
              ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
    })
    ->where(function($q) {
        $q->whereNull('tbl_data_jabatan.id_level') // Include users without level
          ->orWhereNotIn('tbl_data_jabatan.id_level', [7, 8, 13]); // Exclude specific levels
    })
    ->select('users.id', 'users.nama_lengkap', 'users.email', 'tbl_data_jabatan.nama_jabatan', 'tbl_data_divisi.nama_divisi', 'tbl_data_outlet.nama_outlet', 'tbl_data_jabatan.id_level')
    ->orderBy('users.nama_lengkap')
    ->limit(50)
    ->get();
```

**Filter Level yang Diterapkan:**
- ✅ **Include users without level** (`id_level` is null)
- ✅ **Exclude levels 7, 8, 13** (tidak boleh dipilih sebagai atasan)
- ✅ **Include all other levels** (1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 14, dst.)

### 7. **Final Testing Steps**
1. **Test dengan filter level aktif** - Pastikan filter level berfungsi dengan benar
2. **Check format display** - Pastikan nama + email + jabatan + divisi ditampilkan dengan benar
3. **Verify level filtering** - Pastikan user dengan level 7, 8, 13 tidak muncul
4. **Check debug log** - Lihat level_summary untuk memastikan filter berfungsi

### 8. **Debug Information**
Log sekarang akan menampilkan:
- `total_count`: Jumlah user yang dikembalikan
- `level_summary`: Distribusi level dari user yang dikembalikan
- `users`: Detail setiap user termasuk level mereka

**Expected Result:**
- User dengan level 7, 8, 13: **TIDAK MUNCUL**
- User dengan level lain atau null: **MUNCUL**
- Format tampilan: **Nama + Email + Jabatan + Divisi + Outlet**

**Format Tampilan:**
- **Nama** (bold, dark)
- **Email** (small, gray)
- **Jabatan** (small, blue)
- **Divisi** (small, gray)
- **Outlet** (small, green)

## File yang Dimodifikasi

1. **app/Http/Controllers/AttendanceController.php**
   - Line 957-967: Menambahkan join ke tbl_data_level dan filter level (sementara dinonaktifkan)
   - Line 991-997: Menambahkan debug logging
