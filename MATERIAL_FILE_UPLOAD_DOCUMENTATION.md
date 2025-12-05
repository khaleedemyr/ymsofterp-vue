# Material File Upload System Documentation

## **🎯 Overview**

Sistem upload file untuk material type dalam LMS yang baru menggunakan struktur **session-based curriculum**. File yang diupload akan disimpan ke storage dan metadata-nya disimpan ke database.

## **🏗️ Struktur Database**

### **1. Tabel `lms_sessions`** (Container Sesi)
```sql
- id: Primary key
- course_id: Reference ke course
- session_title: Judul sesi
- session_description: Deskripsi sesi
- order_number: Urutan sesi dalam course
- estimated_duration_minutes: Estimasi durasi sesi
- status: active/inactive
- created_by, updated_by: User tracking
- timestamps: created_at, updated_at, deleted_at
```

### **2. Tabel `lms_session_items`** (Item dalam Sesi)
```sql
- id: Primary key
- session_id: Reference ke session
- item_type: 'quiz', 'material', atau 'questionnaire'
- item_id: Reference ke quiz/material/questionnaire
- title: Judul custom item
- description: Deskripsi custom item
- order_number: Urutan item dalam session
- estimated_duration_minutes: Estimasi durasi item
- status: active/inactive
- created_by, updated_by: User tracking
- timestamps: created_at, updated_at, deleted_at
```

### **3. Tabel `lms_curriculum_materials`** (File Storage)
```sql
- id: Primary key
- title: Judul material
- description: Deskripsi material
- file_path: Path file di storage (public/lms/materials/)
- file_type: 'pdf', 'image', 'video', 'document', 'link'
- estimated_duration_minutes: Estimasi durasi dalam menit
- status: active/inactive
- created_by, updated_by: User tracking
- timestamps: created_at, updated_at, deleted_at
```

## **📁 Struktur Storage**

```
storage/
└── app/
    └── public/
        └── lms/
            └── materials/
                ├── course_1/
                │   ├── session_1/
                │   │   ├── material_1.pdf
                │   │   ├── material_2.jpg
                │   │   └── material_3.mp4
                │   └── session_2/
                │       └── material_4.docx
                └── course_2/
                    └── session_1/
                        └── material_5.pdf
```

## **🔄 Flow Upload File**

### **1. Frontend (Vue.js)**
```javascript
// User memilih file untuk material type
const handleMaterialFileUpload = (event, sessionIndex, itemIndex) => {
  const files = event.target.files;
  
  files.forEach(file => {
    // Validasi file
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
      showError('File terlalu besar!');
      return;
    }
    
    // Tambahkan ke material_files array
    const item = form.value.sessions[sessionIndex].items[itemIndex];
    if (!item.material_files) {
      item.material_files = [];
    }
    
    item.material_files.push({
      file: file,
      name: file.name,
      size: file.size,
      type: file.type
    });
  });
}
```

### **2. Form Submission**
```javascript
// Saat submit form, file dikirim sebagai FormData
const formData = new FormData();

// Data sesi dan item
formData.append(`sessions[${sessionIndex}][session_title]`, session.session_title);
formData.append(`sessions[${sessionIndex}][items][${itemIndex}][item_type]`, item.item_type);

// File untuk material type
if (item.item_type === 'material' && item.material_files) {
  item.material_files.forEach((file, fileIndex) => {
    formData.append(`sessions[${sessionIndex}][items][${itemIndex}][material_files][${fileIndex}]`, file.file);
  });
}
```

### **3. Backend Processing (Laravel Controller)**
```php
// LmsController::storeCourse()
foreach ($sessionData['items'] as $itemIndex => $itemData) {
    // Handle material type with file uploads
    if ($itemData['item_type'] === 'material' && isset($itemData['material_files'])) {
        
        // Create curriculum material record
        $material = LmsCurriculumMaterial::create([
            'title' => $itemData['title'] ?? 'Material ' . ($itemIndex + 1),
            'description' => $itemData['description'] ?? '',
            'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);
        
        // Process each uploaded file
        foreach ($itemData['material_files'] as $uploadedFile) {
            if ($uploadedFile instanceof UploadedFile) {
                // Store file to storage
                $filePath = $uploadedFile->store('lms/materials', 'public');
                
                // Determine file type
                $fileType = $this->getFileType($uploadedFile->getMimeType());
                
                // Update material record with file info
                $material->update([
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                    'updated_by' => auth()->id()
                ]);
            }
        }
        
        $itemId = $material->id;
    }
    
    // Create session item
    $item = $session->items()->create([
        'item_type' => $itemData['item_type'],
        'item_id' => $itemId, // Reference ke material yang baru dibuat
        'title' => $itemData['title'] ?? null,
        'description' => $itemData['description'] ?? null,
        'order_number' => $itemData['order_number'],
        'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
        'created_by' => auth()->id(),
    ]);
}
```

