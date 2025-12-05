# 📄 Dokumen Bersama - Fitur File Sharing Modern

## 🎯 **Overview**

Fitur "Dokumen Bersama" adalah sistem file sharing kolaboratif yang memungkinkan pengguna untuk upload, berbagi, dan mengedit dokumen Excel, Word, dan PowerPoint secara real-time menggunakan OnlyOffice Document Server.

## ✨ **Fitur Utama**

### 🎨 **Design Modern & 3D**
- **Glass Morphism**: Efek kaca transparan dengan backdrop blur
- **3D Animations**: Hover effects, scale, dan transform 3D
- **Gradient Backgrounds**: Warna-warna modern dengan gradien
- **Smooth Transitions**: Animasi halus dengan cubic-bezier
- **Responsive Design**: Optimized untuk mobile dan desktop

### 📁 **File Management**
- Upload dokumen Excel (.xlsx, .xls), Word (.docx, .doc), PowerPoint (.pptx, .ppt)
- Maksimal file size: 10MB
- Preview dokumen dengan icon yang sesuai tipe file
- Search dan filter berdasarkan nama dan tipe file

### 👥 **Collaboration**
- **Real-time Editing**: Menggunakan OnlyOffice Document Server
- **Permission System**: View, Edit, Admin
- **Public/Private Access**: Dokumen bisa publik atau private
- **User Sharing**: Bagikan dengan user tertentu
- **Version Control**: Tracking perubahan dokumen

### 🔐 **Security**
- Permission-based access control
- File validation dan sanitization
- Secure file storage
- User authentication required

## 🏗️ **Arsitektur**

### **Database Tables**
```sql
-- Tabel utama dokumen
shared_documents
├── id (Primary Key)
├── title (Judul dokumen)
├── description (Deskripsi)
├── filename (Nama file asli)
├── file_path (Path file di storage)
├── file_type (Tipe file: xlsx, docx, pptx, dll)
├── file_size (Ukuran file dalam bytes)
├── is_public (Boolean - akses publik)
├── created_by (User ID pembuat)
├── created_at, updated_at

-- Tabel permission user
document_permissions
├── id (Primary Key)
├── document_id (Foreign Key)
├── user_id (Foreign Key)
├── permission (view, edit, admin)
├── created_at, updated_at

-- Tabel versi dokumen
document_versions
├── id (Primary Key)
├── document_id (Foreign Key)
├── version_number
├── file_path
├── created_by
├── created_at
```

### **File Structure**
```
resources/js/Pages/SharedDocuments/
├── Index.vue          # Halaman daftar dokumen
├── Create.vue         # Halaman upload dokumen
└── Show.vue           # Halaman view/edit dokumen

resources/css/
└── shared-documents.css  # CSS untuk efek modern 3D

app/Models/
├── SharedDocument.php
├── DocumentPermission.php
└── DocumentVersion.php

app/Http/Controllers/
└── SharedDocumentController.php
```

## 🎨 **Design System**

### **Color Palette**
```css
/* Primary Colors */
--blue-600: #2563eb
--purple-600: #9333ea
--indigo-600: #4f46e5

/* Gradient Combinations */
.gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%)
.gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%)
.gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)
```

### **Animation Classes**
```css
.animate-fade-in-up      /* Fade in dari bawah */
.animate-scale-in        /* Scale in effect */
.animate-float           /* Floating animation */
.hover-lift              /* Hover lift effect */
.hover-scale             /* Hover scale effect */
```

### **Glass Effects**
```css
.glass-card              /* Card dengan glass effect */
.backdrop-blur-xl        /* Blur effect */
.bg-white/80             /* Semi-transparent white */
```

## 🚀 **Setup & Installation**

### **1. Database Setup**
```bash
# Jalankan query SQL untuk membuat tabel
mysql -u username -p database_name < database/sql/create_shared_documents_tables.sql

# Insert menu dan permission
mysql -u username -p database_name < database/sql/insert_shared_documents_menu.sql
```

### **2. OnlyOffice Setup**
```bash
# Jalankan OnlyOffice dengan Docker
docker-compose -f docker-compose.onlyoffice.yml up -d
```

### **3. File Storage**
```bash
# Buat symbolic link untuk storage
php artisan storage:link

# Set permission untuk upload
chmod -R 775 storage/app/public/shared-documents
```

### **4. Environment Variables**
```env
ONLYOFFICE_URL=http://localhost:80
ONLYOFFICE_JWT_SECRET=your-secret-key
```

## 📱 **User Interface**

### **Halaman Index (Daftar Dokumen)**
- Header dengan gradient background dan glass effect
- Search bar dengan icon dan filter dropdown
- Grid layout untuk dokumen cards
- Hover effects dengan 3D transform
- Empty state dengan call-to-action

### **Halaman Create (Upload)**
- Form dengan glass morphism design
- Drag & drop file upload area
- Permission selection (public/private)
- User sharing dengan dynamic form
- Modern button dengan gradient

### **Halaman Show (View/Edit)**
- Document info dengan glass cards
- OnlyOffice editor integration
- Share modal dengan backdrop blur
- Real-time collaboration indicators

