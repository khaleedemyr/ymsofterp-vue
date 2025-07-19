# Status Aktif Update Documentation

## Overview
Perubahan logika status aktif member dari menggunakan `Y/N` menjadi `1/0` untuk konsistensi dengan standar database.

## Perubahan yang Dilakukan

### 1. Database Logic
- **Sebelum**: `status_aktif = 'Y'` (Aktif), `status_aktif = 'N'` (Tidak Aktif)
- **Sesudah**: `status_aktif = '1'` (Aktif), `status_aktif = '0'` (Tidak Aktif)

### 2. Files Modified

#### A. Controller (`app/Http/Controllers/MemberController.php`)
```php
// Statistics calculation
'active_members' => Customer::where('status_aktif', '1')->count(),

// Validation rules
'status_aktif' => 'required|in:1,0',

// Toggle function
$member->update([
    'status_aktif' => $member->status_aktif === '1' ? '0' : '1'
]);
```

#### B. Model (`app/Models/Customer.php`)
```php
// Scope for active members
public function scopeActive($query)
{
    return $query->where('status_aktif', '1');
}

// Scope for status filtering
public function scopeByStatus($query, $status)
{
    if ($status === 'active') {
        return $query->where('status_aktif', '1');
    } elseif ($status === 'inactive') {
        return $query->where('status_aktif', '0');
    }
    return $query;
}

// Accessor for readable status
public function getStatusAktifTextAttribute()
{
    return $this->status_aktif === '1' ? 'Aktif' : 'Tidak Aktif';
}
```

#### C. Vue Components

##### Index.vue (`resources/js/Pages/Members/Index.vue`)
```vue
<!-- Toggle button logic -->
<button @click="toggleStatus(member)" :class="[
  'px-2 py-1 rounded transition',
  member.status_aktif === '1' 
    ? 'bg-orange-100 text-orange-700 hover:bg-orange-200' 
    : 'bg-green-100 text-green-700 hover:bg-green-200'
]" :title="member.status_aktif === '1' ? 'Nonaktifkan' : 'Aktifkan'">
  <i :class="member.status_aktif === '1' ? 'fa-solid fa-user-slash' : 'fa-solid fa-user-check'"></i>
</button>
```

##### Create.vue (`resources/js/Pages/Members/Create.vue`)
```vue
<!-- Form default value -->
const form = ref({
  // ... other fields
  status_aktif: '1', // Default to active
  // ... other fields
});

<!-- Select options -->
<select v-model="form.status_aktif">
  <option value="1">Aktif</option>
  <option value="0">Tidak Aktif</option>
</select>
```

##### Edit.vue (`resources/js/Pages/Members/Edit.vue`)
```vue
<!-- Form default value -->
const form = ref({
  // ... other fields
  status_aktif: '1', // Default to active
  // ... other fields
});

<!-- Select options -->
<select v-model="form.status_aktif">
  <option value="1">Aktif</option>
  <option value="0">Tidak Aktif</option>
</select>
```

## Migration Guide

### Jika Ada Data Existing
Jika database sudah memiliki data dengan format lama (`Y/N`), perlu melakukan migration:

```sql
-- Update existing data from Y/N to 1/0
UPDATE costumers SET status_aktif = '1' WHERE status_aktif = 'Y';
UPDATE costumers SET status_aktif = '0' WHERE status_aktif = 'N';
```

### Laravel Migration (Optional)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateStatusAktifValues extends Migration
{
    public function up()
    {
        DB::connection('mysql_second')->statement("
            UPDATE costumers 
            SET status_aktif = CASE 
                WHEN status_aktif = 'Y' THEN '1'
                WHEN status_aktif = 'N' THEN '0'
                ELSE status_aktif
            END
        ");
    }

    public function down()
    {
        DB::connection('mysql_second')->statement("
            UPDATE costumers 
            SET status_aktif = CASE 
                WHEN status_aktif = '1' THEN 'Y'
                WHEN status_aktif = '0' THEN 'N'
                ELSE status_aktif
            END
        ");
    }
}
```

## Testing

### Unit Tests
```php
// Test status aktif logic
public function test_status_aktif_values()
{
    $activeMember = Customer::factory()->create(['status_aktif' => '1']);
    $inactiveMember = Customer::factory()->create(['status_aktif' => '0']);

    $this->assertEquals('Aktif', $activeMember->status_aktif_text);
    $this->assertEquals('Tidak Aktif', $inactiveMember->status_aktif_text);
}

public function test_active_scope()
{
    Customer::factory()->create(['status_aktif' => '1']);
    Customer::factory()->create(['status_aktif' => '0']);

    $activeCount = Customer::active()->count();
    $this->assertEquals(1, $activeCount);
}
```

### Manual Testing
1. **Create Member**: Pastikan default status adalah "Aktif" (1)
2. **Edit Member**: Pastikan bisa mengubah status antara Aktif (1) dan Tidak Aktif (0)
3. **Toggle Status**: Pastikan tombol toggle berfungsi dengan benar
4. **Filter**: Pastikan filter "Aktif" dan "Tidak Aktif" berfungsi
5. **Statistics**: Pastikan card "Member Aktif" menampilkan jumlah yang benar

## Benefits

### 1. Consistency
- Menggunakan format angka standar database
- Konsisten dengan field boolean lainnya

### 2. Performance
- Query lebih efisien dengan angka
- Indexing lebih optimal

### 3. Maintainability
- Kode lebih mudah dipahami
- Mengurangi konfusi antara string dan boolean

## Rollback Plan

Jika perlu rollback ke format lama:

### 1. Database
```sql
UPDATE costumers SET status_aktif = 'Y' WHERE status_aktif = '1';
UPDATE costumers SET status_aktif = 'N' WHERE status_aktif = '0';
```

### 2. Code Changes
- Ubah semua `'1'` menjadi `'Y'`
- Ubah semua `'0'` menjadi `'N'`
- Update validation rules
- Update scope dan accessor methods

## Impact Analysis

### Affected Features
- ✅ Member listing dengan filter status
- ✅ Member creation dengan default status
- ✅ Member editing dengan status change
- ✅ Status toggle functionality
- ✅ Statistics calculation
- ✅ Export functionality

### Not Affected
- Status block (tetap menggunakan Y/N)
- Exclusive member (tetap menggunakan Y/N)
- Other member fields

## Monitoring

### Key Metrics to Watch
1. **Error Rate**: Monitor error logs untuk validasi status_aktif
2. **Data Integrity**: Pastikan tidak ada data dengan nilai invalid
3. **User Experience**: Monitor feedback terkait status member

### Logging
```php
// Add logging for status changes
Log::info('Member status changed', [
    'member_id' => $member->id,
    'old_status' => $oldStatus,
    'new_status' => $member->status_aktif,
    'user_id' => auth()->id()
]);
```

---

**Status**: ✅ Implemented  
**Last Updated**: January 2024  
**Version**: 2.0.0  
**Migration Required**: Yes (if existing data exists) 