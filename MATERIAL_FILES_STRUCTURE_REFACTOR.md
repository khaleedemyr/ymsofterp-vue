# Material Files Structure Refactoring Documentation

## **🎯 Overview**

Dokumen ini menjelaskan refactoring dari sistem penyimpanan file curriculum material yang sebelumnya menggunakan JSON array menjadi struktur tabel relasional yang lebih baik.

## **🔧 Masalah Sebelumnya**

### **1. JSON Storage (Cara Lama)**
```php
// Data disimpan sebagai JSON array di kolom file_path dan file_type
$material = LmsCurriculumMaterial::create([
    'title' => 'Material Title',
    'file_path' => json_encode(['file1.pdf', 'file2.pdf']), // ❌ JSON array
    'file_type' => json_encode(['pdf', 'pdf']), // ❌ JSON array
    // ... other fields
]);
```

**Masalah:**
- Sulit untuk query dan filter berdasarkan file
- Tidak bisa menggunakan fitur database untuk file management
- Sulit untuk tracking individual files
- Tidak ada metadata file yang lengkap (size, mime type, dll)
- Sulit untuk reordering files

## **✅ Solusi Baru**

### **1. Struktur Database Baru**

#### **Tabel `lms_curriculum_materials` (Updated)**
```sql
- id: Primary key
- title: Judul material
- description: Deskripsi material
- estimated_duration_minutes: Estimasi durasi
- status: active/inactive
- created_by, updated_by: User tracking
- timestamps: created_at, updated_at, deleted_at
-- ❌ REMOVED: file_path, file_type (JSON fields)
```

#### **Tabel Baru `lms_curriculum_material_files`**
```sql
- id: Primary key
- material_id: Foreign key ke lms_curriculum_materials
- file_path: Path file di storage
- file_name: Nama file asli
- file_size: Ukuran file dalam bytes
- file_mime_type: MIME type file
- file_type: Tipe file (pdf, image, video, document, link)
- order_number: Urutan file dalam material
- is_primary: Apakah file utama (untuk thumbnail/preview)
- status: active/inactive
- created_by: User yang membuat
- timestamps: created_at, updated_at, deleted_at
```

### **2. Model Baru**

#### **LmsCurriculumMaterial (Updated)**
```php
class LmsCurriculumMaterial extends Model
{
    // ❌ REMOVED: file_path, file_type dari fillable
    
    // ✅ NEW: Relationships
    public function files()
    {
        return $this->hasMany(LmsCurriculumMaterialFile::class, 'material_id')->ordered();
    }
    
    public function primaryFile()
    {
        return $this->hasOne(LmsCurriculumMaterialFile::class, 'material_id')->where('is_primary', true);
    }
    
    // ✅ NEW: Methods
    public function addFile($file, $orderNumber = null, $isPrimary = false)
    public function removeFile($fileId)
    public function reorderFiles($fileOrder)
    
    // ✅ NEW: Accessors
    public function getPrimaryFileUrlAttribute()
    public function getFilesCountAttribute()
    public function getPrimaryFileTypeAttribute()
}
```

#### **LmsCurriculumMaterialFile (New)**
```php
class LmsCurriculumMaterialFile extends Model
{
    protected $fillable = [
        'material_id', 'file_path', 'file_name', 'file_size',
        'file_mime_type', 'file_type', 'order_number', 
        'is_primary', 'status', 'created_by'
    ];
    
    // Relationships
    public function material()
    public function creator()
    
    // Methods
    public function deleteFile()
    public function updateFile($file, $newFileName = null)
    public function setAsPrimary()
}
```

## **🚀 Cara Penggunaan Baru**

### **1. Menambah File ke Material**
```php
// Cara lama (JSON)
$material->update([
    'file_path' => json_encode(array_merge(
        json_decode($material->file_path ?? '[]', true),
        ['new_file.pdf']
    ))
]);

// Cara baru (Relational)
$material->addFile($uploadedFile, 1, true); // true = primary file
```

### **2. Mengakses Files**
```php
// Cara lama (JSON)
$filePaths = json_decode($material->file_path ?? '[]', true);
$fileTypes = json_decode($material->file_type ?? '[]', true);

// Cara baru (Relational)
$files = $material->files; // Collection of files
$primaryFile = $material->primaryFile; // Primary file
$filesCount = $material->files_count; // Count via accessor
```

