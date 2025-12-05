# User Shift Absent Blocking Feature

## Feature Description
Menambahkan fitur blocking tanggal yang sudah ada absen di menu "Input Shift Mingguan Karyawan". Fitur ini mencegah user untuk mengatur shift pada tanggal dimana karyawan sudah memiliki approved absent request.

## Implementation Details

### **Files Modified**

#### **`resources/js/Pages/UserShift/Index.vue`**

### **1. Added Props**
```javascript
const props = defineProps({
  outlets: Array,
  divisions: Array,
  users: Array,
  shifts: Array,
  dates: Array,
  userShifts: Array,
  filter: Object,
  holidays: Array,
  approvedAbsents: Array,  // ✅ ADDED
});
```

### **2. Added Computed Property**
```javascript
// Computed property untuk mendapatkan approved absent berdasarkan tanggal dan user
const getApprovedAbsentForDate = computed(() => {
  return (dateString, userId) => {
    if (!props.approvedAbsents) return null
    
    const date = new Date(dateString).toISOString().split('T')[0]
    
    return props.approvedAbsents.find(absent => {
      const fromDate = new Date(absent.date_from).toISOString().split('T')[0]
      const toDate = new Date(absent.date_to).toISOString().split('T')[0]
      
      return absent.user_id === userId && date >= fromDate && date <= toDate
    })
  }
});
```

### **3. Updated Template with Blocking Logic**
```vue
<td v-for="tgl in dates" :key="tgl"
  :class="[
    'px-4 py-2 text-center',
    isLibur(tgl) ? 'holiday-cell' : '',
    getApprovedAbsentForDate(tgl, user.id) ? 'bg-green-50' : ''  // ✅ Background highlight
  ]"
  :title="getApprovedAbsentForDate(tgl, user.id) ? 
    `Sudah ada absen: ${getApprovedAbsentForDate(tgl, user.id).leave_type_name} - ${getApprovedAbsentForDate(tgl, user.id).reason}` : 
    (holidayMap.value && holidayMap.value[tglKey(tgl)] ? holidayMap.value[tglKey(tgl)] : '')"  // ✅ Tooltip
>
  <!-- Approved Absent Indicator -->
  <div v-if="getApprovedAbsentForDate(tgl, user.id)" class="mb-1">  <!-- ✅ Visual indicator -->
    <div class="w-full text-xs bg-green-500 text-white px-1 py-0.5 rounded">
      <i class="fa-solid fa-check-circle sm:mr-1"></i>
      <span class="hidden sm:inline">{{ getApprovedAbsentForDate(tgl, user.id).leave_type_name }}</span>
      <span class="sm:hidden">✓</span>
    </div>
    <div class="text-xs text-green-600 mt-0.5">
      {{ getApprovedAbsentForDate(tgl, user.id).reason }}
    </div>
  </div>
  
  <!-- Shift Select - Disabled if has approved absent -->
  <select 
    v-model="form.shifts[user.id][tgl]" 
    :disabled="getApprovedAbsentForDate(tgl, user.id)"  // ✅ Disable select
    :class="[
      'form-input rounded',
      getApprovedAbsentForDate(tgl, user.id) ? 'bg-gray-100 cursor-not-allowed opacity-50' : ''  // ✅ Visual disabled state
    ]"
  >
    <option :value="null">OFF</option>
    <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.shift_name }}</option>
  </select>
</td>
```

## Features Implemented

### **1. Date Blocking Logic**
- ✅ **Check Approved Absent**: Validates if user has approved absent for specific date
- ✅ **Date Range Support**: Handles absent requests that span multiple days
- ✅ **User-Specific**: Each user's absent status is checked individually

### **2. Visual Indicators**
- ✅ **Background Highlight**: Green background (`bg-green-50`) for blocked dates
- ✅ **Absent Badge**: Green badge showing leave type name
- ✅ **Reason Display**: Shows absent reason below the badge
- ✅ **Tooltip**: Hover tooltip with detailed absent information

### **3. UI/UX Improvements**
- ✅ **Disabled Select**: Shift dropdown is disabled for blocked dates
- ✅ **Visual Feedback**: Gray background and opacity for disabled state
- ✅ **Cursor Indication**: `cursor-not-allowed` for disabled elements
- ✅ **Responsive Design**: Different display for mobile vs desktop

