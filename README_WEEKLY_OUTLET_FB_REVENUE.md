# Weekly Outlet FB Revenue Report

## Overview
Weekly Outlet FB Revenue Report adalah fitur untuk menampilkan laporan revenue outlet mingguan yang dikelompokkan per minggu dalam satu bulan dengan fitur budget bulanan.

## Fitur Utama

### 1. Filter Outlet
- **User dengan id_outlet = 1**: Dapat memilih outlet dari dropdown
- **User dengan id_outlet ≠ 1**: Otomatis menggunakan outlet yang sudah ditentukan

### 2. Filter Waktu
- **Bulan**: Pilihan bulan (Januari - Desember)
- **Tahun**: Pilihan tahun (tahun sekarang + 5 tahun ke belakang)

### 3. Budget Bulanan
- **Input Manual**: User dapat input budget bulanan langsung dari halaman report
- **Penyimpanan Database**: Budget disimpan di tabel `outlet_monthly_budgets`
- **MTD Performance**: Menghitung persentase pencapaian (Total MTD / Budget) * 100%

### 4. Data yang Ditampilkan
Untuk setiap hari, menampilkan:
- **WEEK**: Nomor minggu (1, 2, 3, 4)
- **DATE**: Tanggal dalam bulan
- **DAY**: Nama hari dalam seminggu
- **FB REVENUE**: Total grand_total dari orders
- **COVER**: Total pax dari orders
- **A/C**: Average Check (grand_total / pax)

### 5. Tampilan Khusus
- **Weekend**: Baris untuk Sabtu dan Minggu di-highlight dengan warna orange
- **Hari Libur**: Baris untuk hari libur nasional di-highlight dengan warna merah (dari tabel `tbl_kalender_perusahaan`)
- **Weekly Summary**: Summary per minggu dengan breakdown weekdays/weekends
- **MTD Summary**: Summary total bulan berjalan

### 6. Perhitungan Otomatis
- **Jumlah hari**: Total hari dalam bulan
- **Weekdays**: Hari kerja (tidak termasuk weekend dan hari libur)
- **Weekends**: Akhir pekan + hari libur
- **Day to Date**: Jumlah hari yang sudah berlalu dalam bulan
- **Weekdays to Date**: Jumlah hari kerja yang sudah berlalu
- **Weekends to Date**: Jumlah akhir pekan yang sudah berlalu

## Struktur Database

### Tabel Baru: outlet_monthly_budgets
```sql
CREATE TABLE IF NOT EXISTS `outlet_monthly_budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_qr_code` varchar(255) NOT NULL COMMENT 'QR Code outlet dari tbl_data_outlet',
  `month` int(2) NOT NULL COMMENT 'Bulan (1-12)',
  `year` int(4) NOT NULL COMMENT 'Tahun',
  `budget_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Budget bulanan dalam rupiah',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat budget',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_monthly_budgets_unique` (`outlet_qr_code`, `month`, `year`)
);
```

### Tabel Existing: tbl_kalender_perusahaan
- Digunakan untuk mendapatkan data hari libur nasional
- Field `tgl_libur` digunakan untuk highlight hari libur

## API Endpoints

### 1. GET /api/report/weekly-outlet-fb-revenue
**Parameters:**
- `month` (required): Bulan (1-12)
- `year` (required): Tahun
- `outlet` (optional): QR Code outlet (untuk superuser)

**Response:**
```json
{
  "weekly_data": {
    "1": [
      {
        "date": "2025-07-01",
        "day": "Selasa",
        "week": 1,
        "is_weekend": false,
        "is_holiday": false,
        "revenue": 30599400,
        "cover": 90,
        "avg_check": 339993
      }
    ]
  },
  "weekly_summaries": {
    "1": {
      "total_revenue": 150000000,
      "avg_revenue_per_day": 25000000,
      "weekdays_revenue": 120000000,
      "avg_weekdays_revenue": 24000000,
      "weekends_revenue": 30000000,
      "avg_weekends_revenue": 30000000
    }
  },
  "monthly_summary": {
    "total_revenue": 500000000,
    "total_cover": 1500,
    "weekdays_revenue": 400000000,
    "weekdays_cover": 1200,
    "weekends_revenue": 100000000,
    "weekends_cover": 300
  },
  "monthly_budget": 1355500000,
  "mtd_performance": 76.41,
  "day_counts": {
    "total_days": 31,
    "weekdays": 23,
    "weekends": 8,
    "days_to_date": 26,
    "weekdays_to_date": 19,
    "weekends_to_date": 7
  },
  "outlet_name": "SH KEMANG"
}
```

### 2. POST /api/report/weekly-outlet-fb-revenue/budget
**Parameters:**
- `outlet` (required): QR Code outlet
- `month` (required): Bulan (1-12)
- `year` (required): Tahun
- `budget_amount` (required): Jumlah budget

**Response:**
```json
{
  "success": true,
  "message": "Budget berhasil disimpan"
}
```

## File yang Dibuat/Dimodifikasi

### 1. Vue Component
- `resources/js/Pages/Report/ReportWeeklyOutletFbRevenue.vue`

### 2. Controller
- `app/Http/Controllers/ReportWeeklyOutletFbRevenueController.php`

### 3. Routes
- `routes/api.php`: Menambahkan route API
- `routes/web.php`: Menambahkan route halaman

### 4. Layout
- `resources/js/Layouts/AppLayout.vue`: Menambahkan menu ke sidebar

### 5. Database
- `create_monthly_budget_table.sql`: Query create table
- `insert_weekly_outlet_fb_revenue_menu.sql`: Query insert menu dan permission

## Cara Akses

### Via Web Browser
```
http://your-domain/report-weekly-outlet-fb-revenue
```

### Via API
```
GET /api/report/weekly-outlet-fb-revenue?month=7&year=2025&outlet=OUTLET_QR_CODE
POST /api/report/weekly-outlet-fb-revenue/budget
```

## Setup Database

### 1. Create Table Budget
```bash
mysql -u username -p database_name < create_monthly_budget_table.sql
```

### 2. Insert Menu dan Permission
```bash
mysql -u username -p database_name < insert_weekly_outlet_fb_revenue_menu.sql
```

## Dependencies
- Laravel 10+
- Vue 3
- Inertia.js
- Tailwind CSS
- Font Awesome (untuk icons)
- Carbon (untuk date manipulation)

## Catatan Teknis

### Query Database
- Menggunakan table `orders` untuk data revenue
- Menggunakan table `tbl_kalender_perusahaan` untuk hari libur
- Menggunakan table `outlet_monthly_budgets` untuk budget

### Perhitungan
- **A/C (Average Check)**: `round(grand_total / pax)`
- **MTD Performance**: `(total_revenue / monthly_budget) * 100`
- **Weekend**: Sabtu dan Minggu
- **Hari Libur**: Dari tabel `tbl_kalender_perusahaan`

### Styling
- Menggunakan styling yang sama dengan `ReportDailyOutletRevenue.vue`
- Responsive design dengan Tailwind CSS
- Highlight weekend dengan warna orange
- Highlight hari libur dengan warna merah
- Header dengan gradient blue 