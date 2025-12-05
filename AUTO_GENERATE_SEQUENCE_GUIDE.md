# Auto Generate Sequence Guide

## Overview
Fitur auto generate kode area telah diupdate untuk mengikuti sequence data terakhir. Sistem akan otomatis melanjutkan sequence dari kode area terakhir yang ada di database.

## Cara Kerja Sequence

### 1. Format Kode Area
```
OPS001, OPS002, OPS003, ..., OPS999
```

### 2. Algoritma Sequence
1. **Cari kode terakhir** berdasarkan departemen yang dipilih
2. **Extract nomor** dari kode terakhir (misal: OPS057 → 57)
3. **Increment nomor** (57 + 1 = 58)
4. **Format kode baru** (OPS058)
5. **Validasi unik** untuk memastikan tidak ada duplikasi

### 3. Contoh Generate Kode
- **Data terakhir**: OPS057 (Employee Locker - Kitchen)
- **Area baru**: "New Area" → **Kode**: OPS058
- **Area baru lagi**: "Another Area" → **Kode**: OPS059

## Perubahan yang Dibuat

### 1. Controller (MasterReportController.php)
- **Method `generateKodeArea()`**: Diupdate untuk menggunakan sequence
- **Method `getNextAreaCode()`**: Method baru untuk preview kode
- **Route baru**: `/master-report/next-area-code`

### 2. Form Modal (MasterReportFormModal.vue)
- **Preview kode area**: Menampilkan kode yang akan dibuat
- **Auto fetch**: Otomatis fetch kode saat pilih departemen
- **Loading state**: Menampilkan loading saat fetch kode
- **Real-time update**: Kode berubah saat ganti departemen

## Fitur Baru

### 1. Preview Kode Area
- User bisa melihat kode yang akan dibuat sebelum submit
- Kode ditampilkan dalam box preview yang menarik
- Format: `OPS058` dengan styling khusus

### 2. Auto Fetch Kode
- Saat user pilih departemen, sistem otomatis fetch kode berikutnya
- Tidak perlu manual refresh atau submit
- Real-time update

### 3. Loading State
- Menampilkan loading spinner saat fetch kode
- User experience yang lebih baik
- Feedback visual yang jelas

## Algoritma Generate Kode

```php
private function generateKodeArea($namaArea, $departemenId, $excludeId = null)
{
    // 1. Cari kode area terakhir berdasarkan departemen
    $lastArea = Area::where('departemen_id', $departemenId)
        ->orderBy('kode_area', 'desc')
        ->first();
    
    // 2. Generate nomor urut berikutnya
    $nextNumber = 1;
    if ($lastArea && preg_match('/OPS(\d{3})$/', $lastArea->kode_area, $matches)) {
        $nextNumber = (int)$matches[1] + 1;
    }
    
    // 3. Format kode dengan 3 digit
    $finalKode = 'OPS' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    
    // 4. Pastikan kode unik
    while (Area::where('kode_area', $finalKode)->exists()) {
        $nextNumber++;
        $finalKode = 'OPS' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
    
    return $finalKode;
}
```

## Contoh Penggunaan

### 1. User Pilih Departemen
- User buka form create area
- User pilih departemen "Kitchen"
- Sistem otomatis fetch kode berikutnya (misal: OPS058)
- Preview kode ditampilkan: `OPS058`

### 2. User Input Data
- User isi nama area: "New Kitchen Area"
- User isi deskripsi: "Area kitchen baru"
- User klik submit
- Sistem create area dengan kode OPS058

### 3. Area Baru Berikutnya
- User buat area baru lagi
- Sistem otomatis generate OPS059
- Dan seterusnya...

## Keuntungan

1. **Sequence Konsisten**: Kode selalu berurutan tanpa gap
2. **User Friendly**: User bisa lihat preview kode
3. **Real-time**: Kode update otomatis saat pilih departemen
4. **Tidak Ada Duplikasi**: Sistem memastikan kode unik
5. **Scalable**: Bisa handle banyak area tanpa masalah
6. **Visual Feedback**: Loading state dan preview yang jelas

## API Endpoint

### GET /master-report/next-area-code
**Parameters:**
- `departemen_id` (required): ID departemen

**Response:**
```json
{
    "success": true,
    "next_code": "OPS058"
}
```

## Notes

- Kode area menggunakan format OPS + 3 digit (OPS001 - OPS999)
- Sequence dimulai dari 1 dan increment otomatis
- Sistem handle duplikasi dengan increment nomor
- Preview kode hanya muncul saat create area baru
- Edit area tidak mengubah kode (kecuali nama/departemen berubah)
