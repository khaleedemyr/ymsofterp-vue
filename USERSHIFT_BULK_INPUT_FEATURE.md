# UserShift Bulk Input Feature

## ✅ **Implementation Status: COMPLETE**

Fitur bulk input untuk menu "Input Shift Mingguan Karyawan" telah berhasil diimplementasikan untuk memudahkan pengisian shift secara massal.

## 🎯 **Feature Summary**

### **What It Does**
- **Bulk shift assignment** untuk multiple karyawan dan tanggal sekaligus
- **Flexible selection** - bisa pilih semua karyawan/tanggal atau pilih spesifik
- **Smart validation** - otomatis skip tanggal yang sudah lewat atau ada absen
- **User-friendly interface** - form yang mudah digunakan dengan toggle expand/collapse

### **Use Cases**
- **Single shift teams** - Karyawan yang hanya punya 1 shift kerja
- **Consistent schedules** - Jadwal yang sama untuk beberapa karyawan
- **Quick setup** - Setup jadwal mingguan dengan cepat
- **Bulk updates** - Update shift untuk multiple karyawan sekaligus

## 🔧 **Technical Implementation**

### **Frontend Changes** (`resources/js/Pages/UserShift/Index.vue`)

#### **1. Added Bulk Input State Management**
```javascript
// Bulk input functionality
const showBulkInput = ref(false);
const bulkInput = ref({
  shift_id: null,
  selectedUsers: [],
  selectedDates: [],
  applyToAllUsers: false,
  applyToAllDates: false
});
```

#### **2. Added Bulk Input Functions**
```javascript
function toggleBulkInput() {
  showBulkInput.value = !showBulkInput.value;
  if (!showBulkInput.value) {
    resetBulkInput();
  }
}

function resetBulkInput() {
  bulkInput.value = {
    shift_id: null,
    selectedUsers: [],
    selectedDates: [],
    applyToAllUsers: false,
    applyToAllDates: false
  };
}

function applyBulkInput() {
  // Validation logic
  if (!bulkInput.value.shift_id) {
    Swal.fire('Pilih Shift', 'Silakan pilih shift yang akan diterapkan!', 'warning');
    return;
  }

  // Apply bulk input with smart filtering
  const usersToApply = bulkInput.value.applyToAllUsers ? props.users : props.users.filter(u => bulkInput.value.selectedUsers.includes(u.id));
  const datesToApply = bulkInput.value.applyToAllDates ? props.dates : bulkInput.value.selectedDates;

  let appliedCount = 0;
  let skippedCount = 0;

  usersToApply.forEach(user => {
    datesToApply.forEach(date => {
      // Skip if date is past or user has approved absent
      if (isPastDate(date) || getApprovedAbsentForDate(date, user.id)) {
        skippedCount++;
        return;
      }

      // Apply shift
      form.shifts[user.id][date] = bulkInput.value.shift_id;
      appliedCount++;
    });
  });

  // Show result with statistics
  let message = `Berhasil menerapkan shift ke ${appliedCount} slot jadwal.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} slot dilewati (tanggal lewat atau ada absen).`;
  }

  Swal.fire('Bulk Input Berhasil', message, 'success');
  resetBulkInput();
  showBulkInput.value = false;
}
```

#### **3. Added Computed Properties**
```javascript
const availableDates = computed(() => {
  return props.dates.filter(date => !isPastDate(date));
});

const availableUsers = computed(() => {
  return props.users;
});
```