### **4. Data Structure Expected**
```javascript
// Expected approvedAbsents data structure
approvedAbsents: [
  {
    user_id: 123,
    date_from: '2024-01-15',
    date_to: '2024-01-17',
    leave_type_name: 'Cuti Tahunan',
    reason: 'Liburan keluarga'
  },
  // ... more absent records
]
```

## Backend Integration Required

### **Controller Update Needed**
The backend controller for UserShift needs to be updated to pass `approvedAbsents` data:

```php
// In UserShiftController@index method
public function index(Request $request)
{
    // ... existing code ...
    
    // Get approved absents for the selected users and date range
    $approvedAbsents = DB::table('absent_requests')
        ->whereIn('user_id', $userIds)  // Users in the selected division
        ->where('status', 'approved')
        ->where(function($query) use ($startDate, $endDate) {
            $query->whereBetween('date_from', [$startDate, $endDate])
                  ->orWhereBetween('date_to', [$startDate, $endDate])
                  ->orWhere(function($q) use ($startDate, $endDate) {
                      $q->where('date_from', '<=', $startDate)
                        ->where('date_to', '>=', $endDate);
                  });
        })
        ->select('user_id', 'date_from', 'date_to', 'leave_type_name', 'reason')
        ->get();
    
    return inertia('UserShift/Index', [
        // ... existing props ...
        'approvedAbsents' => $approvedAbsents,
    ]);
}
```

## User Experience

### **Before Implementation:**
- User bisa mengatur shift pada tanggal dimana karyawan sudah absen
- Tidak ada indikasi visual bahwa tanggal tersebut sudah ada absen
- Bisa terjadi konflik antara shift schedule dan absent request

### **After Implementation:**
- ✅ **Clear Visual Feedback**: User langsung tahu tanggal mana yang sudah ada absen
- ✅ **Prevented Conflicts**: Tidak bisa mengatur shift pada tanggal absen
- ✅ **Informative Display**: Menampilkan jenis cuti dan alasan absen
- ✅ **Consistent UX**: Mengikuti pattern yang sama dengan menu Attendance

## Benefits

### **1. Data Integrity**
- ✅ **Prevents Conflicts**: Eliminates scheduling conflicts between shifts and absences
- ✅ **Consistent Data**: Ensures shift schedule aligns with approved leave requests
- ✅ **Audit Trail**: Clear indication of why certain dates are blocked

### **2. User Experience**
- ✅ **Intuitive Interface**: Visual indicators make it clear which dates are unavailable
- ✅ **Informative**: Users can see the reason for blocking (leave type and reason)
- ✅ **Efficient**: No need to manually check absent requests before scheduling

### **3. Administrative Benefits**
- ✅ **Reduced Errors**: Prevents accidental scheduling conflicts
- ✅ **Better Planning**: Clear view of employee availability
- ✅ **Compliance**: Ensures adherence to approved leave requests

## Testing Scenarios

### **Test Case 1: Single Day Absent**
1. **Setup**: User has approved absent for 2024-01-15
2. **Action**: Try to set shift for 2024-01-15
3. **Expected**: Select dropdown disabled, green indicator shown

### **Test Case 2: Multi-Day Absent**
1. **Setup**: User has approved absent from 2024-01-15 to 2024-01-17
2. **Action**: Try to set shift for any date in range
3. **Expected**: All dates in range blocked with indicators

### **Test Case 3: No Absent**
1. **Setup**: User has no approved absent for the date range
2. **Action**: Try to set shift for any date
3. **Expected**: Normal shift selection available

### **Test Case 4: Different Users**
1. **Setup**: User A has absent, User B doesn't
2. **Action**: Check shift selection for both users
3. **Expected**: Only User A's dates blocked, User B's dates available

## Future Enhancements

1. **Pending Absent Requests**: Also block dates with pending absent requests
2. **Shift Override**: Allow admin to override blocked dates with confirmation
3. **Bulk Operations**: Handle bulk shift assignments with absent validation
4. **Notifications**: Alert when trying to schedule on blocked dates
5. **Export/Import**: Include absent information in shift schedule exports
