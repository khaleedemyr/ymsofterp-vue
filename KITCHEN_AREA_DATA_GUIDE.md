# Kitchen Area Data Insert Guide

## Overview
Dokumentasi untuk insert data area Kitchen ke database Master Daily Report.

## Data Area Kitchen yang Akan Diinsert

Berikut adalah 27 area Kitchen yang akan diinsert ke database:

1. Kitchen Fuel
2. Chiller & Freezer Temperature
3. Stove Burner
4. Kwali range
5. Microwave
6. Griller
7. Fryer
8. Bain Marie/ Warmer
9. Oven
10. Sous Vide Equipment
11. Vacuum Sealing
12. Sink
13. Kitchen Hood & Ducting
14. Kitchen Fresh Air
15. Kitchen Lighting
16. Steward Lighting
17. Dishwashing Machine
18. Water Supply
19. APAR
20. Main Grease trap
21. Gutter
22. IPAL
23. FIFO / FEFO Check
24. Commodity Labeling
25. Storage Area
26. Employee Grooming (Kitchen & Steward)
27. Employee Locker

## SQL Files

### 1. insert_area_data_kitchen.sql
File SQL untuk insert area Kitchen saja (OPS031 - OPS057).

### 2. insert_all_areas_complete.sql (Recommended)
File SQL untuk insert semua area (Operations + Kitchen) dengan sequence lengkap.

## Kode Area yang Dihasilkan (Kitchen)

| No | Nama Area | Kode Area | Departemen |
|----|-----------|-----------|------------|
| 1 | Kitchen Fuel | OPS031 | Kitchen |
| 2 | Chiller & Freezer Temperature | OPS032 | Kitchen |
| 3 | Stove Burner | OPS033 | Kitchen |
| 4 | Kwali range | OPS034 | Kitchen |
| 5 | Microwave | OPS035 | Kitchen |
| 6 | Griller | OPS036 | Kitchen |
| 7 | Fryer | OPS037 | Kitchen |
| 8 | Bain Marie/ Warmer | OPS038 | Kitchen |
| 9 | Oven | OPS039 | Kitchen |
| 10 | Sous Vide Equipment | OPS040 | Kitchen |
| 11 | Vacuum Sealing | OPS041 | Kitchen |
| 12 | Sink | OPS042 | Kitchen |
| 13 | Kitchen Hood & Ducting | OPS043 | Kitchen |
| 14 | Kitchen Fresh Air | OPS044 | Kitchen |
| 15 | Kitchen Lighting | OPS045 | Kitchen |
| 16 | Steward Lighting | OPS046 | Kitchen |
| 17 | Dishwashing Machine | OPS047 | Kitchen |
| 18 | Water Supply | OPS048 | Kitchen |
| 19 | APAR | OPS049 | Kitchen |
| 20 | Main Grease trap | OPS050 | Kitchen |
| 21 | Gutter | OPS051 | Kitchen |
| 22 | IPAL | OPS052 | Kitchen |
| 23 | FIFO / FEFO Check | OPS053 | Kitchen |
| 24 | Commodity Labeling | OPS054 | Kitchen |
| 25 | Storage Area | OPS055 | Kitchen |
| 26 | Employee Grooming (Kitchen & Steward) | OPS056 | Kitchen |
| 27 | Employee Locker | OPS057 | Kitchen |

## Sequence Lengkap (Operations + Kitchen)

### Operations Areas (OPS001 - OPS030)
- OPS001 - OPS030: 30 area Operations

### Kitchen Areas (OPS031 - OPS057)  
- OPS031 - OPS057: 27 area Kitchen

**Total: 57 area dengan sequence OPS001 - OPS057**

## Cara Penggunaan

### Option 1: Insert Kitchen Area Saja
```sql
-- Jalankan file: database/sql/insert_area_data_kitchen.sql
-- Hanya insert area Kitchen (OPS031 - OPS057)
```

### Option 2: Insert Semua Area (Recommended)
```sql
-- Jalankan file: database/sql/insert_all_areas_complete.sql
-- Insert semua area (Operations + Kitchen) dengan sequence lengkap
```

## Verifikasi Data

Setelah menjalankan SQL, verifikasi dengan query berikut:

```sql
-- Cek semua area Kitchen
SELECT 
    a.id,
    a.nama_area,
    a.kode_area,
    d.nama_departemen,
    a.deskripsi,
    a.status
FROM areas a
JOIN departemens d ON a.departemen_id = d.id
WHERE a.departemen_id = 2
ORDER BY a.kode_area;

-- Cek semua area (Operations + Kitchen)
SELECT 
    a.id,
    a.nama_area,
    a.kode_area,
    d.nama_departemen,
    a.deskripsi,
    a.status
FROM areas a
JOIN departemens d ON a.departemen_id = d.id
ORDER BY a.kode_area;

-- Cek duplikasi kode area
SELECT kode_area, COUNT(*) as jumlah
FROM areas
GROUP BY kode_area
HAVING COUNT(*) > 1;
```

## Notes

1. **Departemen**: Area Kitchen diassign ke departemen_id = 2 (Kitchen)
2. **Status**: Semua area dibuat dengan status 'A' (Aktif)
3. **Kode Format**: OPS + 3 digit nomor urut (031-057)
4. **Sequence**: Kode area berurutan dari OPS031 sampai OPS057
5. **Deskripsi**: Setiap area memiliki deskripsi yang sesuai
6. **Lanjutan**: Sequence melanjutkan dari Operations (OPS001-OPS030)

## Integration

Setelah data diinsert, area-area Kitchen ini akan tersedia di:
- Master Daily Report → Area
- Form create/edit area
- Dropdown selection
- Report dan analisis
- Filter berdasarkan departemen Kitchen