#### **4. Added Bulk Input UI**
```vue
<!-- Bulk Input Section -->
<div v-if="users.length && dates.length" class="mb-6">
  <div class="bg-white rounded-2xl shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-800 flex items-center">
        <i class="fa-solid fa-layer-group mr-2 text-blue-600"></i>
        Bulk Input Shift
      </h3>
      <button 
        type="button"
        @click="toggleBulkInput"
        :class="[
          'px-4 py-2 rounded-lg font-medium transition-colors',
          showBulkInput ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
        ]"
      >
        <i :class="showBulkInput ? 'fa-solid fa-times' : 'fa-solid fa-plus'" class="mr-2"></i>
        {{ showBulkInput ? 'Tutup' : 'Bulk Input' }}
      </button>
    </div>
    
    <div v-if="showBulkInput" class="space-y-4">
      <!-- Shift Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Pilih Shift <span class="text-red-500">*</span>
        </label>
        <select v-model="bulkInput.shift_id" class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
          <option :value="null">-- Pilih Shift --</option>
          <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
            {{ shift.shift_name }} ({{ shift.time_start }} - {{ shift.time_end }})
          </option>
        </select>
      </div>
      
      <!-- User Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Pilih Karyawan
        </label>
        <div class="space-y-2">
          <label class="flex items-center">
            <input type="checkbox" v-model="bulkInput.applyToAllUsers" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="ml-2 text-sm text-gray-700">Terapkan ke Semua Karyawan</span>
          </label>
          <div v-if="!bulkInput.applyToAllUsers" class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-2">
            <label v-for="user in availableUsers" :key="user.id" class="flex items-center py-1">
              <input type="checkbox" :value="user.id" v-model="bulkInput.selectedUsers" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
              <span class="ml-2 text-sm text-gray-700">{{ user.nama_lengkap }}</span>
            </label>
          </div>
        </div>
      </div>
      
      <!-- Date Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Pilih Tanggal
        </label>
        <div class="space-y-2">
          <label class="flex items-center">
            <input type="checkbox" v-model="bulkInput.applyToAllDates" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="ml-2 text-sm text-gray-700">Terapkan ke Semua Tanggal (kecuali yang sudah lewat)</span>
          </label>
          <div v-if="!bulkInput.applyToAllDates" class="flex flex-wrap gap-2">
            <label v-for="date in availableDates" :key="date" class="flex items-center">
              <input type="checkbox" :value="date" v-model="bulkInput.selectedDates" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
              <span class="ml-2 text-sm text-gray-700">{{ getDayName(date) }} ({{ date }})</span>
            </label>
          </div>
        </div>
      </div>
      
      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3 pt-4 border-t">
        <button type="button" @click="resetBulkInput" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
          <i class="fa-solid fa-undo mr-2"></i>
          Reset
        </button>
        <button type="button" @click="applyBulkInput" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
          <i class="fa-solid fa-check mr-2"></i>
          Terapkan
        </button>
      </div>
    </div>
  </div>
</div>
```

## 🎨 **User Interface**

### **Bulk Input Panel**
```
┌─────────────────────────────────────────────────────────┐
│  📊 Bulk Input Shift                    [Bulk Input]    │
├─────────────────────────────────────────────────────────┤
│  Pilih Shift *                                         │
│  [Dropdown: -- Pilih Shift --]                         │
│                                                         │
│  Pilih Karyawan                                        │
│  ☑ Terapkan ke Semua Karyawan                          │
│                                                         │
│  Pilih Tanggal                                         │
│  ☑ Terapkan ke Semua Tanggal (kecuali yang sudah lewat)│
│                                                         │
│  ──────────────────────────────────────────────────────│
│                                    [Reset] [Terapkan]  │
└─────────────────────────────────────────────────────────┘
```

### **Visual States**
- **Collapsed**: Blue button "Bulk Input" with plus icon
- **Expanded**: Red button "Tutup" with X icon
- **Form Fields**: Clean, organized layout with proper spacing
- **Validation**: Required fields marked with red asterisk

## 🔄 **Workflow**

### **Step 1: Open Bulk Input**
1. User clicks "Bulk Input" button
2. Panel expands showing form fields
3. Button changes to "Tutup" with X icon

### **Step 2: Configure Bulk Input**
1. **Select Shift**: Choose from available shifts (required)
2. **Select Users**: 
   - Option A: Check "Terapkan ke Semua Karyawan"
   - Option B: Select specific users from checkbox list
3. **Select Dates**:
   - Option A: Check "Terapkan ke Semua Tanggal"
   - Option B: Select specific dates from checkbox list

### **Step 3: Apply Bulk Input**
1. Click "Terapkan" button
2. System validates input
3. Applies shift to selected users/dates
4. Skips blocked dates (past dates, approved absents)
5. Shows success message with statistics

### **Step 4: Review Results**
- Success message shows:
  - Number of slots successfully updated
  - Number of slots skipped (with reason)
- Form resets automatically
- Panel collapses automatically

## 🧪 **Validation Logic**

### **Input Validation**
```javascript
// Required shift selection
if (!bulkInput.value.shift_id) {
  Swal.fire('Pilih Shift', 'Silakan pilih shift yang akan diterapkan!', 'warning');
  return;
}

// Required user selection
if (!bulkInput.value.applyToAllUsers && bulkInput.value.selectedUsers.length === 0) {
  Swal.fire('Pilih Karyawan', 'Silakan pilih karyawan atau centang "Terapkan ke Semua Karyawan"!', 'warning');
  return;
}

// Required date selection
if (!bulkInput.value.applyToAllDates && bulkInput.value.selectedDates.length === 0) {
  Swal.fire('Pilih Tanggal', 'Silakan pilih tanggal atau centang "Terapkan ke Semua Tanggal"!', 'warning');
  return;
}
```

