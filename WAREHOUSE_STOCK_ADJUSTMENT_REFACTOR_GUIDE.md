# Warehouse Stock Adjustment - Refactor ke Approval Flow System

## Ringkasan Perubahan

Refactor sistem approval Warehouse Stock Adjustment dari **hardcoded approval berdasarkan warehouse dan jabatan** menjadi **flexible approval flow** dimana user bisa memilih approver sendiri (sama seperti Outlet Stock Adjustment).

## Keuntungan Sistem Baru

1. **Fleksibel**: User bisa pilih siapa saja sebagai approver, tidak tergantung warehouse
2. **Multi-level**: Bisa setup multiple approver dengan urutan (level 1, 2, 3, dst)
3. **Transparan**: Semua flow approval tercatat di database dengan timestamp
4. **Konsisten**: Sama dengan sistem yang sudah jalan di Outlet Stock Adjustment
5. **Mudah maintenance**: Tidak perlu hardcode jabatan di controller

## Langkah Implementasi

### 1. Jalankan Query SQL

File: `food_inventory_adjustment_approval_flow.sql`

```bash
# Login ke MySQL
mysql -u username -p database_name < food_inventory_adjustment_approval_flow.sql
```

Query ini akan:
- Membuat tabel `food_inventory_adjustment_approval_flows`
- Migrate data existing (yang masih waiting approval) ke tabel approval flows
- Mark yang sudah approved dengan status APPROVED

### 2. Backup Controller Lama

```bash
cd app/Http/Controllers
cp FoodInventoryAdjustmentController.php FoodInventoryAdjustmentController_BACKUP.php
```

### 3. Replace Controller

Copy file `FoodInventoryAdjustmentController_REFACTORED.php` ke `FoodInventoryAdjustmentController.php`

```bash
cp FoodInventoryAdjustmentController_REFACTORED.php FoodInventoryAdjustmentController.php
```

### 4. Update Frontend (Vue)

#### A. Form Create/Edit

File: `resources/js/Pages/FoodInventoryAdjustment/Form.vue`

**Tambahkan field approvers:**

```vue
<template>
  <!-- Existing fields... -->
  
  <!-- Approvers Selection -->
  <div class="form-group">
    <label class="required">Approvers</label>
    <approver-multi-select
      v-model="form.approvers"
      :errors="errors.approvers"
    />
    <small class="text-muted">
      Pilih approver sesuai urutan (Level 1 approve dulu, baru Level 2, dst)
    </small>
  </div>
</template>

<script>
import ApproverMultiSelect from '@/Components/ApproverMultiSelect.vue';

export default {
  components: {
    ApproverMultiSelect,
  },
  data() {
    return {
      form: {
        // ... existing fields
        approvers: [], // Array of approver IDs
      },
    };
  },
  methods: {
    submit() {
      // Validate approvers
      if (this.form.approvers.length === 0) {
        alert('Pilih minimal 1 approver');
        return;
      }
      
      this.$inertia.post('/food-inventory-adjustment', this.form);
    },
  },
};
</script>
```

#### B. Show/Detail Page

File: `resources/js/Pages/FoodInventoryAdjustment/Show.vue`

**Tampilkan approval flows:**

