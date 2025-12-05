# Employee Movement Execution System

## Overview
Sistem eksekusi otomatis untuk Personal Movement setelah semua approver menyetujui. Perubahan akan diterapkan pada tanggal effective date yang ditentukan.

## ✅ **7 Fungsi Eksekusi yang Diimplementasikan**

### **1. Perubahan Position**
- **Kondisi:** `position_change = true` dan `position_to` terisi
- **Aksi:** Update `id_jabatan` di tabel `users`
- **Target:** Employee yang bersangkutan

### **2. Perubahan Level**
- **Kondisi:** `level_change = true` dan `level_to` terisi
- **Aksi:** Update `id_level` di tabel `tbl_data_jabatan` untuk jabatan yang baru
- **Target:** Jabatan yang dipilih di `position_to`

### **3. Perubahan Salary**
- **Kondisi:** `salary_change = true` dan `salary_to` terisi
- **Aksi:** Update `gaji` dan `tunjangan` di tabel `payroll_master`
- **Target:** Employee yang bersangkutan
- **Logic:** 
  - Jika data payroll sudah ada → Update existing
  - Jika data payroll belum ada → Create new record

### **4. Perubahan Division**
- **Kondisi:** `division_change = true` dan `division_to` terisi
- **Aksi:** Update `division_id` di tabel `users`
- **Target:** Employee yang bersangkutan

### **5. Perubahan Unit/Property**
- **Kondisi:** `unit_property_change = true` dan `unit_property_to` terisi
- **Aksi:** Update `id_outlet` di tabel `users`
- **Target:** Employee yang bersangkutan

### **6. Termination**
- **Kondisi:** `employment_type = 'termination'`
- **Aksi:** Update `status = 'N'` di tabel `users`
- **Target:** Employee yang bersangkutan

### **7. Effective Date Management**
- **Kondisi:** Semua approval selesai dan `employment_effective_date` sudah tiba
- **Aksi:** Eksekusi semua perubahan yang applicable
- **Timing:** 
  - Otomatis saat approval terakhir (jika effective date sudah tiba)
  - Scheduled job setiap hari jam 08:00
  - Manual execution oleh HR/Superadmin

## **Flow Eksekusi**

### **1. Automatic Execution (Saat Approval)**
```
Approval Terakhir → Cek Effective Date → Eksekusi (jika sudah tiba)
```

### **2. Scheduled Execution (Daily)**
```
Cron Job 08:00 → Cari Movement Approved + Effective Date Hari Ini → Eksekusi
```

### **3. Manual Execution (HR/Superadmin)**
```
Button Execute → Konfirmasi → Eksekusi → Update Status
```

## **Database Changes**

### **Users Table Updates:**
```sql
-- Position change
UPDATE users SET id_jabatan = ? WHERE id = ?

-- Division change  
UPDATE users SET division_id = ? WHERE id = ?

-- Outlet change
UPDATE users SET id_outlet = ? WHERE id = ?

-- Termination
UPDATE users SET status = 'N' WHERE id = ?
```

### **Tbl_data_jabatan Table Updates:**
```sql
-- Level change
UPDATE tbl_data_jabatan SET id_level = ? WHERE id_jabatan = ?
```

### **Payroll_master Table Updates:**
```sql
-- Update existing
UPDATE payroll_master SET gaji = ?, tunjangan = ?, updated_at = ? WHERE user_id = ?

-- Insert new
INSERT INTO payroll_master (user_id, outlet_id, division_id, gaji, tunjangan, ...) VALUES (...)
```

## **Status Management**

### **Movement Status Flow:**
```
draft → pending → approved → executed
                    ↓
                 rejected
                    ↓
                  error
```

### **Status Definitions:**
- **`draft`** - Belum disubmit
- **`pending`** - Menunggu approval
- **`approved`** - Semua approval selesai, menunggu eksekusi
- **`executed`** - Perubahan sudah dieksekusi
- **`rejected`** - Ditolak oleh salah satu approver
- **`error`** - Error saat eksekusi

## **API Endpoints**

### **Manual Execution:**
```
POST /employee-movements/{id}/execute
```

**Request:** None

**Response:**
```json
{
    "success": true,
    "message": "Employee movement berhasil dieksekusi"
}
```