### **3. Filter dan Query**
```php
// Cara lama (JSON) - Sulit dan tidak efisien
$materials = LmsCurriculumMaterial::whereRaw("JSON_CONTAINS(file_type, ?)", ['pdf']);

// Cara baru (Relational) - Mudah dan efisien
$materials = LmsCurriculumMaterial::byFileType('pdf');
$materials = LmsCurriculumMaterial::hasFiles();
```

## **📋 Langkah Migrasi**

### **1. Jalankan Migration SQL**
```bash
# Jalankan file: database/sql/migrate_material_files_structure.sql
mysql -u username -p database_name < migrate_material_files_structure.sql
```

### **2. Update Code**
- ✅ Model `LmsCurriculumMaterial` sudah diupdate
- ✅ Model `LmsCurriculumMaterialFile` sudah dibuat
- ✅ Controller `LmsController::storeCourse()` sudah diupdate

### **3. Testing**
```php
// Test creating material with files
$material = LmsCurriculumMaterial::create([
    'title' => 'Test Material',
    'description' => 'Test Description',
    'estimated_duration_minutes' => 30,
    'status' => 'active',
    'created_by' => auth()->id(),
]);

// Add multiple files
$material->addFile($file1, 1, true);  // Primary file
$material->addFile($file2, 2, false); // Secondary file

// Verify
echo $material->files_count; // Should show 2
echo $material->primary_file_url; // Should show primary file URL
```

## **🔍 Keuntungan Struktur Baru**

### **1. Performance**
- ✅ Query lebih cepat dengan proper indexing
- ✅ Tidak ada JSON parsing overhead
- ✅ Bisa menggunakan database features (JOIN, WHERE, ORDER BY)

### **2. Flexibility**
- ✅ Bisa tambah/hapus file individual
- ✅ Bisa reorder files
- ✅ Bisa set primary file
- ✅ Metadata file yang lengkap

### **3. Maintainability**
- ✅ Code lebih clean dan readable
- ✅ Relationship yang jelas
- ✅ Mudah untuk debugging
- ✅ Mudah untuk testing

### **4. Scalability**
- ✅ Bisa handle unlimited files per material
- ✅ Bisa add file types baru dengan mudah
- ✅ Bisa implement file versioning di masa depan

## **⚠️ Hal yang Perlu Diperhatikan**

### **1. Backward Compatibility**
- Kolom lama `file_path` dan `file_type` masih ada (untuk backup)
- Bisa dihapus setelah migration selesai dan testing berhasil

### **2. File Storage**
- File tetap disimpan di `storage/app/public/lms/materials/`
- Path file tidak berubah, hanya cara akses yang berubah

### **3. Frontend Updates**
- Frontend perlu diupdate untuk menggunakan struktur baru
- API responses akan berubah format

## **🧪 Testing Checklist**

- [ ] Create material tanpa file
- [ ] Create material dengan single file
- [ ] Create material dengan multiple files
- [ ] Set primary file
- [ ] Reorder files
- [ ] Remove file
- [ ] Update file
- [ ] Query materials by file type
- [ ] Query materials with files
- [ ] File upload validation
- [ ] File storage and retrieval

## **📚 File Files yang Telah Dibuat/Diupdate**

1. ✅ `database/sql/create_material_files_table.sql` - Create table SQL
2. ✅ `database/sql/migrate_material_files_structure.sql` - Migration script
3. ✅ `app/Models/LmsCurriculumMaterialFile.php` - New model
4. ✅ `app/Models/LmsCurriculumMaterial.php` - Updated model
5. ✅ `app/Http/Controllers/LmsController.php` - Updated controller
6. ✅ `MATERIAL_FILES_STRUCTURE_REFACTOR.md` - This documentation

## **🎉 Kesimpulan**

Refactoring ini mengubah sistem dari JSON-based storage menjadi relational database structure yang lebih robust, scalable, dan maintainable. Setiap file sekarang memiliki record sendiri dengan metadata lengkap, memungkinkan management file yang lebih baik dan query yang lebih efisien.