```vue
<template>
  <div>
    <!-- Existing content... -->
    
    <!-- Approval Flow Section -->
    <div class="card mt-3">
      <div class="card-header">
        <h5>Approval Flow</h5>
      </div>
      <div class="card-body">
        <div v-if="adjustment.approval_flows && adjustment.approval_flows.length > 0">
          <div
            v-for="flow in adjustment.approval_flows"
            :key="flow.id"
            class="approval-flow-item d-flex align-items-center mb-3"
          >
            <div class="approval-level">
              <span class="badge badge-secondary">Level {{ flow.approval_level }}</span>
            </div>
            <div class="approval-info flex-grow-1 ml-3">
              <div>
                <strong>{{ flow.approver_name }}</strong>
                <span v-if="flow.approver_jabatan" class="text-muted">
                  ({{ flow.approver_jabatan }})
                </span>
              </div>
              <div v-if="flow.status === 'APPROVED'" class="text-success">
                <i class="fas fa-check-circle"></i>
                Approved pada {{ formatDate(flow.approved_at) }}
              </div>
              <div v-else-if="flow.status === 'REJECTED'" class="text-danger">
                <i class="fas fa-times-circle"></i>
                Rejected pada {{ formatDate(flow.approved_at) }}
              </div>
              <div v-else class="text-warning">
                <i class="fas fa-clock"></i>
                Pending
              </div>
              <div v-if="flow.notes" class="text-muted mt-1">
                <small>Note: {{ flow.notes }}</small>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-muted">
          <em>No approval flow found</em>
        </div>
      </div>
    </div>
    
    <!-- Approve/Reject Buttons -->
    <div v-if="can_approve && adjustment.status === 'waiting_approval'" class="mt-3">
      <button @click="approveAdjustment" class="btn btn-success">
        <i class="fas fa-check"></i> Approve
      </button>
      <button @click="rejectAdjustment" class="btn btn-danger ml-2">
        <i class="fas fa-times"></i> Reject
      </button>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    adjustment: Object,
    user: Object,
    approval_flows: Array,
    current_approval_flow_id: Number,
    can_approve: Boolean,
  },
  methods: {
    approveAdjustment() {
      const note = prompt('Catatan approval (optional):');
      this.$inertia.post(`/food-inventory-adjustment/${this.adjustment.id}/approve`, {
        note: note,
      });
    },
    rejectAdjustment() {
      const note = prompt('Alasan reject:');
      if (!note) {
        alert('Alasan reject wajib diisi');
        return;
      }
      this.$inertia.post(`/food-inventory-adjustment/${this.adjustment.id}/reject`, {
        note: note,
      });
    },
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleString('id-ID');
    },
  },
};
</script>

<style scoped>
.approval-flow-item {
  padding: 15px;
  border: 1px solid #e0e0e0;
  border-radius: 5px;
  background-color: #f9f9f9;
}
.approval-level {
  min-width: 80px;
  text-align: center;
}
</style>
```

#### C. Component ApproverMultiSelect

Jika belum ada, buat component untuk select approver:

File: `resources/js/Components/ApproverMultiSelect.vue`

```vue
<template>
  <div>
    <div v-for="(approverId, index) in localApprovers" :key="index" class="d-flex align-items-center mb-2">
      <span class="badge badge-secondary mr-2">Level {{ index + 1 }}</span>
      <select
        v-model="localApprovers[index]"
        @change="updateApprovers"
        class="form-control"
        :class="{ 'is-invalid': errors && errors[`approvers.${index}`] }"
      >
        <option value="">-- Pilih Approver --</option>
        <option v-for="user in users" :key="user.id" :value="user.id">
          {{ user.name }} {{ user.jabatan ? `(${user.jabatan})` : '' }}
        </option>
      </select>
      <button
        v-if="index > 0"
        @click="removeApprover(index)"
        type="button"
        class="btn btn-sm btn-danger ml-2"
      >
        <i class="fas fa-trash"></i>
      </button>
    </div>
    
    <button @click="addApprover" type="button" class="btn btn-sm btn-secondary">
      <i class="fas fa-plus"></i> Tambah Approver
    </button>
    
    <div v-if="errors" class="invalid-feedback d-block">
      {{ errors }}
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  props: {
    modelValue: {
      type: Array,
      default: () => [],
    },
    errors: {
      type: [String, Object],
      default: null,
    },
  },
  data() {
    return {
      localApprovers: this.modelValue.length > 0 ? [...this.modelValue] : [''],
      users: [],
    };
  },
  mounted() {
    this.loadApprovers();
  },
  methods: {
    async loadApprovers() {
      try {
        const response = await axios.get('/api/food-inventory-adjustment/approvers');
        this.users = response.data.users;
      } catch (error) {
        console.error('Error loading approvers:', error);
      }
    },
    addApprover() {
      this.localApprovers.push('');
      this.updateApprovers();
    },
    removeApprover(index) {
      this.localApprovers.splice(index, 1);
      this.updateApprovers();
    },
    updateApprovers() {
      // Remove empty values
      const filtered = this.localApprovers.filter(id => id !== '');
      this.$emit('update:modelValue', filtered);
    },
  },
};
</script>
```

### 5. Update Routes

File: `routes/web.php`

Tambahkan route untuk get approvers:

```php
// Warehouse Stock Adjustment
Route::get('/api/food-inventory-adjustment/approvers', [FoodInventoryAdjustmentController::class, 'getApprovers']);
Route::post('/food-inventory-adjustment/{id}/approve', [FoodInventoryAdjustmentController::class, 'approve']);
Route::post('/food-inventory-adjustment/{id}/reject', [FoodInventoryAdjustmentController::class, 'reject']);
```

