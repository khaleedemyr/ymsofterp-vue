# Area Data Insert Guide

## Overview
Dokumentasi untuk insert data area ke database Master Daily Report.

## Data Area yang Akan Diinsert

Berikut adalah 30 area yang akan diinsert ke database:

1. Parking Area
2. Neon Sign / Loly Pop
3. Security Area
4. Guest Waiting Area
5. Greeter Area
6. Cashier Area
7. Petty Cash
8. Pastry Show Case
9. Dine-in Area & VIP Room
10. AC, Air Curtain, Lighting & Sound System
11. Table & Chair/Sofa Set Up
12. CCTV
13. Wifi Connection
14. POS
15. CO Printer
16. Side Station Set Up
17. Equipment Set Up & Preparation (Condiments, Guest Supplies. etc)
18. Toilet/Washtafel
19. Sanitation & Hygiene
20. Mushola
21. Garden/Plantation
22. Employee Locker
23. Garbage Room Area
24. Janitor Area
25. Back Area (Steward)
26. Storage Area
27. FB Office
28. IPAL & Main Grease Trap
29. Media Promo (TV,Tend Card, Banner,Baligho, dll)
30. Employee Grooming (Service & Security)

## SQL Files

### 1. insert_area_data.sql
File SQL dengan kode area manual (tidak menggunakan auto generate).

### 2. insert_area_data_auto_generate.sql
File SQL yang menggunakan sistem auto generate kode area.

### 3. insert_area_data_safe.sql
File SQL yang aman dengan handle duplikasi kode.

### 4. insert_area_data_sequence.sql (Recommended)
File SQL dengan format kode sequence OPS001, OPS002, dst.

## Kode Area yang Dihasilkan (Format Sequence)

| No | Nama Area | Kode Area | Keterangan |
|----|-----------|-----------|------------|
| 1 | Parking Area | OPS001 | OPS + 001 |
| 2 | Neon Sign / Loly Pop | OPS002 | OPS + 002 |
| 3 | Security Area | OPS003 | OPS + 003 |
| 4 | Guest Waiting Area | OPS004 | OPS + 004 |
| 5 | Greeter Area | OPS005 | OPS + 005 |
| 6 | Cashier Area | OPS006 | OPS + 006 |
| 7 | Petty Cash | OPS007 | OPS + 007 |
| 8 | Pastry Show Case | OPS008 | OPS + 008 |
| 9 | Dine-in Area & VIP Room | OPS009 | OPS + 009 |
| 10 | AC, Air Curtain, Lighting & Sound System | OPS010 | OPS + 010 |
| 11 | Table & Chair/Sofa Set Up | OPS011 | OPS + 011 |
| 12 | CCTV | OPS012 | OPS + 012 |
| 13 | Wifi Connection | OPS013 | OPS + 013 |
| 14 | POS | OPS014 | OPS + 014 |
| 15 | CO Printer | OPS015 | OPS + 015 |
| 16 | Side Station Set Up | OPS016 | OPS + 016 |
| 17 | Equipment Set Up & Preparation | OPS017 | OPS + 017 |
| 18 | Toilet/Washtafel | OPS018 | OPS + 018 |
| 19 | Sanitation & Hygiene | OPS019 | OPS + 019 |
| 20 | Mushola | OPS020 | OPS + 020 |
| 21 | Garden/Plantation | OPS021 | OPS + 021 |
| 22 | Employee Locker | OPS022 | OPS + 022 |
| 23 | Garbage Room Area | OPS023 | OPS + 023 |
| 24 | Janitor Area | OPS024 | OPS + 024 |
| 25 | Back Area (Steward) | OPS025 | OPS + 025 |
| 26 | Storage Area | OPS026 | OPS + 026 |
| 27 | FB Office | OPS027 | OPS + 027 |
| 28 | IPAL & Main Grease Trap | OPS028 | OPS + 028 |
| 29 | Media Promo | OPS029 | OPS + 029 |
| 30 | Employee Grooming | OPS030 | OPS + 030 |

## Cara Penggunaan

### Option 1: Manual Insert (Tidak Recommended)
```sql
-- Jalankan file: database/sql/insert_area_data.sql
-- Kode area dibuat manual
```

### Option 2: Sequence Format (Recommended)
```sql
-- Jalankan file: database/sql/insert_area_data_sequence.sql
-- Kode area menggunakan format sequence OPS001, OPS002, dst
```

### Option 3: Auto Generate
```sql
-- Jalankan file: database/sql/insert_area_data_safe.sql
-- Kode area dibuat otomatis dengan handle duplikasi
```

## Keuntungan Format Sequence

Format sequence OPS001, OPS002, dst memiliki keuntungan:
- ✅ **Tidak ada duplikasi**: Setiap kode unik
- ✅ **Mudah diingat**: Format yang konsisten
- ✅ **Mudah diurutkan**: Otomatis terurut berdasarkan nomor
- ✅ **Scalable**: Bisa menambah area baru dengan mudah
- ✅ **User friendly**: Lebih mudah dipahami user

## Verifikasi Data

Setelah menjalankan SQL, verifikasi dengan query berikut:

```sql
-- Cek semua area yang telah diinsert
SELECT 
    a.id,
    a.nama_area,
    a.kode_area,
    d.nama_departemen,
    a.deskripsi,
    a.status
FROM areas a
JOIN departemens d ON a.departemen_id = d.id
WHERE a.departemen_id = 1
ORDER BY a.nama_area;

-- Cek duplikasi kode area
SELECT kode_area, COUNT(*) as jumlah
FROM areas
WHERE departemen_id = 1
GROUP BY kode_area
HAVING COUNT(*) > 1;
```

## Notes

1. **Departemen**: Semua area akan diassign ke departemen_id = 1 (Operations)
2. **Status**: Semua area dibuat dengan status 'A' (Aktif)
3. **Kode Format**: OPS + 3 digit nomor urut (001-030)
4. **Sequence**: Kode area berurutan dari OPS001 sampai OPS030
5. **Deskripsi**: Setiap area memiliki deskripsi yang sesuai

## Integration

Setelah data diinsert, area-area ini akan tersedia di:
- Master Daily Report → Area
- Form create/edit area
- Dropdown selection
- Report dan analisis
