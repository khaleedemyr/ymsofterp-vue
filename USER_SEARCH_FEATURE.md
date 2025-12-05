# 🔍 User Search Feature - Pencarian User Modern

## 🎯 **Overview**

Fitur pencarian user yang modern dengan autocomplete, menampilkan informasi lengkap user (nama, email, divisi, jabatan) untuk keperluan sharing dokumen.

## ✨ **Fitur Utama**

### 🔍 **Search Capabilities**
- **Multi-field Search**: Cari berdasarkan nama, email, divisi, atau jabatan
- **Real-time Autocomplete**: Hasil pencarian muncul secara real-time
- **Debounced Search**: Optimized performance dengan debouncing
- **Smart Filtering**: Exclude current user dari hasil pencarian

### 🎨 **Modern UI/UX**
- **Glass Morphism**: Dropdown dengan backdrop blur effect
- **Smooth Animations**: Hover effects dan transitions
- **Responsive Design**: Works perfectly di mobile dan desktop
- **Loading States**: Spinner animation saat searching

### 👥 **User Information Display**
- **Nama Lengkap**: Nama user yang jelas
- **Email**: Alamat email user
- **Divisi**: Divisi tempat user bekerja
- **Jabatan**: Jabatan/posisi user
- **Selection Indicator**: Visual feedback untuk user yang dipilih

## 🏗️ **Arsitektur**

### **Backend API**
```php
// Controller Method
public function searchUsers(Request $request)
{
    $search = $request->get('search', '');
    
    $users = User::select('id', 'nama_lengkap', 'email', 'divisi', 'jabatan')
        ->where(function($query) use ($search) {
            $query->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('divisi', 'like', "%{$search}%")
                  ->orWhere('jabatan', 'like', "%{$search}%");
        })
        ->where('id', '!=', auth()->id()) // Exclude current user
        ->limit(10)
        ->get();
    
    return response()->json([
        'success' => true,
        'data' => $users
    ]);
}
```

### **Frontend Component**
```vue
<!-- UserSearchDropdown.vue -->
<template>
    <div class="relative">
        <!-- Search Input -->
        <input
            v-model="searchQuery"
            @input="handleSearch"
            placeholder="Cari user..."
        >
        
        <!-- Dropdown Results -->
        <div v-if="showDropdown" class="dropdown-results">
            <div v-for="user in searchResults" @click="selectUser(user)">
                <div class="user-name">{{ user.nama_lengkap }}</div>
                <div class="user-email">{{ user.email }}</div>
                <div class="user-info">
                    {{ user.divisi }} • {{ user.jabatan }}
                </div>
            </div>
        </div>
    </div>
</template>
```

## 🎨 **Design System**

### **Color Scheme**
```css
/* Primary Colors */
--blue-500: #3b82f6
--blue-600: #2563eb
--gray-400: #9ca3af
--gray-500: #6b7280
--gray-600: #4b5563
--gray-900: #111827

/* Glass Effects */
.glass-dropdown {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
```

### **Animation Classes**
```css
.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}

.hover-lift {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
```

## 📱 **User Interface**

### **Search Input**
- **Placeholder**: "Cari user berdasarkan nama, email, divisi, atau jabatan..."
- **Search Icon**: FontAwesome search icon
- **Focus States**: Blue ring focus dengan smooth transition
- **Loading State**: Spinner animation saat searching

### **Dropdown Results**
- **Glass Effect**: Semi-transparent background dengan blur
- **User Cards**: Each user dalam card terpisah
- **Hover Effects**: Background color change dan scale
- **Selection Indicator**: Radio button style indicator

### **User Information Layout**
```
┌─────────────────────────────────────┐
│ John Doe                            │
│ john.doe@company.com                │
│ IT Department • Software Engineer   │
│                    ○ (selected)     │
└─────────────────────────────────────┘
```

## 🔧 **Technical Implementation**

### **Debounced Search**
```javascript
const handleSearch = () => {
    clearTimeout(searchTimeout.value)
    
    if (searchQuery.value.length < 2) {
        searchResults.value = []
        return
    }
    
    loading.value = true
    searchTimeout.value = setTimeout(async () => {
        try {
            const response = await axios.get(route('shared-documents.users.search'), {
                params: { search: searchQuery.value }
            })
            
            if (response.data.success) {
                searchResults.value = response.data.data
            }
        } catch (error) {
            console.error('Error searching users:', error)
            searchResults.value = []
        } finally {
            loading.value = false
        }
    }, 300) // 300ms debounce
}
```

### **User Selection Logic**
```javascript
const selectUser = (user) => {
    if (props.multiple) {
        // For multiple selection, toggle user selection
        const isAlreadySelected = props.selectedUsers.some(u => u.id === user.id)
        if (isAlreadySelected) {
            emit('user-removed', user)
        } else {
            emit('user-selected', user)
        }
    } else {
        // For single selection, select user and close dropdown
        emit('user-selected', user)
        searchQuery.value = user.nama_lengkap
        closeDropdown()
    }
}
```