## **🔧 File Type Detection**

### **MIME Type Mapping**
```php
private function getFileType($mimeType)
{
    $mimeToType = [
        'application/pdf' => 'pdf',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'application/vnd.ms-powerpoint' => 'document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
        'image/jpeg' => 'image',
        'image/jpg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'video/mp4' => 'video',
        'video/avi' => 'video',
        'video/quicktime' => 'video',
    ];
    
    return $mimeToType[$mimeType] ?? 'document';
}
```

## **📋 Validasi File**

### **Frontend Validation**
- **File Size**: Maksimal 10MB
- **File Type**: PDF, Image (JPG, PNG, GIF), Video (MP4, AVI, MOV), Document (DOC, DOCX, PPT, PPTX)
- **Required Fields**: Title, Description, Duration

### **Backend Validation**
```php
$request->validate([
    'sessions.*.items.*.item_type' => 'required|in:quiz,material,questionnaire',
    'sessions.*.items.*.title' => 'nullable|string|max:255',
    'sessions.*.items.*.description' => 'nullable|string',
    'sessions.*.items.*.estimated_duration_minutes' => 'nullable|integer|min:0',
    // File validation handled in controller
]);
```

## **🚀 Cara Penggunaan**

### **1. Buat Course dengan Material**
1. Buka form "Tambah Course"
2. Isi informasi dasar course
3. Tambah sesi training
4. Dalam sesi, tambah item dengan tipe "Material"
5. Upload file (PDF, Image, Video, Document)
6. Submit form

### **2. File akan Otomatis:**
- ✅ Disimpan ke `storage/app/public/lms/materials/`
- ✅ Metadata disimpan ke tabel `lms_curriculum_materials`
- ✅ Session item dibuat dengan reference ke material
- ✅ File bisa diakses via URL: `/storage/lms/materials/filename.ext`

## **🔍 Troubleshooting**

### **File Tidak Tersimpan**
1. **Cek Storage Permission**: Pastikan folder `storage/app/public` writable
2. **Cek Database**: Pastikan tabel `lms_curriculum_materials` ada dan struktur benar
3. **Cek Logs**: Lihat Laravel logs untuk error detail
4. **Cek File Size**: Pastikan file tidak melebihi 10MB

### **File Tidak Bisa Diakses**
1. **Cek Storage Link**: Jalankan `php artisan storage:link`
2. **Cek File Path**: Pastikan path di database benar
3. **Cek File Exists**: Pastikan file ada di storage

### **Error Database**
1. **Cek Migration**: Jalankan migration untuk tabel materials
2. **Cek Foreign Keys**: Pastikan constraint tidak error
3. **Cek User ID**: Pastikan `created_by` valid

## **📝 Contoh Data**

### **Sample Course Structure**
```
Course: "Basic Training"
├── Session 1: "Introduction"
│   ├── Material: "Course Overview.pdf" (30 menit)
│   └── Quiz: "Pre-Test" (15 menit)
└── Session 2: "Core Content"
    ├── Material: "Main Content.mp4" (45 menit)
    └── Material: "Supporting Documents.docx" (20 menit)
```

### **Database Records**
```sql
-- lms_sessions
INSERT INTO lms_sessions (course_id, session_title, order_number) VALUES
(1, 'Introduction', 1),
(1, 'Core Content', 2);

-- lms_session_items
INSERT INTO lms_session_items (session_id, item_type, item_id, title) VALUES
(1, 'material', 1, 'Course Overview'),
(1, 'quiz', 1, 'Pre-Test'),
(2, 'material', 2, 'Main Content'),
(2, 'material', 3, 'Supporting Documents');

-- lms_curriculum_materials
INSERT INTO lms_curriculum_materials (title, file_path, file_type) VALUES
('Course Overview', 'lms/materials/course_overview.pdf', 'pdf'),
('Main Content', 'lms/materials/main_content.mp4', 'video'),
('Supporting Documents', 'lms/materials/supporting_docs.docx', 'document');
```

## **🔮 Fitur Masa Depan**

### **Planned Enhancements**
- ✅ **Multiple Files per Material**: Satu material bisa punya multiple file
- ✅ **File Versioning**: Track perubahan file
- ✅ **File Preview**: Preview PDF, Image, Video langsung di browser
- ✅ **File Compression**: Auto-compress file besar
- ✅ **CDN Integration**: File disimpan di CDN untuk performance
- ✅ **File Analytics**: Track download dan view statistics
