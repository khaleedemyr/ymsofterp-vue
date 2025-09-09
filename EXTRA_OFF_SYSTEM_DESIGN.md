# Sistem Extra Off Balance - Design Document

## 📋 **Overview**
Sistem untuk menangani saldo extra off karyawan yang bekerja di hari tanpa jadwal shift atau hari libur mereka.

## 🗄️ **Database Structure**

### 1. **extra_off_balance** (Saldo Extra Off)
- `user_id`: ID karyawan (unique)
- `balance`: Jumlah hari extra off yang tersedia
- `created_at`, `updated_at`: Timestamp

### 2. **extra_off_transactions** (Transaksi Extra Off)
- `user_id`: ID karyawan
- `transaction_type`: 'earned' (dapat) atau 'used' (gunakan)
- `amount`: Jumlah hari (+1 untuk earned, -1 untuk used)
- `source_type`: 'unscheduled_work', 'manual_adjustment', 'holiday_work'
- `source_date`: Tanggal sumber (untuk unscheduled_work)
- `description`: Deskripsi transaksi
- `used_date`: Tanggal penggunaan (untuk used)
- `approved_by`: ID yang approve (untuk manual_adjustment)
- `status`: 'pending', 'approved', 'cancelled'

## 🔍 **Logika Deteksi Extra Off**

### **Kriteria untuk Mendapat Extra Off:**
1. **Kerja tanpa shift**: Karyawan check-in di hari yang tidak ada jadwal shift
2. **Bukan hari libur nasional**: Karena itu sudah ditangani di holiday_attendance_compensations
3. **User aktif**: Status = 'A'
4. **Belum ada transaksi**: Belum pernah dapat extra off untuk tanggal tersebut

### **Query Deteksi:**
```sql
-- Cek attendance yang tidak ada shift
SELECT u.id, u.nama_lengkap, DATE(a.checktime) as work_date
FROM att_log a
INNER JOIN users u ON a.userid = u.id
LEFT JOIN user_shifts us ON u.id = us.user_id 
    AND DATE(a.checktime) = DATE(us.shift_date)
    AND us.status = 'active'
WHERE 
    a.inoutmode = 1  -- Check-in
    AND us.id IS NULL  -- Tidak ada shift
    AND u.status = 'A'  -- User aktif
    AND NOT EXISTS (SELECT 1 FROM tbl_kalender_perusahaan kp WHERE kp.tgl_libur = DATE(a.checktime))  -- Bukan hari libur nasional
    AND NOT EXISTS (SELECT 1 FROM extra_off_transactions eot WHERE eot.user_id = u.id AND eot.source_date = DATE(a.checktime))  -- Belum ada transaksi
```

## ⚙️ **Implementasi Sistem**

### **1. Automated Detection (Cron Job)**
- **Frequency**: Harian (setiap pagi)
- **Process**: 
  1. Deteksi kerja tanpa shift kemarin
  2. Insert transaksi 'earned'
  3. Update balance
  4. Log hasil

### **2. Manual Adjustment**
- **Admin/HR**: Bisa tambah/kurang saldo manual
- **Approval**: Butuh approval dari supervisor
- **Audit Trail**: Semua transaksi tercatat

### **3. Usage Tracking**
- **Employee**: Bisa gunakan extra off untuk izin
- **Validation**: Cek saldo cukup
- **Update**: Kurangi balance, catat used_date

## 🔄 **Workflow**

### **Earned Extra Off:**
1. **Detection**: Sistem deteksi kerja tanpa shift
2. **Transaction**: Insert transaksi 'earned' dengan status 'approved'
3. **Balance Update**: Update balance user
4. **Notification**: Notify user (optional)

### **Used Extra Off:**
1. **Request**: User ajukan izin dengan extra off
2. **Validation**: Cek saldo cukup
3. **Transaction**: Insert transaksi 'used'
4. **Balance Update**: Kurangi balance
5. **Approval**: Update status izin

## 📊 **API Endpoints yang Dibutuhkan**

### **1. Get Extra Off Balance**
```
GET /api/extra-off/balance
Response: { "balance": 5, "transactions": [...] }
```

### **2. Get Extra Off Transactions**
```
GET /api/extra-off/transactions?limit=10
Response: { "transactions": [...] }
```

### **3. Use Extra Off**
```
POST /api/extra-off/use
Body: { "use_date": "2025-01-15", "reason": "Izin pribadi" }
```

### **4. Manual Adjustment (Admin)**
```
POST /api/extra-off/adjust
Body: { "user_id": 123, "amount": 1, "reason": "Kompensasi kerja lembur" }
```

## 🎯 **Integration dengan Attendance Modal**

### **Frontend Changes:**
1. **Deteksi Leave Type**: Jika "Extra Off" dipilih
2. **Load Balance**: Ambil saldo dari API
3. **Display**: Tampilkan saldo dan detail
4. **Validation**: Cek saldo cukup sebelum submit

### **Backend Changes:**
1. **Controller**: Tambah method untuk extra off balance
2. **Service**: Logic untuk deteksi dan update balance
3. **Model**: Model untuk extra_off_balance dan extra_off_transactions

## 🔧 **Setup Instructions**

### **1. Database Setup:**
```bash
# Jalankan query create table
mysql -u username -p database_name < create_extra_off_balance_table.sql
```

### **2. Cron Job Setup:**
```bash
# Tambah ke crontab
0 6 * * * cd /path/to/project && php artisan extra-off:detect
```

### **3. API Routes:**
```php
Route::prefix('api/extra-off')->group(function () {
    Route::get('/balance', [ExtraOffController::class, 'getBalance']);
    Route::get('/transactions', [ExtraOffController::class, 'getTransactions']);
    Route::post('/use', [ExtraOffController::class, 'useExtraOff']);
    Route::post('/adjust', [ExtraOffController::class, 'adjustBalance'])->middleware('admin');
});
```

## 📈 **Benefits**

1. **Transparency**: User tahu saldo extra off mereka
2. **Automation**: Deteksi otomatis tanpa manual input
3. **Audit Trail**: Semua transaksi tercatat
4. **Flexibility**: Bisa manual adjustment jika perlu
5. **Integration**: Terintegrasi dengan sistem izin yang ada

## 🚀 **Next Steps**

1. **Create Tables**: Jalankan query create table
2. **Create Models**: Buat model ExtraOffBalance dan ExtraOffTransaction
3. **Create Service**: Buat ExtraOffService untuk business logic
4. **Create Controller**: Buat ExtraOffController untuk API
5. **Create Command**: Buat artisan command untuk deteksi otomatis
6. **Update Frontend**: Integrasi dengan modal attendance
7. **Setup Cron**: Setup cron job untuk deteksi harian
8. **Testing**: Test semua fitur dan edge cases