**Access Control:** HR (`division_id = 6`) atau Superadmin (`id_role = '5af56935b011a'`)

## **Scheduled Commands**

### **Command:**
```bash
php artisan employee-movements:execute
```

### **Schedule:**
- **Frequency:** Daily at 08:00 AM
- **Log:** `storage/logs/employee-movements-execution.log`
- **Overlap Protection:** `withoutOverlapping()`

### **Manual Execution:**
```bash
php artisan employee-movements:execute
```

## **Activity Logging**

### **Logged Actions:**
- `position_change` - Perubahan jabatan
- `level_change` - Perubahan level
- `salary_change` - Perubahan gaji
- `division_change` - Perubahan divisi
- `outlet_change` - Perubahan outlet
- `termination` - Terminasi employee

### **Log Format:**
```php
ActivityLog::create([
    'user_id' => auth()->id(),
    'action' => 'position_change',
    'model' => 'User',
    'model_id' => $employee->id,
    'description' => "Position changed from {$old} to {$new} for {$employee->nama_lengkap}",
]);
```

## **Notifications**

### **Execution Notification:**
- **Type:** `employee_movement_executed`
- **Recipient:** Employee yang bersangkutan
- **Message:** Daftar perubahan yang diterapkan
- **URL:** Link ke detail movement

### **Example Message:**
```
Personal Movement untuk John Doe telah dieksekusi. 
Perubahan yang diterapkan: Position, Salary, Division
```

## **Error Handling**

### **Database Transaction:**
- Semua perubahan dalam 1 transaction
- Rollback jika ada error
- Status movement diupdate ke `error`

### **Error Logging:**
```php
\Log::error('Error executing employee movement', [
    'movement_id' => $movement->id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);
```

### **User Feedback:**
- Success message untuk manual execution
- Error message dengan detail
- Status badge di UI menunjukkan error

## **UI Components**

### **Execute Button:**
- **Visibility:** HR/Superadmin + Status = 'approved'
- **Location:** Header di halaman detail
- **Style:** Green button dengan text "Execute Movement"
- **Action:** Confirmation dialog → API call → Reload page

### **Status Badges:**
- **Executed:** Blue badge
- **Error:** Red badge
- **Approved:** Green badge (dengan execute button)

## **Testing Scenarios**

### **Test Case 1: Position Change**
1. Create movement dengan position change
2. Approve semua level
3. Execute movement
4. Verify: `users.id_jabatan` updated

### **Test Case 2: Salary Change**
1. Create movement dengan salary change
2. Approve semua level
3. Execute movement
4. Verify: `payroll_master.gaji` dan `tunjangan` updated

### **Test Case 3: Termination**
1. Create movement dengan employment_type = 'termination'
2. Approve semua level
3. Execute movement
4. Verify: `users.status` = 'N'

### **Test Case 4: Scheduled Execution**
1. Create movement dengan effective date = tomorrow
2. Approve semua level
3. Wait until tomorrow 08:00
4. Verify: Movement status = 'executed'

### **Test Case 5: Error Handling**
1. Create movement dengan invalid data
2. Approve semua level
3. Execute movement
4. Verify: Movement status = 'error', transaction rolled back

## **Security Features**

1. **Access Control:** Hanya HR/Superadmin yang bisa manual execute
2. **Transaction Safety:** Database rollback pada error
3. **Activity Logging:** Semua perubahan di-log
4. **Validation:** Validasi data sebelum eksekusi
5. **Error Handling:** Proper error messages dan logging

## **Performance Considerations**

1. **Batch Processing:** Command bisa handle multiple movements
2. **Transaction Optimization:** Minimal database calls
3. **Logging Efficiency:** Structured logging untuk monitoring
4. **Scheduled Execution:** Background processing untuk performance
5. **Overlap Protection:** Prevent multiple executions

## **Monitoring & Maintenance**

### **Log Files:**
- `storage/logs/employee-movements-execution.log` - Command execution
- `storage/logs/laravel.log` - Application errors

### **Database Monitoring:**
- Check `activity_logs` table untuk tracking
- Monitor `employee_movements` status
- Verify data consistency setelah execution

### **Manual Commands:**
```bash
# Check pending executions
php artisan employee-movements:execute --dry-run

# Force execution (ignore effective date)
php artisan employee-movements:execute --force

# Check command status
php artisan schedule:list
```
