# Payroll Mutation Split Implementation

## Tanggal
29 Januari 2026

## Problem Statement
Ketika karyawan mutasi di tengah periode payroll, karyawan tersebut harus muncul di **DUA** outlet:
1. **Outlet LAMA** - dengan data proporsi sebelum mutasi
2. **Outlet BARU** - dengan data proporsi setelah mutasi

## Current Issue - Case Noviya Deva
- **Mutasi**: 26 Desember 2025 ke Justus Steak House Miko Mall
- **Periode Des 2025** (26 Nov - 25 Des): Muncul di outlet lama ✅ BENAR
- **Periode Jan 2026** (26 Des - 25 Jan): MASIH muncul di outlet lama ❌ SALAH
  - Seharusnya: TIDAK muncul di outlet lama (0 hari kerja)
  - Seharusnya: Muncul di outlet baru (full 31 hari)

## Solution Requirements

### 1. Query Mutations - FIXED ✅
**File**: `PayrollReportController.php` line ~166

**OLD**:
```php
->where('employment_effective_date', '>=', $start) // Salah!
```

**NEW**:
```php
->where('employment_effective_date', '>', $start)  // Benar!
->where('employment_effective_date', '<=', $end)
```

**Logic**:
- Karyawan mutasi TEPAT di start periode (26 Des) → TIDAK muncul di outlet lama
- Karyawan mutasi SETELAH start periode → Muncul di outlet lama (proporsi)

### 2. Split Data Components

#### A. Hari Kerja (Attendance)
- **Outlet LAMA**: Count attendance WHERE `tanggal < effective_date`
- **Outlet BARU**: Count attendance WHERE `tanggal >= effective_date`

#### B. Gaji Pokok & Tunjangan (Proporsi Hari Kerja) - USER PILIH OPSI A
```
Total Gaji = gaji_pokok + tunjangan
Proporsi Outlet Lama = (hari_kerja_outlet_lama / total_hari_kerja)
Proporsi Outlet Baru = (hari_kerja_outlet_baru / total_hari_kerja)

Gaji Outlet Lama = Total Gaji × Proporsi Outlet Lama
Gaji Outlet Baru = Total Gaji × Proporsi Outlet Baru
```

**Example**:
- Gaji: 5,000,000
- Tunjangan: 1,000,000
- Total: 6,000,000
- Hari kerja outlet lama: 10 hari
- Hari kerja outlet baru: 20 hari
- Total hari kerja: 30 hari

**Result**:
- Outlet lama: 6,000,000 × (10/30) = 2,000,000
- Outlet baru: 6,000,000 × (20/30) = 4,000,000

#### C. Service Charge (Proporsi Hari Kerja) - USER PILIH OPSI B
```
Total SC = Sum semua SC dalam periode
Proporsi Outlet Lama = (hari_kerja_outlet_lama / total_hari_kerja)
Proporsi Outlet Baru = (hari_kerja_outlet_baru / total_hari_kerja)

SC Outlet Lama = Total SC × Proporsi Outlet Lama
SC Outlet Baru = Total SC × Proporsi Outlet Baru
```

**Note**: BUKAN split by transaction date, tapi by proporsi hari kerja

#### D. Lembur & Uang Makan
- **Outlet LAMA**: Sum dari attendance sebelum `effective_date`
- **Outlet BARU**: Sum dari attendance setelah `effective_date`

#### E. BPJS & Potongan - USER PILIH OUTLET LAMA
- **BPJS JKN**: Masuk ke outlet LAMA (100%)
- **BPJS TK**: Masuk ke outlet LAMA (100%)
- **Potongan Telat**: Split by actual telat per outlet

#### F. L&B, Deviasi, City Ledger (Proporsi Hari Kerja)
Same as Service Charge - split by proporsi hari kerja

### 3. Payroll Entry Structure

Karyawan mutasi akan generate **2 ENTRIES**:

**Entry 1 - Outlet LAMA**:
```php
[
    'user_id' => 123,
    'nama_lengkap' => 'Noviya Deva',
    'outlet_id' => 5, // Outlet LAMA
    'hari_kerja' => 10, // Hari kerja sebelum mutasi
    'gaji_pokok' => 2_000_000, // Proporsi
    'tunjangan' => 667_000, // Proporsi
    'service_charge' => 500_000, // Proporsi
    'lembur' => 200_000, // Actual dari attendance
    'uang_makan' => 100_000, // Actual dari attendance
    'bpjs_jkn' => 50_000, // FULL (100%)
    'bpjs_tk' => 100_000, // FULL (100%)
    'potongan_telat' => 50_000, // Actual dari attendance
    'is_mutated_employee' => true,
    'mutation_note' => 'Mutasi ke Justus Steak House (26/12/2025)'
]
```

