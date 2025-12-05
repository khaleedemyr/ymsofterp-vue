# UserShift Backend - Approved Absents Integration

## Update Description
Menambahkan logic untuk mengambil data approved absents di UserShiftController dan mengirimkannya ke frontend untuk fitur blocking tanggal yang sudah ada absen.

## Files Modified

### **`app/Http/Controllers/UserShiftController.php`**

#### **1. Added Approved Absents Query Logic**
```php
$holidays = collect();
$approvedAbsents = collect();  // ✅ ADDED
if (!empty($dates)) {
    $holidays = DB::table('tbl_kalender_perusahaan')
        ->whereIn('tgl_libur', $dates)
        ->select('tgl_libur as date', 'keterangan as name')
        ->get();
    
    // Get approved absents for the selected users and date range
    if ($users->isNotEmpty()) {  // ✅ ADDED
        $userIds = $users->pluck('id');
        $startDate = min($dates);
        $endDate = max($dates);
        
        $approvedAbsents = DB::table('absent_requests')
            ->whereIn('user_id', $userIds)
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
    }
}
```

#### **2. Added Approved Absents to Return Data**
```php
return Inertia::render('UserShift/Index', [
    'outlets' => $outlets,
    'divisions' => $divisions,
    'users' => $users,
    'shifts' => $shifts,
    'dates' => $dates,
    'userShifts' => $userShifts,
    'holidays' => $holidays,
    'approvedAbsents' => $approvedAbsents,  // ✅ ADDED
    'filter' => [
        'outlet_id' => $outletId,
        'division_id' => $divisionId,
        'start_date' => $startDate,
    ],
]);
```

#### **3. Added Debug Logging**
```php
\Log::info('USERSHIFT_INDEX_DATES', $dates);
\Log::info('USERSHIFT_INDEX_HOLIDAYS', $holidays->toArray());
\Log::info('USERSHIFT_INDEX_APPROVED_ABSENTS', $approvedAbsents->toArray());  // ✅ ADDED
```

## Technical Details

### **Query Logic Explanation**

#### **1. User Filtering**
```php
$userIds = $users->pluck('id');
```
- Mengambil semua user ID dari users yang sudah difilter berdasarkan outlet dan divisi

#### **2. Date Range Calculation**
```php
$startDate = min($dates);
$endDate = max($dates);
```
- `$dates` adalah array 7 hari (Senin-Minggu)
- `min($dates)` = tanggal pertama (Senin)
- `max($dates)` = tanggal terakhir (Minggu)

#### **3. Absent Request Query**
```php
$approvedAbsents = DB::table('absent_requests')
    ->whereIn('user_id', $userIds)           // Filter by selected users
    ->where('status', 'approved')            // Only approved requests
    ->where(function($query) use ($startDate, $endDate) {
        // Complex date range logic
    })
    ->select('user_id', 'date_from', 'date_to', 'leave_type_name', 'reason')
    ->get();
```

#### **4. Date Range Logic**
```php
->where(function($query) use ($startDate, $endDate) {
    $query->whereBetween('date_from', [$startDate, $endDate])      // Case 1: Absent starts within week
          ->orWhereBetween('date_to', [$startDate, $endDate])      // Case 2: Absent ends within week
          ->orWhere(function($q) use ($startDate, $endDate) {      // Case 3: Absent spans entire week
              $q->where('date_from', '<=', $startDate)
                ->where('date_to', '>=', $endDate);
          });
})
```

**Case Examples:**
- **Case 1**: Absent 2024-01-15 to 2024-01-17, Week 2024-01-15 to 2024-01-21 → **MATCH**
- **Case 2**: Absent 2024-01-20 to 2024-01-22, Week 2024-01-15 to 2024-01-21 → **MATCH**
- **Case 3**: Absent 2024-01-10 to 2024-01-25, Week 2024-01-15 to 2024-01-21 → **MATCH**

### **Data Structure Returned**
```php
// Example approvedAbsents data
[
    {
        "user_id": 2,
        "date_from": "2025-09-27",
        "date_to": "2025-09-27",
        "leave_type_name": "Cuti Tahunan",
        "reason": "Liburan keluarga"
    },
    {
        "user_id": 5,
        "date_from": "2025-09-28",
        "date_to": "2025-09-30",
        "leave_type_name": "Sakit",
        "reason": "Demam tinggi"
    }
]
```

