# Employee Movement Approval System

## Overview
Sistem approval berjenjang untuk Personal Movement yang mengirim notifikasi secara bertahap sesuai urutan approval yang dipilih.

## Flow Approval
1. **HOD** (Head of Department) - Approval pertama
2. **GM** (General Manager) - Approval kedua
3. **GM HR** (General Manager HR) - Approval ketiga  
4. **BOD** (Board of Directors) - Approval terakhir

## Cara Kerja

### 1. Saat Create Employee Movement
- Ketika data disimpan dengan status `pending`, sistem otomatis mengirim notifikasi ke approver pertama (HOD)
- Notifikasi berisi informasi employee, jenis movement, dan pembuat request

### 2. Proses Approval Berjenjang
- Setiap kali ada approval, sistem akan:
  - Update status approval di database
  - Kirim notifikasi ke approver berikutnya (jika ada)
  - Jika semua approval selesai, kirim notifikasi ke creator

### 3. Jika Ditolak
- Sistem akan mengirim notifikasi ke creator dengan alasan penolakan
- Proses approval berhenti

## API Endpoints

### Approve Employee Movement
```
POST /employee-movements/{id}/approve
```

**Request Body:**
```json
{
    "approval_level": "hod|gm|gm_hr|bod",
    "status": "approved|rejected", 
    "notes": "Optional notes"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Approval berhasil diproses"
}
```

## Notifikasi Types

### 1. `employee_movement_approval`
- Dikirim ke approver yang harus approve
- Berisi informasi employee dan jenis movement

### 2. `employee_movement_completed`
- Dikirim ke creator ketika semua approval selesai
- Menandakan proses approval telah selesai

### 3. `employee_movement_rejected`
- Dikirim ke creator ketika ada penolakan
- Berisi alasan penolakan

## Validasi

### 1. Hak Akses
- Hanya approver yang dipilih yang bisa approve pada level tersebut
- Validasi berdasarkan `hod_approver_id`, `gm_approver_id`, dll

### 2. Urutan Approval
- HOD harus approve dulu sebelum GM
- GM harus approve dulu sebelum GM HR
- GM HR harus approve dulu sebelum BOD

### 3. Status Movement
- Status akan berubah otomatis:
  - `pending` → saat menunggu approval
  - `approved` → ketika semua approval selesai
  - `rejected` → ketika ada penolakan

## Contoh Penggunaan

### 1. Create Employee Movement
```php
// Di form create, pilih approver untuk setiap level
$data = [
    'employee_id' => 123,
    'employee_name' => 'John Doe',
    'employment_type' => 'promotion',
    'hod_approver_id' => 456,  // HOD yang dipilih
    'gm_approver_id' => 789,   // GM yang dipilih
    'gm_hr_approver_id' => 101, // GM HR yang dipilih
    'bod_approver_id' => 202,   // BOD yang dipilih
    'status' => 'pending'
];

// Sistem otomatis kirim notifikasi ke HOD (approver pertama)
```

### 2. HOD Approve
```javascript
// HOD melakukan approval
fetch('/employee-movements/123/approve', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        approval_level: 'hod',
        status: 'approved',
        notes: 'Approved by HOD'
    })
});

// Sistem otomatis kirim notifikasi ke GM (approver berikutnya)
```

### 3. GM Approve
```javascript
// GM melakukan approval
fetch('/employee-movements/123/approve', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        approval_level: 'gm',
        status: 'approved',
        notes: 'Approved by GM'
    })
});

// Sistem otomatis kirim notifikasi ke GM HR (approver berikutnya)
```

## Database Fields

### Employee Movements Table
- `hod_approval` - Status approval HOD
- `hod_approval_date` - Tanggal approval HOD
- `hod_approver_id` - ID user HOD yang dipilih
- `gm_approval` - Status approval GM
- `gm_approval_date` - Tanggal approval GM
- `gm_approver_id` - ID user GM yang dipilih
- `gm_hr_approval` - Status approval GM HR
- `gm_hr_approval_date` - Tanggal approval GM HR
- `gm_hr_approver_id` - ID user GM HR yang dipilih
- `bod_approval` - Status approval BOD
- `bod_approval_date` - Tanggal approval BOD
- `bod_approver_id` - ID user BOD yang dipilih
- `status` - Status keseluruhan (pending/approved/rejected)

## Testing

### Test Case 1: Normal Flow
1. Create employee movement dengan 4 approver
2. HOD approve → notifikasi ke GM
3. GM approve → notifikasi ke GM HR
4. GM HR approve → notifikasi ke BOD
5. BOD approve → notifikasi ke creator (completed)

### Test Case 2: Rejection Flow
1. Create employee movement dengan 4 approver
2. HOD approve → notifikasi ke GM
3. GM reject → notifikasi ke creator (rejected)

### Test Case 3: Unauthorized Access
1. User yang bukan approver mencoba approve
2. Sistem harus return error 403

### Test Case 4: Wrong Order
1. GM mencoba approve sebelum HOD
2. Sistem harus return error 400