### 6. Testing

#### Test Case 1: Create New Adjustment with Approvers

1. Buka form create adjustment
2. Isi data adjustment (warehouse, type, items)
3. Pilih approvers (minimal 1, bisa lebih)
4. Submit
5. Cek approval muncul di first approver

#### Test Case 2: Approve Flow (Multi-level)

1. Login sebagai approver level 1
2. Approve adjustment
3. Cek notifikasi terkirim ke approver level 2
4. Login sebagai approver level 2
5. Approve adjustment
6. Cek status menjadi approved dan inventory ter-update

#### Test Case 3: Reject Flow

1. Login sebagai approver
2. Reject adjustment dengan note
3. Cek status menjadi rejected
4. Cek inventory tidak berubah

#### Test Case 4: Superadmin Can Approve Any

1. Login sebagai superadmin
2. Buka adjustment yang pending
3. Superadmin bisa approve meski bukan assigned approver

## Struktur Database Baru

### Tabel: food_inventory_adjustment_approval_flows

| Field          | Type                            | Description                    |
|----------------|---------------------------------|--------------------------------|
| id             | bigint(20) UNSIGNED             | Primary key                    |
| adjustment_id  | bigint(20) UNSIGNED             | FK ke food_inventory_adjustments |
| approver_id    | bigint(20) UNSIGNED             | FK ke users                    |
| approval_level | int(11)                         | 1, 2, 3, dst (urutan approval)  |
| status         | enum('PENDING','APPROVED','REJECTED') | Status approval          |
| approved_at    | datetime                        | Timestamp approval             |
| notes          | text                            | Catatan approver               |
| created_at     | timestamp                       | Created timestamp              |
| updated_at     | timestamp                       | Updated timestamp              |

## Backward Compatibility

### Adjustment Lama (Sebelum Refactor)

Adjustment yang dibuat sebelum refactor tidak punya approval_flows. Untuk handle ini:

1. Query SQL sudah migrate data yang masih waiting ke approval_flows
2. Controller sudah cek apakah ada approval_flows atau tidak
3. Jika tidak ada, throw error dengan pesan jelas

## Rollback Plan (Jika Ada Masalah)

### Rollback Steps:

1. **Restore controller lama:**
   ```bash
   cp FoodInventoryAdjustmentController_BACKUP.php FoodInventoryAdjustmentController.php
   ```

2. **Revert frontend changes** (restore dari git)

3. **Keep database table** (jangan drop, untuk audit trail)

4. **Update status manually** jika perlu:
   ```sql
   -- Update status berdasarkan approval_flows
   UPDATE food_inventory_adjustments fia
   SET fia.status = 'approved'
   WHERE fia.id IN (
       SELECT af.adjustment_id 
       FROM food_inventory_adjustment_approval_flows af 
       WHERE af.status = 'APPROVED'
       GROUP BY af.adjustment_id
       HAVING COUNT(*) = (
           SELECT COUNT(*) FROM food_inventory_adjustment_approval_flows af2 
           WHERE af2.adjustment_id = af.adjustment_id
       )
   );
   ```

## Catatan Penting

1. **Jangan hapus field lama** di tabel `food_inventory_adjustments`:
   - `approved_by_assistant_ssd_manager`
   - `approved_by_ssd_manager`
   - `approved_by_cost_control_manager`
   - Dll.
   
   Keep untuk backward compatibility dan audit trail.

2. **Notifikasi**: Pastikan `NotificationService` sudah jalan dengan baik

3. **Permission**: Superadmin tetap bisa approve any level

4. **Validation**: Frontend dan backend sama-sama validasi minimal 1 approver

## Support

Jika ada masalah saat implementasi:
1. Cek log di `storage/logs/laravel.log`
2. Cek query di tabel `food_inventory_adjustment_approval_flows`
3. Test dengan data dummy dulu

## Changelog

### Version 2.0 (Refactor to Approval Flow)
- **Added**: Flexible approval flow system
- **Added**: User can choose approvers
- **Added**: Multi-level approval support
- **Removed**: Hardcoded warehouse-based approval logic
- **Changed**: Approval process now uses approval_flows table
- **Improved**: Consistency with Outlet Stock Adjustment

### Version 1.0 (Old System)
- Hardcoded approval based on warehouse (MK vs non-MK)
- Fixed approval hierarchy (Asisten SSD → SSD → Cost Control)
