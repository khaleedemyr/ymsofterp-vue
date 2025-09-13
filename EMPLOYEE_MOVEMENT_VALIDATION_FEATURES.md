# Employee Movement Validation Features

## Overview
Implementasi validasi dan access control untuk sistem Personal Movement dengan fitur-fitur berikut:

## ✅ **Fitur yang Telah Diimplementasikan**

### 1. **Validasi Employment Type untuk Salary Fields**

#### **Aturan Validasi:**
- **`Extend contract without adjustment`** → Salary fields **TIDAK BISA** di-checklis
- **`Extend contract with adjustment`** → Salary fields **BISA** di-checklis
- **`Promotion`** → Salary fields **BISA** di-checklis
- **`Mutation`** → Salary fields **BISA** di-checklis
- **`Demotion`** → Salary fields **BISA** di-checklis
- **`Termination`** → Salary fields **TIDAK BISA** di-checklis

#### **Implementasi Frontend:**
```javascript
// Computed property untuk validasi salary fields
const isSalaryAllowed = computed(() => {
  return form.employment_type && 
         form.employment_type !== 'extend_contract_without_adjustment' &&
         form.employment_type !== 'termination';
});

// Watch employment_type untuk reset salary fields jika tidak diizinkan
watch(() => form.employment_type, (newType) => {
  if (newType === 'extend_contract_without_adjustment' || newType === 'termination') {
    form.salary_change = false;
    form.gaji_pokok_to = '';
    form.tunjangan_to = '';
    form.salary_to = '';
  }
});
```

#### **UI Behavior:**
- Checkbox salary menjadi disabled jika employment type tidak mengizinkan
- Input fields gaji pokok dan tunjangan menjadi disabled
- Label menunjukkan pesan "(Not available for [employment type])"
- Otomatis reset salary fields saat employment type berubah

### 2. **Access Control untuk Salary Fields**

#### **Aturan Access Control:**
- **Hanya users dengan `division_id = 6` (HR)** yang bisa melihat dan edit salary
- Salary input **HANYA** ada di halaman detail (Show.vue)
- Salary tidak bisa diedit di halaman create/edit

#### **Implementasi Backend:**
```php
// Validasi di updateSalary method
if ($user->division_id !== 6) {
    return response()->json([
        'success' => false,
        'message' => 'Hanya HR yang dapat mengedit salary'
    ], 403);
}
```

#### **Implementasi Frontend:**
```javascript
// Check if user can edit salary (division_id = 6)
const canEditSalary = computed(() => {
  return props.user?.division_id === 6;
});

// Check if salary change is allowed for this employment type
const isSalaryChangeAllowed = computed(() => {
  return props.movement.employment_type && 
         props.movement.employment_type !== 'extend_contract_without_adjustment' &&
         props.movement.employment_type !== 'termination';
});
```

### 3. **Salary Editing di Halaman Detail**

#### **Fitur Edit Salary:**
- Button "Edit" muncul hanya jika:
  - User adalah HR (`division_id = 6`)
  - Employment type mengizinkan salary change
  - Salary change sudah di-checklis
- Form edit inline dengan fields:
  - Gaji Pokok
  - Tunjangan
- Button Save/Cancel
- Auto-calculate total salary

#### **UI Components:**
```vue
<button 
  v-if="canEditSalary && isSalaryChangeAllowed && movement.salary_change"
  @click="startEditSalary"
  class="ml-2 text-blue-600 hover:text-blue-800 text-xs"
>
  Edit
</button>
```

### 4. **Gabungan Effective Date**

#### **Perubahan:**
- **Sebelum:** Ada 2 effective date fields
  - `employment_effective_date` (di Employment & Renewal section)
  - `adjustment_effective_date` (di Adjustment & Movement section)
- **Sesudah:** Hanya 1 effective date field
  - `employment_effective_date` (di Employment & Renewal section)

#### **Database Changes:**
- Field `adjustment_effective_date` dihapus dari:
  - Model fillable
  - Model casts
  - Controller validation
  - Frontend form

### 5. **API Endpoints**

#### **Update Salary (HR Only):**
```
PUT /employee-movements/{id}/salary
```

**Request Body:**
```json
{
    "gaji_pokok_to": "5000000",
    "tunjangan_to": "1000000"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Salary berhasil diperbarui"
}
```

**Validasi:**
- User harus `division_id = 6` (HR)
- Employment type harus mengizinkan salary change
- Fields harus numeric dan >= 0

## **Flow Penggunaan**

### **1. Create Employee Movement:**
1. Pilih employment type
2. Jika `extend_contract_without_adjustment` atau `termination`:
   - Salary checkbox disabled
   - Salary input fields disabled
3. Jika employment type lain:
   - Salary checkbox enabled
   - Bisa checklis salary change

### **2. Edit Salary (HR Only):**
1. Buka halaman detail employee movement
2. Jika user adalah HR dan employment type mengizinkan:
   - Button "Edit" muncul di salary section
3. Klik "Edit" → form edit inline muncul
4. Input gaji pokok dan tunjangan
5. Klik "Save" → data tersimpan
6. Klik "Cancel" → form edit hilang

### **3. Validasi Backend:**
- Semua validasi employment type dan access control
- Auto-calculate total salary dari gaji pokok + tunjangan
- Activity log untuk tracking perubahan

## **Database Schema**

### **Employee Movements Table:**
```sql
-- Fields yang digunakan untuk salary
gaji_pokok_to DECIMAL(15,2) NULL
tunjangan_to DECIMAL(15,2) NULL  
salary_to DECIMAL(15,2) NULL
salary_change BOOLEAN DEFAULT FALSE

-- Field yang dihapus
-- adjustment_effective_date (dihapus)
```

## **Testing Scenarios**

### **Test Case 1: Employment Type Validation**
1. Pilih "Extend contract without adjustment"
2. Verify: Salary checkbox disabled
3. Pilih "Promotion"
4. Verify: Salary checkbox enabled

### **Test Case 2: Access Control**
1. Login sebagai non-HR user
2. Buka detail employee movement
3. Verify: Button "Edit" tidak muncul
4. Login sebagai HR user
5. Verify: Button "Edit" muncul (jika conditions terpenuhi)

### **Test Case 3: Salary Editing**
1. Login sebagai HR
2. Buka detail employee movement dengan salary change
3. Klik "Edit"
4. Input gaji pokok dan tunjangan
5. Klik "Save"
6. Verify: Data tersimpan dan form edit hilang

### **Test Case 4: Effective Date**
1. Create employee movement
2. Verify: Hanya ada 1 effective date field
3. Input effective date
4. Submit form
5. Verify: Data tersimpan dengan 1 effective date

## **Security Features**

1. **Access Control:** Hanya HR yang bisa edit salary
2. **Validation:** Employment type validation di frontend dan backend
3. **Activity Logging:** Semua perubahan salary di-log
4. **Input Sanitization:** Currency formatting dan validation
5. **Error Handling:** Proper error messages dan status codes
