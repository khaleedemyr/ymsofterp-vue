# Dokumentasi Lengkap Fitur "My Attendance" di YMSoftERP

## 📋 Daftar Isi
1. [Overview](#overview)
2. [Struktur Database](#struktur-database)
3. [Fitur Utama](#fitur-utama)
4. [Alur Kerja](#alur-kerja)
5. [API Endpoints](#api-endpoints)
6. [Komponen Frontend](#komponen-frontend)
7. [Logika Bisnis](#logika-bisnis)

---

## Overview

Fitur "My Attendance" adalah modul yang memungkinkan karyawan untuk:
- Melihat jadwal kerja dan data kehadiran mereka
- Melihat ringkasan statistik kehadiran (hadir, terlambat, tidak hadir, lembur)
- Mengajukan izin/cuti dengan approval flow berjenjang
- Melihat status pengajuan koreksi jadwal/attendance
- Melihat data Public Holiday (PH) dan Extra Off
- Melihat saldo cuti tahunan

**Route:** `/attendance`  
**Controller:** `App\Http\Controllers\AttendanceController`  
**View:** `resources/js/Pages/Attendance/Index.vue`

---

## Struktur Database

### Tabel Utama

#### 1. `att_log`
Tabel utama untuk menyimpan data scan fingerprint/attendance.
- `scan_date`: Timestamp scan
- `pin`: PIN karyawan
- `inoutmode`: 1 = IN, 2 = OUT
- `sn`: Serial number mesin fingerprint

#### 2. `user_shifts`
Jadwal kerja karyawan per hari.
- `user_id`: ID karyawan
- `tanggal`: Tanggal jadwal
- `shift_id`: ID shift (NULL = OFF)
- `outlet_id`: ID outlet

#### 3. `shifts`
Master data shift kerja.
- `id`: ID shift
- `shift_name`: Nama shift (e.g., "Pagi", "Siang", "Malam")
- `time_start`: Jam mulai shift
- `time_end`: Jam akhir shift

#### 4. `absent_requests`
Pengajuan izin/cuti karyawan.
- `id`: ID request
- `user_id`: ID karyawan
- `leave_type_id`: ID jenis izin/cuti
- `date_from`: Tanggal mulai
- `date_to`: Tanggal akhir
- `reason`: Alasan
- `status`: pending, supervisor_approved, approved, rejected, cancelled
- `document_path`: Path dokumen pendukung (single)
- `document_paths`: JSON array path dokumen (multiple)
- `approval_request_id`: ID approval request

#### 5. `leave_types`
Master data jenis izin/cuti.
- `id`: ID jenis cuti
- `name`: Nama jenis cuti
- `max_days`: Maksimal hari (0 = unlimited)
- `requires_document`: Wajib dokumen pendukung
- `description`: Deskripsi

#### 6. `approval_requests`
Request approval untuk izin/cuti (legacy system).
- `id`: ID approval request
- `user_id`: ID karyawan
- `approver_id`: ID approver pertama (backward compatibility)
- `hrd_approver_id`: ID approver HRD
- `leave_type_id`: ID jenis cuti
- `date_from`: Tanggal mulai
- `date_to`: Tanggal akhir
- `status`: pending, approved, rejected
- `hrd_status`: pending, approved, rejected

#### 7. `absent_request_approval_flows`
Approval flow berjenjang untuk izin/cuti (new system).
- `id`: ID flow
- `absent_request_id`: ID absent request
- `approver_id`: ID approver
- `approval_level`: Level approval (1, 2, 3, dst)
- `status`: PENDING, APPROVED, REJECTED

#### 8. `holiday_attendance_compensations`
Kompensasi untuk kerja di hari libur nasional.
- `id`: ID kompensasi
- `user_id`: ID karyawan
- `holiday_date`: Tanggal libur
- `compensation_type`: extra_off atau bonus
- `compensation_amount`: Jumlah kompensasi
- `status`: approved, used
- `available_amount`: Jumlah yang masih tersedia

#### 9. `extra_off_balance`
Saldo extra off karyawan.
- `user_id`: ID karyawan
- `balance`: Saldo saat ini

#### 10. `extra_off_transactions`
Transaksi extra off (earned/used).
- `id`: ID transaksi
- `user_id`: ID karyawan
- `transaction_type`: earned atau used
- `amount`: Jumlah
- `source_type`: Sumber transaksi
- `source_date`: Tanggal sumber
- `status`: approved

#### 11. `schedule_attendance_correction_approvals`
Pengajuan koreksi jadwal/attendance.
- `id`: ID correction
- `user_id`: ID karyawan yang dikoreksi
- `type`: schedule atau attendance
- `tanggal`: Tanggal yang dikoreksi
- `old_value`: Nilai lama
- `new_value`: Nilai baru
- `reason`: Alasan koreksi
- `status`: pending, approved, rejected
- `requested_by`: ID yang mengajukan
- `approved_by`: ID yang menyetujui

#### 12. `tbl_kalender_perusahaan`
Kalender libur perusahaan.
- `tgl_libur`: Tanggal libur
- `keterangan`: Keterangan libur

#### 13. `user_pins`
Mapping PIN karyawan dengan outlet.
- `user_id`: ID karyawan
- `pin`: PIN di mesin fingerprint
- `outlet_id`: ID outlet

---

## Fitur Utama

### 1. **Kalender Jadwal Kerja & Attendance**

#### Tampilan Kalender
- Menampilkan kalender bulanan dengan periode payroll (26 bulan sebelumnya - 25 bulan sekarang)
- Setiap hari menampilkan:
  - **Jadwal shift** (nama shift, jam mulai-akhir)
  - **Data attendance** (jam masuk, jam keluar, telat, lembur)
  - **Status** (hadir, tidak hadir, tidak checkout)
  - **Hari libur** (ditandai dengan warna merah)
  - **Approved absent** (ditandai dengan badge hijau)

#### Detail Attendance per Hari
- Modal detail yang menampilkan:
  - Total scan IN dan OUT per outlet
  - Jam masuk pertama (first_in)
  - Jam keluar terakhir (last_out)
  - Durasi kerja
  - Nama outlet

#### Logika Cross-Day Attendance
- Menangani attendance yang melewati tengah malam (cross-day)
- Menggunakan logika smart cross-day processing:
  - Jika ada OUT scan di hari yang sama dan cross-day, pilih yang paling masuk akal
  - Prioritas cross-day jika:
    - Same-day OUT terlalu pendek (< 5 jam) ATAU
    - Cross-day OUT di pagi sangat awal (00:00-06:00)

### 2. **Ringkasan Statistik Attendance**

Menampilkan 5 metrik utama:
1. **Hadir**: Jumlah hari hadir (hanya shift yang sudah lewat)
2. **Terlambat**: Total menit terlambat
3. **Tidak Hadir**: Jumlah hari tidak hadir
4. **Lembur**: Total jam lembur
5. **Persentase**: Persentase kehadiran

#### Perhitungan:
- **Present Days**: Shift yang sudah lewat dan ada attendance (first_in)
- **Absent Days**: Shift yang sudah lewat dan tidak ada attendance
- **Late Minutes**: Selisih jam masuk dengan jam mulai shift (jika terlambat)
- **Overtime Hours**: Selisih jam keluar dengan jam akhir shift (jika lebih dari shift end)
- **Percentage**: (Present Days / Total Shifts) * 100

### 3. **Pengajuan Izin/Cuti**

#### Fitur:
- **Multiple Leave Types**: Mendukung berbagai jenis izin/cuti
- **Date Range**: Dapat memilih tanggal mulai dan akhir
- **Multiple Approvers**: Support approval berjenjang (Level 1, 2, 3, dst)
- **Document Upload**: Upload dokumen pendukung (single atau multiple)
- **Camera Capture**: Ambil foto dokumen langsung dari kamera
- **Balance Validation**: Validasi saldo untuk:
  - Public Holiday Extra Off
  - Annual Leave (cuti tahunan)
  - Regular Extra Off

#### Alur Approval:
1. User mengajukan izin/cuti dengan memilih multiple approvers
2. Approval flow berjenjang:
   - Level 1 approver menerima notifikasi pertama
   - Setelah Level 1 approve, Level 2 menerima notifikasi
   - Begitu seterusnya sampai semua approver approve
3. Setelah semua approver approve, muncul approval HRD
4. Setelah HRD approve, status menjadi `approved`

#### Validasi:
- Tidak boleh ada attendance data di tanggal yang diajukan
- Tidak boleh ada pengajuan lain yang overlap
- Harus memilih minimal 1 approver
- Dokumen wajib jika `requires_document = true`
- Saldo harus cukup untuk jenis cuti tertentu

#### Status:
- `pending`: Menunggu approval
- `supervisor_approved`: Disetujui supervisor (tapi belum HRD)
- `approved`: Disetujui HRD (final)
- `rejected`: Ditolak
- `cancelled`: Dibatalkan user

### 4. **Pembatalan Izin/Cuti**

#### Kondisi yang Diizinkan:
- Status: `pending`, `supervisor_approved`, atau `approved`
- Tanggal mulai belum terlewat (dapat dibatalkan sampai hari H)

#### Proses:
- User mengklik tombol "Batalkan" pada request
- Mengisi alasan pembatalan (opsional)
- Status berubah menjadi `rejected` (cancelled by user)
- Notifikasi dikirim ke approver(s)

### 5. **Public Holiday (PH) & Extra Off**

#### Public Holiday:
- Menampilkan kompensasi untuk kerja di hari libur nasional
- Tipe kompensasi:
  - **Extra Off**: Hari libur tambahan
  - **Bonus**: Uang bonus
- Menampilkan:
  - Total hari PH
  - Total bonus (Rp)
  - Detail per hari libur

#### Extra Off:
- Menampilkan saldo extra off saat ini
- Menampilkan transaksi bulan ini (earned/used)
- Net bulan ini (earned - used)
- Detail transaksi per item

### 6. **Status Pengajuan Koreksi**

Menampilkan status pengajuan koreksi jadwal/attendance:
- **Type**: schedule atau attendance
- **Tanggal**: Tanggal yang dikoreksi
- **Sebelum**: Nilai lama
- **Sesudah**: Nilai baru
- **Alasan**: Alasan koreksi
- **Status**: pending, approved, rejected
- **Approver**: Nama yang menyetujui/menolak

---

## Alur Kerja

### 1. Alur Menampilkan Attendance

```
User membuka /attendance
    ↓
AttendanceController@index
    ↓
1. Get work schedules (user_shifts)
2. Get attendance records (att_log)
3. Get attendance summary (statistik)
4. Get attendance data with first_in/last_out
5. Get holidays
6. Get approved absents
7. Get user leave requests
8. Get leave types
9. Get available approvers
10. Get PH data
11. Get Extra Off data
12. Get correction requests
    ↓
Render Index.vue dengan semua data
```

### 2. Alur Pengajuan Izin/Cuti

```
User klik tombol "Absent" di kalender
    ↓
Modal form muncul
    ↓
User isi form:
- Pilih jenis cuti
- Pilih tanggal
- Isi alasan
- Pilih approver(s)
- Upload dokumen (jika perlu)
    ↓
Validasi:
- Cek saldo tersedia
- Cek tidak ada attendance
- Cek tidak ada overlap
    ↓
Submit ke API
    ↓
AttendanceController@submitAbsentRequest
    ↓
1. Validasi input
2. Cek attendance data
3. Cek overlap request
4. Upload dokumen
5. Insert absent_requests
6. Insert approval_requests (legacy)
7. Insert absent_request_approval_flows (new)
8. Kirim notifikasi ke approver pertama
    ↓
Response success
```

### 3. Alur Approval Izin/Cuti

```
Approver menerima notifikasi
    ↓
Approver buka halaman approval
    ↓
Approver approve/reject
    ↓
Jika approve:
- Update status flow ke APPROVED
- Cek apakah semua flow sudah approve
- Jika ya, kirim notifikasi ke HRD
- Jika belum, kirim notifikasi ke approver berikutnya
    ↓
Jika reject:
- Update status flow ke REJECTED
- Update absent_request status ke rejected
- Kirim notifikasi ke user
```

---

## API Endpoints

### 1. GET `/attendance`
Menampilkan halaman My Attendance.

**Response:**
```json
{
  "workSchedules": [...],
  "attendanceRecords": [...],
  "attendanceSummary": {
    "total_days": 20,
    "present_days": 18,
    "total_late_minutes": 45,
    "absent_days": 2,
    "total_lembur_hours": 8,
    "percentage": 90.0
  },
  "calendar": {...},
  "holidays": [...],
  "approvedAbsents": [...],
  "userLeaveRequests": [...],
  "leaveTypes": [...],
  "availableApprovers": [...],
  "phData": {...},
  "extraOffData": {...},
  "correctionRequests": [...],
  "filters": {
    "bulan": 12,
    "tahun": 2024,
    "start_date": "2024-11-26",
    "end_date": "2024-12-25"
  },
  "user": {
    "id": 1,
    "nama_lengkap": "John Doe",
    "id_outlet": 1,
    "cuti": 12
  }
}
```

### 2. GET `/api/attendance/calendar-data`
Mendapatkan data kalender untuk periode tertentu.

**Query Parameters:**
- `start_date`: Tanggal mulai
- `end_date`: Tanggal akhir

**Response:**
```json
{
  "2024-12-01": [
    {
      "shift_name": "Pagi",
      "start_time": "08:00:00",
      "end_time": "17:00:00",
      "check_in_time": "08:05:00",
      "check_out_time": "17:30:00",
      "attendance_status": "present",
      "has_attendance": true
    }
  ]
}
```

### 3. POST `/api/attendance/absent-request`
Mengajukan izin/cuti.

**Request Body:**
```json
{
  "leave_type_id": 1,
  "date_from": "2024-12-10",
  "date_to": "2024-12-12",
  "reason": "Sakit",
  "approvers": [2, 3],  // Array of approver IDs
  "documents": [...]  // Array of files (optional)
}
```

**Response:**
```json
{
  "success": true,
  "message": "Permohonan izin/cuti berhasil dikirim",
  "data": {
    "id": 123,
    "leave_type_id": 1,
    "leave_type_name": "Sakit",
    "date_from": "2024-12-10",
    "date_to": "2024-12-12",
    "status": "pending"
  }
}
```

### 4. GET `/api/attendance/approvers`
Mencari approver berdasarkan keyword.

**Query Parameters:**
- `search`: Keyword pencarian

**Response:**
```json
{
  "success": true,
  "users": [
    {
      "id": 2,
      "nama_lengkap": "Jane Doe",
      "email": "jane@example.com",
      "nama_jabatan": "Manager",
      "nama_divisi": "HRD",
      "nama_outlet": "Head Office"
    }
  ]
}
```

### 5. POST `/api/attendance/cancel-leave/{id}`
Membatalkan pengajuan izin/cuti.

**Request Body:**
```json
{
  "reason": "Alasan pembatalan"  // Optional
}
```

**Response:**
```json
{
  "success": true,
  "message": "Permohonan izin/cuti berhasil dibatalkan"
}
```

### 6. GET `/api/holiday-attendance/my-extra-off-days`
Mendapatkan saldo extra off dari Public Holiday.

**Response:**
```json
[
  {
    "id": 1,
    "holiday_date": "2024-12-25",
    "holiday_name": "Natal",
    "compensation_type": "extra_off",
    "compensation_amount": 1,
    "available_amount": 1
  }
]
```

---

## Komponen Frontend

### File: `resources/js/Pages/Attendance/Index.vue`

#### Props:
- `workSchedules`: Array jadwal kerja
- `attendanceRecords`: Array record attendance
- `attendanceSummary`: Object ringkasan statistik
- `calendar`: Object data kalender
- `holidays`: Array hari libur
- `approvedAbsents`: Array izin yang disetujui
- `userLeaveRequests`: Array permohonan izin user
- `leaveTypes`: Array jenis izin/cuti
- `availableApprovers`: Array approver yang tersedia
- `phData`: Object data Public Holiday
- `extraOffData`: Object data Extra Off
- `correctionRequests`: Array pengajuan koreksi
- `filters`: Object filter (bulan, tahun, dll)
- `user`: Object data user

#### Computed Properties:
- `calendarDays`: Generate array hari untuk kalender
- `getApprovedAbsentForDate`: Function untuk mendapatkan approved absent per tanggal
- `selectedLeaveType`: Leave type yang dipilih
- `isPublicHolidayType`: Cek apakah jenis cuti adalah Public Holiday
- `isAnnualLeaveType`: Cek apakah jenis cuti adalah Annual Leave
- `isExtraOffType`: Cek apakah jenis cuti adalah Extra Off
- `totalExtraOffDays`: Total saldo extra off dari PH
- `annualLeaveBalance`: Saldo cuti tahunan
- `regularExtraOffBalance`: Saldo extra off biasa
- `selectedDaysCount`: Jumlah hari yang dipilih
- `availableBalance`: Saldo yang tersedia
- `isExceedingBalance`: Cek apakah melebihi saldo

#### Methods:
- `fetchData()`: Reload data dengan filter baru
- `showAttendanceDetail(date)`: Tampilkan modal detail attendance
- `showAbsentModal(date)`: Tampilkan modal pengajuan izin
- `submitAbsentRequest()`: Submit pengajuan izin
- `loadApprovers(search)`: Load daftar approver
- `addApprover(user)`: Tambah approver ke list
- `removeApprover(index)`: Hapus approver dari list
- `openCameraModal()`: Buka modal kamera
- `capturePhoto()`: Ambil foto dari kamera
- `showCancelModal(request)`: Tampilkan modal pembatalan
- `confirmCancelRequest()`: Konfirmasi pembatalan
- `formatDate(date)`: Format tanggal
- `formatDateTime(datetime)`: Format tanggal waktu
- `formatDateRange(from, to)`: Format range tanggal
- `getStatusText(status)`: Get text status
- `formatCorrectionValue(value, type)`: Format nilai koreksi

---

## Logika Bisnis

### 1. Perhitungan Attendance

#### First In & Last Out:
- **First In**: Scan IN pertama di hari tersebut
- **Last Out**: Scan OUT terakhir di hari tersebut (bisa cross-day)

#### Cross-Day Logic:
```php
// Jika ada OUT scan di hari yang sama dan cross-day:
if (sameDayOutDuration < 5 hours OR crossDayOutHour is 00:00-06:00) {
    use crossDayOut
    isCrossDay = true
} else {
    use sameDayOut
    isCrossDay = false
}
```

#### Perhitungan Telat:
```php
telat = max(0, (jam_masuk - jam_mulai_shift) / 60)  // dalam menit
```

#### Perhitungan Lembur:
```php
if (jam_keluar > jam_akhir_shift) {
    if (crossDay) {
        lembur = (24 jam + selisih) / 3600
    } else {
        lembur = (jam_keluar - jam_akhir_shift) / 3600
    }
    lembur = min(lembur, 12)  // Maksimal 12 jam
}
```

### 2. Validasi Pengajuan Izin/Cuti

#### Validasi Saldo:
- **Public Holiday**: Cek `totalExtraOffDays` dari PH compensations
- **Annual Leave**: Cek `user.cuti` (saldo cuti tahunan)
- **Extra Off**: Cek `extra_off_balance.balance`

#### Validasi Overlap:
```php
// Tidak boleh overlap dengan:
1. Attendance data di tanggal yang sama
2. Absent request lain dengan status: pending, supervisor_approved, approved
```

#### Validasi Dokumen:
```php
if (leaveType.requires_document && !hasDocument) {
    return error: "Dokumen pendukung wajib"
}
```

### 3. Approval Flow

#### Sequential Approval:
```php
// Level 1 approver approve
if (allApproversApproved) {
    // Kirim notifikasi ke HRD
    hrd_status = 'pending'
} else {
    // Kirim notifikasi ke approver berikutnya
    nextApproverLevel = currentLevel + 1
}
```

#### Status Update:
```php
// Setelah semua approver approve
if (allApproversApproved && hrdApproved) {
    absent_request.status = 'approved'
} else if (allApproversApproved && !hrdApproved) {
    absent_request.status = 'supervisor_approved'
} else if (anyRejected) {
    absent_request.status = 'rejected'
}
```

### 4. Periode Payroll

Periode payroll adalah:
- **Start Date**: 26 bulan sebelumnya
- **End Date**: 25 bulan sekarang

Contoh:
- Bulan: Desember 2024
- Start: 26 November 2024
- End: 25 Desember 2024

---

## Catatan Penting

1. **Cross-Day Attendance**: Sistem menangani attendance yang melewati tengah malam dengan logika khusus
2. **Multi-Outlet Support**: User bisa punya PIN di multiple outlet, sistem akan aggregate semua attendance
3. **Approval Berjenjang**: Support multiple approvers dengan sequential approval flow
4. **Document Upload**: Support single dan multiple document upload, dengan camera capture
5. **Balance Validation**: Validasi saldo untuk jenis cuti tertentu (PH, Annual Leave, Extra Off)
6. **Backward Compatibility**: Tetap support single approver untuk backward compatibility

---

## Referensi File

- **Controller**: `app/Http/Controllers/AttendanceController.php`
- **View**: `resources/js/Pages/Attendance/Index.vue`
- **Routes**: `routes/web.php` (line 2116-2126)
- **Models**: 
  - `App\Models\LeaveType`
  - `App\Models\User`
- **Related Controllers**:
  - `ScheduleAttendanceCorrectionController`
  - `HolidayAttendanceController`
  - `ApprovalController`

---

**Dokumentasi ini dibuat untuk memahami fitur My Attendance secara lengkap dan dapat digunakan sebagai referensi untuk pengembangan atau maintenance.**

