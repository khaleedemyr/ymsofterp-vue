# Outlet Return SweetAlert Fix

## Problem Description
Setelah fix stock validation berhasil, return approval berfungsi tapi response JSON langsung ditampilkan di halaman dengan pesan "All Inertia requests must receive a valid Inertia response, however a plain JSON response was received." Padahal seharusnya menggunakan SweetAlert untuk notifikasi yang user-friendly.

## Root Cause Analysis
Masalah terjadi karena **mismatch antara frontend dan backend response handling**:

### Frontend Issue
- Frontend menggunakan `router.post()` (Inertia.js) untuk mengirim request
- Inertia.js mengharapkan response berupa Inertia response (redirect atau render)
- Tapi controller mengembalikan JSON response biasa

### Backend Response
Controller mengembalikan JSON response:
```php
return response()->json([
    'success' => true,
    'message' => 'Return berhasil diapprove dan stock berhasil dikurangi'
]);
```

### Frontend Expectation
Inertia.js mengharapkan response seperti:
```php
return redirect()->route('head-office-return.index')
    ->with('success', 'Return berhasil diapprove');
```

## Solution

### Approach: Change Frontend to Use Axios
Instead of changing backend (which would break other potential API consumers), we change frontend to use `axios` for API calls that return JSON.

### Files Modified

#### 1. HeadOfficeReturn/Show.vue
**Before:**
```javascript
function approveReturn() {
  Swal.fire({...}).then((result) => {
    if (result.isConfirmed) {
      router.post(`/head-office-return/${returnData.id}/approve`, {}, {
        onSuccess: () => {
          Swal.fire({...})
        },
        onError: (errors) => {
          Swal.fire({...})
        }
      })
    }
  })
}
```

**After:**
```javascript
async function approveReturn() {
  const result = await Swal.fire({...});
  
  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/head-office-return/${returnData.id}/approve`);
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
          timer: 1500,
          showConfirmButton: false
        });
        router.visit('/head-office-return');
      }
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: error.response?.data?.message || 'Gagal approve return'
      });
    }
  }
}
```

#### 2. HeadOfficeReturn/Index.vue
**Before:**
```javascript
function approveReturn(id) {
  Swal.fire({...}).then((result) => {
    if (result.isConfirmed) {
      router.post(`/head-office-return/${id}/approve`, {}, {
        onSuccess: () => {
          Swal.fire({...})
        },
        onError: (errors) => {
          Swal.fire({...})
        }
      })
    }
  })
}
```

**After:**
```javascript
async function approveReturn(id) {
  const result = await Swal.fire({...});
  
  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/head-office-return/${id}/approve`);
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
          timer: 1500,
          showConfirmButton: false
        });
        window.location.reload();
      }
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: error.response?.data?.message || 'Gagal approve return'
      });
    }
  }
}
```

### Key Changes Made

#### 1. Function Signature
- Changed from `function` to `async function`
- Use `await` for Swal.fire() calls

#### 2. Request Method
- Changed from `router.post()` to `axios.post()`
- Removed Inertia-specific options (`onSuccess`, `onError`)

#### 3. Response Handling
- Use `try-catch` for error handling
- Check `response.data.success` for success validation
- Use `response.data.message` for dynamic message from backend

#### 4. Error Handling
- Use `error.response?.data?.message` for backend error messages
- Fallback to generic error message if backend message not available

#### 5. Page Refresh
- **Show.vue**: Use `router.visit('/head-office-return')` to navigate back
- **Index.vue**: Use `window.location.reload()` to refresh current page

## Benefits

### 1. Proper Error Handling
- Backend error messages are properly displayed
- Network errors are handled gracefully
- User gets clear feedback on what went wrong

### 2. Better User Experience
- SweetAlert notifications instead of raw JSON display
- Consistent UI/UX across the application
- Proper loading states and confirmations

### 3. Maintainable Code
- Clear separation between API calls and UI updates
- Consistent error handling pattern
- Easy to debug and maintain

### 4. Backward Compatibility
- Backend remains unchanged
- Other potential API consumers still work
- No breaking changes to existing functionality

## Testing

### Before Fix
1. Click "Approve" button on return
2. **Result**: Raw JSON response displayed on page
3. **Error**: "All Inertia requests must receive a valid Inertia response..."

### After Fix
1. Click "Approve" button on return
2. **Result**: SweetAlert confirmation dialog
3. After confirmation: SweetAlert success message
4. Page redirects/refreshes with updated data

## Files Modified

1. **`resources/js/Pages/HeadOfficeReturn/Show.vue`**
   - `approveReturn()` function
   - `rejectReturn()` function

2. **`resources/js/Pages/HeadOfficeReturn/Index.vue`**
   - `approveReturn()` function  
   - `rejectReturn()` function

## Related Systems
This fix pattern can be applied to other similar issues where:
- Frontend uses Inertia.js but backend returns JSON
- API endpoints need proper error handling
- User experience needs improvement with proper notifications

## Future Considerations
- Consider standardizing all API endpoints to return consistent JSON responses
- Implement global error handling for axios requests
- Add loading states for better UX during API calls
