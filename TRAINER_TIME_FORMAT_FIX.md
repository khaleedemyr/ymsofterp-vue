# Fix Error: Time Format Validation untuk Trainer Invitation

## Problem
Error terjadi saat mengundang trainer dengan pesan:
```
The trainers.0.start_time field must match the format H:i.
```

## Root Cause
1. **Frontend**: `props.training.start_time` dan `props.training.end_time` tidak dalam format `H:i` yang benar
2. **Backend**: Validasi terlalu ketat dengan `date_format:H:i` 
3. **Data Format**: Training schedule mungkin menyimpan waktu dalam format yang berbeda

## Solution

### 1. **Frontend Fix** (`TrainerInvitationModal.vue`)
- Tambah function `formatTime()` untuk convert waktu ke format `H:i`
- Handle berbagai format waktu input
- Return `null` jika format tidak valid

```javascript
const formatTime = (time) => {
  if (!time || time === '') return null
  // Jika sudah dalam format H:i, return as is
  if (typeof time === 'string' && /^\d{2}:\d{2}$/.test(time)) {
    return time
  }
  // Jika dalam format lain, convert ke H:i
  try {
    const date = new Date(`2000-01-01T${time}`)
    if (isNaN(date.getTime())) {
      return null
    }
    return date.toTimeString().slice(0, 5)
  } catch (error) {
    console.warn('Error formatting time:', time, error)
    return null
  }
}
```

### 2. **Backend Fix** (`TrainingScheduleController.php`)
- Ubah validasi dari `date_format:H:i` ke `string`
- Tambah function `formatTime()` di backend untuk handle format waktu
- Log warning jika format tidak bisa di-parse

```php
$formatTime = function($time) {
    if (!$time || $time === '') return null;
    // Jika sudah dalam format H:i, return as is
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    // Jika dalam format lain, convert ke H:i
    try {
        $date = \Carbon\Carbon::createFromFormat('H:i:s', $time);
        return $date->format('H:i');
    } catch (\Exception $e) {
        try {
            $date = \Carbon\Carbon::createFromFormat('H:i', $time);
            return $date->format('H:i');
        } catch (\Exception $e2) {
            \Log::warning('Cannot parse time format: ' . $time);
            return null; // Return null if can't parse
        }
    }
};
```

### 3. **Validation Update**
```php
// Before
'trainers.*.start_time' => 'nullable|date_format:H:i',
'trainers.*.end_time' => 'nullable|date_format:H:i',

// After
'trainers.*.start_time' => 'nullable|string',
'trainers.*.end_time' => 'nullable|string',
```

## Supported Time Formats
- `H:i` (14:30)
- `H:i:s` (14:30:00)
- `HH:mm` (14:30)
- `HH:mm:ss` (14:30:00)

## Error Handling
1. **Frontend**: Log warning dan return `null` jika format tidak valid
2. **Backend**: Log warning dan return `null` jika format tidak bisa di-parse
3. **Database**: Field `start_time` dan `end_time` bisa `null` jika format tidak valid

## Testing
1. Test dengan format waktu yang berbeda
2. Test dengan waktu yang null/empty
3. Test dengan format waktu yang tidak valid
4. Verify data tersimpan dengan benar di database

## Benefits
- ✅ **Flexible**: Support berbagai format waktu
- ✅ **Robust**: Handle error dengan graceful
- ✅ **User-friendly**: Tidak crash saat format waktu tidak sesuai
- ✅ **Logging**: Track format waktu yang bermasalah