## 🔧 **Technical Implementation**

### **Vue.js Components**
```vue
<template>
  <AppLayout>
    <!-- Modern header dengan gradient -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700">
      <!-- Glass effect elements -->
      <div class="absolute inset-0 bg-black/10"></div>
      <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
      
      <!-- Content -->
      <div class="relative z-10">
        <h1 class="text-4xl font-bold animate-fade-in-up">Dokumen Bersama</h1>
      </div>
    </div>
  </AppLayout>
</template>
```

### **CSS Animations**
```css
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}
```

### **3D Hover Effects**
```css
.hover-lift {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover-lift:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}
```

## 🔄 **Workflow**

### **Upload Process**
1. User memilih file (Excel, Word, PowerPoint)
2. Validasi file type dan size
3. Upload ke storage dengan unique filename
4. Create record di database
5. Set permissions (public atau specific users)
6. Redirect ke halaman dokumen

### **Sharing Process**
1. User klik tombol "Bagikan"
2. Modal muncul dengan glass effect
3. Pilih user dan permission level
4. Save permissions ke database
5. Notifikasi ke user yang dibagikan

### **Editing Process**
1. User buka dokumen
2. OnlyOffice editor load dengan config
3. Real-time collaboration aktif
4. Changes auto-save via callback
5. Version tracking untuk setiap perubahan

## 🛡️ **Security Features**

### **File Validation**
```php
// Validasi file type
$allowedTypes = ['xlsx', 'xls', 'docx', 'doc', 'pptx', 'ppt'];
$fileType = $request->file('file')->getClientOriginalExtension();

if (!in_array($fileType, $allowedTypes)) {
    return back()->withErrors(['file' => 'File type not allowed']);
}

// Validasi file size (10MB)
if ($request->file('file')->getSize() > 10 * 1024 * 1024) {
    return back()->withErrors(['file' => 'File too large']);
}
```

### **Permission Check**
```php
public function canAccess($document, $user, $permission = 'view')
{
    // Check if document is public
    if ($document->is_public) {
        return true;
    }
    
    // Check user permissions
    $userPermission = $document->permissions()
        ->where('user_id', $user->id)
        ->first();
    
    return $userPermission && $userPermission->permission >= $permission;
}
```

## 📊 **Performance Optimization**

### **Lazy Loading**
- Dokumen cards load dengan staggered animation
- Images dan icons lazy load
- OnlyOffice editor load on demand

### **Caching**
- Document list cached
- User permissions cached
- File metadata cached

### **Responsive Design**
- Mobile-first approach
- Breakpoints: sm, md, lg, xl
- Touch-friendly interactions

## 🎯 **Future Enhancements**

### **Planned Features**
- [ ] File preview thumbnails
- [ ] Advanced search dengan filters
- [ ] Document templates
- [ ] Comment system
- [ ] Activity feed
- [ ] Bulk operations
- [ ] Export/import permissions
- [ ] Integration dengan Google Drive/Dropbox

### **UI/UX Improvements**
- [ ] Dark mode support
- [ ] Custom themes
- [ ] Advanced animations
- [ ] Keyboard shortcuts
- [ ] Accessibility improvements

## 🐛 **Troubleshooting**

### **Common Issues**

**1. OnlyOffice tidak load**
```bash
# Check OnlyOffice container
docker ps | grep onlyoffice

# Check logs
docker logs onlyoffice-document-server

# Verify URL di config
ONLYOFFICE_URL=http://localhost:80
```

**2. File upload gagal**
```bash
# Check storage permissions
chmod -R 775 storage/app/public/shared-documents

# Check disk space
df -h

# Check PHP upload limits
php -i | grep upload
```

**3. Permission denied**
```sql
-- Check user permissions
SELECT * FROM document_permissions 
WHERE document_id = ? AND user_id = ?;
```

## 📚 **API Documentation**

### **Endpoints**

```php
// List documents
GET /shared-documents

// Create document
POST /shared-documents

// Show document
GET /shared-documents/{id}

// Update document
PUT /shared-documents/{id}

// Delete document
DELETE /shared-documents/{id}

// Share document
POST /shared-documents/{id}/share

// Download document
GET /shared-documents/{id}/download

// OnlyOffice callback
POST /shared-documents/{id}/callback
```

### **Response Format**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Document Title",
        "filename": "document.xlsx",
        "file_type": "xlsx",
        "file_size": 1024000,
        "is_public": false,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "creator": {
            "id": 1,
            "name": "John Doe"
        },
        "permissions": [
            {
                "user_id": 2,
                "permission": "edit"
            }
        ]
    }
}
```

## 🎉 **Conclusion**

Fitur "Dokumen Bersama" memberikan pengalaman modern dan intuitif untuk kolaborasi dokumen dengan:

- **Design yang menarik** dengan glass morphism dan 3D effects
- **Fungsionalitas lengkap** untuk file sharing dan editing
- **Security yang robust** dengan permission system
- **Performance yang optimal** dengan caching dan lazy loading
- **User experience yang smooth** dengan animasi dan transitions

Fitur ini siap untuk production dan dapat dikembangkan lebih lanjut sesuai kebutuhan bisnis. 