## 🚀 **Usage Examples**

### **Single Selection**
```vue
<UserSearchDropdown
    placeholder="Pilih user..."
    :selected-users="selectedUser"
    :multiple="false"
    @user-selected="handleUserSelected"
/>
```

### **Multiple Selection**
```vue
<UserSearchDropdown
    placeholder="Cari user untuk dibagikan..."
    :selected-users="selectedUsers"
    :multiple="true"
    @user-selected="addUser"
    @user-removed="removeUser"
/>
```

### **Integration with Forms**
```vue
<template>
    <div class="space-y-4">
        <!-- Selected Users Display -->
        <div v-if="selectedUsers.length > 0" class="space-y-2">
            <div v-for="user in selectedUsers" :key="user.id" class="user-card">
                <div class="user-info">
                    <div class="user-name">{{ user.nama_lengkap }}</div>
                    <div class="user-email">{{ user.email }}</div>
                    <div class="user-details">
                        {{ user.divisi }} • {{ user.jabatan }}
                    </div>
                </div>
                <div class="user-actions">
                    <select v-model="getUserPermission(user.id)">
                        <option value="view">View</option>
                        <option value="edit">Edit</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button @click="removeUser(user)">Remove</button>
                </div>
            </div>
        </div>

        <!-- User Search -->
        <UserSearchDropdown
            :selected-users="selectedUsers"
            :multiple="true"
            @user-selected="addUser"
            @user-removed="removeUser"
        />
    </div>
</template>
```

## 📊 **Performance Optimization**

### **Search Optimization**
- **Debouncing**: 300ms delay untuk mengurangi API calls
- **Minimum Length**: Search hanya setelah 2 karakter
- **Result Limiting**: Maksimal 10 hasil per search
- **Caching**: Browser cache untuk hasil pencarian

### **UI Performance**
- **Lazy Loading**: Dropdown hanya load saat focus
- **Virtual Scrolling**: Untuk list user yang panjang
- **Optimized Animations**: CSS transforms untuk smooth performance

## 🔒 **Security Features**

### **Input Validation**
```php
// Sanitize search input
$search = trim($request->get('search', ''));
$search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

// Prevent SQL injection dengan prepared statements
$users = User::where('nama_lengkap', 'like', "%{$search}%")
    ->orWhere('email', 'like', "%{$search}%")
    ->get();
```

### **Access Control**
- **Current User Exclusion**: User tidak bisa search dirinya sendiri
- **Permission Check**: Hanya user yang authorized bisa search
- **Rate Limiting**: Prevent abuse dengan rate limiting

## 🎯 **Search Tips**

### **Effective Search Keywords**
```
Nama: "John Doe", "joh", "doe"
Email: "john@company.com", "john", "company"
Divisi: "IT", "Marketing", "Finance"
Jabatan: "Manager", "Engineer", "Analyst"
```

### **Search Examples**
```
Input: "john" → Results: John Doe, John Smith
Input: "IT" → Results: All users in IT division
Input: "manager" → Results: All managers
Input: "john@company" → Results: John Doe
```

## 🐛 **Troubleshooting**

### **Common Issues**

**1. Search tidak berfungsi**
```bash
# Check API endpoint
curl -X GET "http://localhost/shared-documents/users/search?search=john"

# Check database connection
php artisan tinker
User::where('nama_lengkap', 'like', '%john%')->get();
```

**2. Dropdown tidak muncul**
```javascript
// Check component import
import UserSearchDropdown from '@/Components/UserSearchDropdown.vue'

// Check route definition
Route::get('shared-documents/users/search', [SharedDocumentController::class, 'searchUsers'])
```

**3. Permission denied**
```php
// Check middleware
Route::middleware(['auth'])->group(function () {
    Route::get('shared-documents/users/search', [SharedDocumentController::class, 'searchUsers']);
});
```

## 📈 **Future Enhancements**

### **Planned Features**
- [ ] **Advanced Filters**: Filter by divisi, jabatan, status
- [ ] **Recent Searches**: Save dan display recent searches
- [ ] **Favorites**: Mark favorite users untuk quick access
- [ ] **Bulk Selection**: Select multiple users at once
- [ ] **Export Results**: Export search results to CSV/Excel

### **UI Improvements**
- [ ] **Dark Mode**: Support untuk dark theme
- [ ] **Keyboard Navigation**: Arrow keys untuk navigation
- [ ] **Voice Search**: Voice input untuk search
- [ ] **Smart Suggestions**: AI-powered search suggestions

## 🎉 **Conclusion**

Fitur User Search memberikan pengalaman pencarian yang modern dan intuitif dengan:

- **Search yang powerful** dengan multi-field search
- **UI yang menarik** dengan glass morphism dan animations
- **Performance yang optimal** dengan debouncing dan caching
- **Security yang robust** dengan input validation dan access control
- **User experience yang smooth** dengan real-time feedback

Fitur ini siap untuk production dan dapat dikembangkan lebih lanjut sesuai kebutuhan bisnis. 