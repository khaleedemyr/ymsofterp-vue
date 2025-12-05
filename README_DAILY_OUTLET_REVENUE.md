# Daily Outlet Revenue Report

## Overview
Daily Outlet Revenue Report adalah fitur untuk menampilkan laporan revenue outlet harian yang memisahkan data lunch dan dinner berdasarkan waktu order.

## Fitur Utama

### 1. Filter Outlet
- **User dengan id_outlet = 1**: Dapat memilih outlet dari dropdown
- **User dengan id_outlet ≠ 1**: Otomatis menggunakan outlet yang sudah ditentukan

### 2. Filter Waktu
- **Bulan**: Pilihan bulan (Januari - Desember)
- **Tahun**: Pilihan tahun (tahun sekarang + 5 tahun ke belakang)

### 3. Kategorisasi Order
- **Lunch**: Order dengan waktu created_at ≤ 17:00
- **Dinner**: Order dengan waktu created_at > 17:00

### 4. Data yang Ditampilkan
Untuk setiap periode (Lunch/Dinner/Total), menampilkan:
- **COVER**: Jumlah pax (dari field pax)
- **REVENUE**: Total grand_total
- **A/C**: Average Check (grand_total / pax)
- **DISC**: Total discount

### 5. Tampilan Khusus
- **Weekend**: Baris untuk Sabtu dan Minggu di-highlight dengan warna orange
- **Month to Date**: Summary total untuk seluruh bulan
- **Format Angka**: Menggunakan separator ribuan (contoh: 1,234,567)

## Struktur Data

### API Response
```json
{
  "daily_data": {
    "2025-07-01": {
      "day_name": "Selasa",
      "lunch": {
        "cover": 45,
        "revenue": 11447900,
        "avg_check": 254398,
        "disc": 271150
      },
      "dinner": {
        "cover": 67,
        "revenue": 18923400,
        "avg_check": 282439,
        "disc": 345600
      },
      "total": {
        "cover": 112,
        "revenue": 30371300,
        "avg_check": 271172,
        "disc": 616750
      }
    }
  },
  "summary": {
    "lunch": {
      "cover": 1342,
      "revenue": 442930400,
      "avg_check": 330052,
      "disc": 7303800
    },
    "dinner": {
      "cover": 1827,
      "revenue": 592793100,
      "avg_check": 324463,
      "disc": 11522600
    },
    "total": {
      "cover": 3169,
      "revenue": 1035723500,
      "avg_check": 326830,
      "disc": 18826400
    }
  }
}
```

## File yang Dibuat/Dimodifikasi

### 1. Vue Component
- `resources/js/Pages/Report/ReportDailyOutletRevenue.vue`

### 2. Controller
- `app/Http/Controllers/ReportDailyOutletRevenueController.php`

### 3. Routes
- `routes/api.php`: Menambahkan route API `/api/report/daily-outlet-revenue`
- `routes/web.php`: Menambahkan route halaman `/report-daily-outlet-revenue`

## Cara Akses

### Via Web Browser
```
http://your-domain/report-daily-outlet-revenue
```

### Via API
```
GET /api/report/daily-outlet-revenue?month=7&year=2025&outlet=OUTLET_QR_CODE
```

## Dependencies
- Laravel 10+
- Vue 3
- Inertia.js
- Tailwind CSS
- Font Awesome (untuk icons)

## Catatan Teknis

### Query Database
- Menggunakan table `orders`
- Filter berdasarkan `qr_code`, `created_at`, dan `status`
- Hanya mengambil order dengan `grand_total > 0`
- Mengabaikan order dengan status `cancelled`

### Perhitungan
- **A/C (Average Check)**: `round(grand_total / pax)`
- **Cover**: Sum dari field `pax`
- **Revenue**: Sum dari field `grand_total`
- **DISC**: Sum dari field `discount`

### Styling
- Menggunakan styling yang sama dengan `ReportSalesSimple.vue`
- Responsive design dengan Tailwind CSS
- Highlight weekend dengan warna orange
- Header dengan gradient blue 