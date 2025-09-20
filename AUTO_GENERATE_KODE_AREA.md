# Auto Generate Kode Area

## Overview
Fitur auto generate kode area untuk Master Daily Report telah diimplementasikan. Kode area akan dibuat otomatis berdasarkan nama area dan departemen yang dipilih.

## Cara Kerja

### 1. Format Kode Area
```
[Kode Departemen][3 huruf pertama nama area]
```

### 2. Contoh Generate Kode
- **Departemen**: HR (Human Resources)
- **Area**: Production
- **Kode Area**: HRPRO

- **Departemen**: FIN (Finance)  
- **Area**: Accounting
- **Kode Area**: FINACC

### 3. Handle Duplikasi
Jika kode sudah ada, sistem akan menambahkan nomor urut:
- HRPRO (sudah ada)
- HRPRO01 (kode baru)
- HRPRO02 (jika HRPRO01 juga ada)

## Perubahan yang Dibuat

### 1. Controller (MasterReportController.php)
- **Method `store()`**: Menghapus validasi kode_area, menambahkan auto generate
- **Method `update()`**: Auto generate kode baru jika nama area atau departemen berubah
- **Method `generateKodeArea()`**: Method baru untuk generate kode otomatis

### 2. Form Modal (MasterReportFormModal.vue)
- **Menghapus field kode_area** dari form input
- **Menambahkan info box** yang menjelaskan bahwa kode dibuat otomatis
- **Update form data** untuk tidak include kode_area

## Algoritma Generate Kode

```php
private function generateKodeArea($namaArea, $departemenId, $excludeId = null)
{
    // 1. Ambil kode departemen (3 karakter pertama)
    $kodeDepartemen = strtoupper(substr($departemen->kode_departemen, 0, 3));
    
    // 2. Generate kode dari nama area (3 karakter pertama, hapus special char)
    $kodeArea = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $namaArea), 0, 3));
    
    // 3. Jika kurang dari 3 karakter, pad dengan 'X'
    if (strlen($kodeArea) < 3) {
        $kodeArea = str_pad($kodeArea, 3, 'X');
    }
    
    // 4. Gabungkan kode departemen + kode area
    $baseKode = $kodeDepartemen . $kodeArea;
    
    // 5. Cek duplikasi dan tambahkan nomor urut jika perlu
    $counter = 1;
    $finalKode = $baseKode;
    
    do {
        $exists = Area::where('kode_area', $finalKode);
        if ($excludeId) {
            $exists->where('id', '!=', $excludeId);
        }
        $exists = $exists->exists();
        
        if ($exists) {
            $finalKode = $baseKode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }
    } while ($exists && $counter <= 99);
    
    return $finalKode;
}
```

## Contoh Hasil Generate

| Departemen | Kode Dept | Area | Kode Area Generated |
|------------|-----------|------|-------------------|
| Human Resources | HR | Production | HRPRO |
| Human Resources | HR | Training & Development | HRTRA |
| Finance | FIN | Accounting | FINACC |
| Finance | FIN | Payroll | FINPAY |
| Operations | OPS | Quality Control | OPSQUA |
| Operations | OPS | Production | OPSPRO |

## Keuntungan

1. **Konsistensi**: Kode area selalu mengikuti format yang sama
2. **Unik**: Sistem memastikan tidak ada duplikasi kode
3. **User Friendly**: User tidak perlu memikirkan kode area
4. **Maintainable**: Mudah dipahami dan di-maintain
5. **Scalable**: Bisa handle banyak area tanpa konflik

## Notes

- Kode area maksimal 8 karakter (3 departemen + 3 area + 2 nomor urut)
- Sistem akan otomatis handle duplikasi dengan menambahkan nomor urut
- Jika nama area berubah saat edit, kode area akan di-generate ulang
- Jika departemen berubah saat edit, kode area akan di-generate ulang