## Integration with Frontend

### **Frontend Expected Data**
The frontend `getApprovedAbsentForDate` computed property expects this exact structure:

```javascript
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

### **Frontend Usage**
```vue
<!-- Check if user has approved absent for specific date -->
<div v-if="getApprovedAbsentForDate(tgl, user.id)">
  <!-- Show absent indicator -->
  <div class="w-full text-xs bg-green-500 text-white px-1 py-0.5 rounded">
    <i class="fa-solid fa-check-circle sm:mr-1"></i>
    <span class="hidden sm:inline">{{ getApprovedAbsentForDate(tgl, user.id).leave_type_name }}</span>
    <span class="sm:hidden">✓</span>
  </div>
  <div class="text-xs text-green-600 mt-0.5">
    {{ getApprovedAbsentForDate(tgl, user.id).reason }}
  </div>
</div>

<!-- Disable shift select if has approved absent -->
<select 
  v-model="form.shifts[user.id][tgl]" 
  :disabled="getApprovedAbsentForDate(tgl, user.id)"
  :class="[
    'form-input rounded',
    getApprovedAbsentForDate(tgl, user.id) ? 'bg-gray-100 cursor-not-allowed opacity-50' : ''
  ]"
>
  <option :value="null">OFF</option>
  <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.shift_name }}</option>
</select>
```

## Performance Considerations

### **1. Query Optimization**
- ✅ **Indexed Fields**: `user_id`, `status`, `date_from`, `date_to` should be indexed
- ✅ **Selective Fields**: Only selecting needed fields (`user_id`, `date_from`, `date_to`, `leave_type_name`, `reason`)
- ✅ **Conditional Query**: Only runs when users and dates are available

### **2. Caching Strategy**
- **Current**: No caching (real-time data)
- **Future**: Could implement Redis caching for approved absents
- **Consideration**: Absent data changes infrequently, good candidate for caching

### **3. Memory Usage**
- **Minimal Impact**: Only loads 7 days of data per request
- **User Limit**: Limited by users in selected outlet/division
- **Data Size**: Small JSON payload per absent record

## Testing Scenarios

### **Test Case 1: Single Day Absent**
1. **Setup**: User ID 2 has approved absent for 2025-09-27
2. **Action**: Load UserShift page for week containing 2025-09-27
3. **Expected**: `approvedAbsents` contains record for user_id=2, date_from=2025-09-27, date_to=2025-09-27

### **Test Case 2: Multi-Day Absent**
1. **Setup**: User ID 5 has approved absent from 2025-09-28 to 2025-09-30
2. **Action**: Load UserShift page for week containing these dates
3. **Expected**: `approvedAbsents` contains record for user_id=5, date_from=2025-09-28, date_to=2025-09-30

### **Test Case 3: No Absent**
1. **Setup**: No users have approved absent for the selected week
2. **Action**: Load UserShift page
3. **Expected**: `approvedAbsents` is empty array

### **Test Case 4: Multiple Users with Absent**
1. **Setup**: Multiple users have different approved absents
2. **Action**: Load UserShift page
3. **Expected**: `approvedAbsents` contains all relevant records

## Debug Information

### **Log Output**
The controller now logs:
```php
\Log::info('USERSHIFT_INDEX_APPROVED_ABSENTS', $approvedAbsents->toArray());
```

**Example Log Output:**
```
[2024-01-15 10:30:00] local.INFO: USERSHIFT_INDEX_APPROVED_ABSENTS 
[
    {
        "user_id": 2,
        "date_from": "2025-09-27",
        "date_to": "2025-09-27",
        "leave_type_name": "Cuti Tahunan",
        "reason": "Liburan keluarga"
    }
]
```

## Future Enhancements

1. **Pending Absent Requests**: Also include pending requests with different visual indicator
2. **Absent Type Filtering**: Allow filtering by leave type
3. **Bulk Operations**: Handle bulk shift assignments with absent validation
4. **Real-time Updates**: WebSocket updates when absent status changes
5. **Export Integration**: Include absent information in shift schedule exports