**Entry 2 - Outlet BARU**:
```php
[
    'user_id' => 123,
    'nama_lengkap' => 'Noviya Deva',
    'outlet_id' => 10, // Outlet BARU (Justus)
    'hari_kerja' => 20, // Hari kerja setelah mutasi
    'gaji_pokok' => 4_000_000, // Proporsi
    'tunjangan' => 1_333_000, // Proporsi
    'service_charge' => 1_000_000, // Proporsi
    'lembur' => 400_000, // Actual dari attendance
    'uang_makan' => 200_000, // Actual dari attendance
    'bpjs_jkn' => 0, // Sudah masuk ke outlet lama
    'bpjs_tk' => 0, // Sudah masuk ke outlet lama
    'potongan_telat' => 100_000, // Actual dari attendance
    'is_mutated_employee' => true,
    'mutation_note' => 'Mutasi dari Outlet Lama (26/12/2025)'
]
```

## Implementation Plan

### Phase 1: Detection & Mapping ✅ DONE
1. ✅ Fix mutation query (line ~166)
2. ✅ Create `$mutationMap` with full data
3. ✅ Add mutation detection in user processing loop
4. ✅ Split attendance into `$attendanceBeforeMutation` and `$attendanceAfterMutation`
5. ✅ Add mutation data to `$userData`
6. ✅ **CRITICAL FIX**: SC/LB/Deviasi/City Ledger pool calculation use TOTAL hari kerja for mutated employees (line ~1162-1268)

### Phase 2: Helper Function (NEXT)
Create `processMutatedEmployeePayroll()` function yang return array of 2 payroll entries.

**Location**: PayrollReportController.php (add as private method)

**Signature**:
```php
private function processMutatedEmployeePayroll(
    $userData, 
    $serviceChargeByPoint, 
    $serviceChargeProRate, 
    $lbByPoint, 
    $deviasiByPoint, 
    $cityLedgerByPoint,
    $customPayrollItems,
    $startDate,
    $endDate,
    $year,
    $month
) {
    // Return array of 2 payroll entries
    return [$payrollEntryOutletLama, $payrollEntryOutletBaru];
}
```

### Phase 3: Integration
In Step 4 (line ~1247), check if `$isMutatedEmployee`:
```php
if ($isMutatedEmployee) {
    // Call helper function
    $mutatedEntries = $this->processMutatedEmployeePayroll(...);
    foreach ($mutatedEntries as $entry) {
        $payrollData->push((object)$entry);
    }
    continue; // Skip normal processing
}
// ... normal processing for non-mutated employees
```

## Edge Cases

### Case 1: Mutasi Tepat di Start Periode
**Example**: Periode Jan (26 Des - 25 Jan), mutasi 26 Des
- Hari kerja outlet lama: 0
- Hari kerja outlet baru: Full periode
- **Result**: Hanya muncul di outlet BARU

### Case 2: Mutasi Tepat di End Periode
**Example**: Periode Jan (26 Des - 25 Jan), mutasi 25 Jan
- Hari kerja outlet lama: Full periode
- Hari kerja outlet baru: 0
- **Result**: Hanya muncul di outlet LAMA (outlet baru muncul di periode next)

### Case 3: Karyawan Baru + Mutasi
**Example**: Masuk 1 Jan, mutasi 15 Jan
- Tanggal masuk: 1 Jan
- Mutasi: 15 Jan
- Hari kerja outlet lama: 15 hari (1-14 Jan)
- Hari kerja outlet baru: 16 hari (15-30 Jan)
- Gaji: Pro-rate dari tanggal masuk, lalu split by proporsi

### Case 4: Resign + Mutasi
**Example**: Mutasi 10 Jan, resign 20 Jan
- Mutasi: 10 Jan
- Resign: 20 Jan
- Hari kerja outlet lama: 10 hari
- Hari kerja outlet baru: 10 hari (sampai tanggal resign)
- Status di outlet baru: Resigned

## Testing Checklist

- [ ] Test Noviya Deva case (mutasi 26 Des)
  - [ ] Periode Des 2025: Muncul di outlet lama dengan full hari kerja
  - [ ] Periode Jan 2026: TIDAK muncul di outlet lama, muncul di Justus dengan full hari kerja
  
- [ ] Test mutasi di tengah periode
  - [ ] Entry outlet lama dengan proporsi benar
  - [ ] Entry outlet baru dengan proporsi benar
  - [ ] Total gaji + tunjangan = gaji pokok original
  
- [ ] Test BPJS assignment
  - [ ] BPJS hanya di outlet lama
  - [ ] BPJS = 0 di outlet baru
  
- [ ] Test service charge split
  - [ ] Total SC split by proporsi hari kerja
  - [ ] Sum(SC outlet lama + SC outlet baru) = Total SC original

## Files Modified
1. ✅ `app/Http/Controllers/PayrollReportController.php`
   - Line ~166: Fix mutation query
   - Line ~200: Create mutation map
   - Line ~998: Add mutation detection
   - Line ~1127: Add mutation data to userData
   - [ ] Add helper function `processMutatedEmployeePayroll()`
   - [ ] Modify Step 4 to call helper for mutated employees

## Status
- Phase 1: ✅ COMPLETED
- Phase 2: ⏳ IN PROGRESS
- Phase 3: ⏳ PENDING

## Notes
- Implementation ini cukup complex karena perlu handle banyak edge cases
- Perlu testing menyeluruh dengan berbagai scenario
- Dokumentasi ini akan di-update seiring progress
