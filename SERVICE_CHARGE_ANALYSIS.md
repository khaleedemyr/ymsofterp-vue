# Analisa Service Charge = 0

## Penyebab Service Charge Menjadi 0

Berdasarkan kode di `PayrollReportController.php`, service charge hanya dihitung jika **KEDUA kondisi berikut terpenuhi**:

### Kondisi 1: `$masterData->sc == 1`
- User harus memiliki flag `sc = 1` di tabel `payroll_master`
- Jika user tidak ada di `payroll_master` atau `sc = 0`, maka service charge = 0
- Default value jika user tidak ada di payroll_master: `sc = 0` (lihat baris 108)

### Kondisi 2: `$serviceCharge > 0`
- Input service charge di form harus lebih dari 0
- Jika input service charge = 0 atau kosong, maka service charge = 0

## Logika Perhitungan (Baris 303-312)

```php
if ($masterData->sc == 1 && $serviceCharge > 0) {
    // Service charge by point = rate × (point × hari kerja)
    $serviceChargeByPointAmount = $rateByPoint * ($userPoint * $hariKerja);
    
    // Service charge pro rate = rate × hari kerja
    $serviceChargeProRateAmount = $rateProRate * $hariKerja;
    
    // Total service charge per user
    $serviceChargeTotal = $serviceChargeByPointAmount + $serviceChargeProRateAmount;
}
```

## Kemungkinan Penyebab Service Charge = 0

1. **User tidak memiliki record di `payroll_master`**
   - Solusi: Pastikan semua user yang perlu service charge memiliki record di `payroll_master` dengan `sc = 1`

2. **User memiliki `sc = 0` di `payroll_master`**
   - Solusi: Update `sc = 1` untuk user yang seharusnya mendapatkan service charge

3. **Input service charge = 0 atau kosong**
   - Solusi: Pastikan input service charge di form diisi dengan nilai > 0

4. **User tidak memiliki hari kerja (`hari_kerja = 0`)**
   - Meskipun `sc = 1` dan `serviceCharge > 0`, jika `hari_kerja = 0`, maka service charge akan 0
   - Solusi: Pastikan user memiliki attendance data yang valid

5. **User tidak memiliki point (`userPoint = 0`)**
   - Untuk service charge by point, jika point = 0, maka bagian by point akan 0
   - Tapi service charge pro rate masih bisa dihitung jika ada hari kerja

## Query untuk Cek Data

```sql
-- Cek user yang tidak ada di payroll_master
SELECT u.id, u.nik, u.nama_lengkap, u.id_outlet
FROM users u
WHERE u.status = 'A'
  AND u.id_outlet = [OUTLET_ID]
  AND u.id NOT IN (SELECT user_id FROM payroll_master WHERE outlet_id = [OUTLET_ID]);

-- Cek user yang sc = 0
SELECT pm.user_id, u.nik, u.nama_lengkap, pm.sc
FROM payroll_master pm
JOIN users u ON pm.user_id = u.id
WHERE pm.outlet_id = [OUTLET_ID]
  AND pm.sc = 0
  AND u.status = 'A';

-- Cek user yang sc = 1 (seharusnya dapat service charge)
SELECT pm.user_id, u.nik, u.nama_lengkap, pm.sc
FROM payroll_master pm
JOIN users u ON pm.user_id = u.id
WHERE pm.outlet_id = [OUTLET_ID]
  AND pm.sc = 1
  AND u.status = 'A';
```

## Rekomendasi

1. **Tambahkan logging** untuk debugging service charge calculation
2. **Validasi** sebelum generate payroll untuk memastikan semua user yang perlu service charge memiliki `sc = 1`
3. **Tampilkan warning** di frontend jika ada user dengan `sc = 0` atau tidak ada di payroll_master