### **Smart Filtering**
```javascript
usersToApply.forEach(user => {
  datesToApply.forEach(date => {
    // Skip if date is past or user has approved absent
    if (isPastDate(date) || getApprovedAbsentForDate(date, user.id)) {
      skippedCount++;
      return;
    }

    // Apply shift
    form.shifts[user.id][date] = bulkInput.value.shift_id;
    appliedCount++;
  });
});
```

## 📊 **Use Case Examples**

### **Example 1: Single Shift Team**
**Scenario**: Tim yang hanya punya 1 shift kerja (08:00-17:00)
**Action**:
1. Select shift "Shift Pagi (08:00-17:00)"
2. Check "Terapkan ke Semua Karyawan"
3. Check "Terapkan ke Semua Tanggal"
4. Click "Terapkan"
**Result**: All users get the same shift for all available dates

### **Example 2: Specific Users, Specific Dates**
**Scenario**: Hanya beberapa karyawan yang perlu shift khusus untuk hari tertentu
**Action**:
1. Select shift "Shift Malam (22:00-06:00)"
2. Select specific users: "John Doe", "Jane Smith"
3. Select specific dates: "Senin", "Selasa"
4. Click "Terapkan"
**Result**: Only selected users get the night shift for selected days

### **Example 3: Mixed Selection**
**Scenario**: Semua karyawan untuk hari tertentu
**Action**:
1. Select shift "Shift Siang (13:00-21:00)"
2. Check "Terapkan ke Semua Karyawan"
3. Select specific dates: "Jumat", "Sabtu"
4. Click "Terapkan"
**Result**: All users get afternoon shift for Friday and Saturday

## 🚀 **Performance Considerations**

### **Efficiency**
- ✅ **Batch Processing**: Processes multiple users/dates in single operation
- ✅ **Smart Filtering**: Automatically skips invalid combinations
- ✅ **Minimal DOM Updates**: Only updates affected form fields
- ✅ **No API Calls**: All processing done client-side

### **User Experience**
- ✅ **Instant Feedback**: Immediate visual feedback on form
- ✅ **Progress Indication**: Success message with detailed statistics
- ✅ **Error Prevention**: Validation prevents invalid operations
- ✅ **Easy Reset**: One-click reset functionality

## 🔧 **Integration with Existing Features**

### **Compatibility with Blocking Features**
- ✅ **Past Date Blocking**: Automatically skips past dates
- ✅ **Absent Blocking**: Automatically skips dates with approved absents
- ✅ **Holiday Display**: Works with existing holiday indicators
- ✅ **Individual Editing**: Bulk input doesn't interfere with individual edits

### **Form Integration**
- ✅ **Form State**: Updates main form.shifts object
- ✅ **Validation**: Uses existing form validation
- ✅ **Submission**: Works with existing form submission
- ✅ **Reset**: Integrates with form reset functionality

## 🎯 **Benefits**

### **For Users**
1. **Time Saving**: Setup weekly schedules in seconds instead of minutes
2. **Consistency**: Ensure consistent shift assignments across team
3. **Flexibility**: Choose between all users/dates or specific selections
4. **Error Reduction**: Less chance of manual input errors
5. **Efficiency**: Handle large teams with multiple shifts easily

### **For Administrators**
1. **Bulk Operations**: Manage multiple schedules simultaneously
2. **Quick Setup**: Initialize new weekly schedules quickly
3. **Pattern Recognition**: Easily apply common shift patterns
4. **Audit Trail**: Clear feedback on what was applied/skipped
5. **Validation**: Built-in validation prevents invalid assignments

## 📝 **Future Enhancements**

1. **Template System**: Save and reuse common bulk input patterns
2. **Copy from Previous Week**: Copy shifts from previous week
3. **Shift Patterns**: Predefined shift patterns (e.g., "5 days work, 2 days off")
4. **Bulk Export**: Export bulk input configurations
5. **Advanced Filtering**: Filter users by department, role, etc.
6. **Undo Functionality**: Undo last bulk input operation
7. **Scheduling Rules**: Apply business rules to bulk input (e.g., max consecutive days)

---

**🎉 Bulk Input Feature Complete!**

The UserShift bulk input feature is now fully implemented and ready for use. Users can efficiently set up weekly shift schedules for multiple employees with just a few clicks, while respecting all existing blocking rules and validations.
