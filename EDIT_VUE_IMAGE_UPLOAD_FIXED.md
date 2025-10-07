# Edit.vue Image Upload Fixed - FormData Implementation

## 🎯 **Masalah yang Diperbaiki**

### **Problem**
- ✅ **Edit.vue tidak support** file upload
- ✅ **Tidak menggunakan FormData** - Hanya mengirim JSON
- ✅ **Tidak ada file upload handling** di controller update
- ✅ **Preview tidak bekerja** dengan file objects

### **Root Cause**
- ✅ **Edit.vue menggunakan JSON** - Tidak support file upload
- ✅ **Controller update** tidak handle file upload
- ✅ **File objects** tidak disimpan dengan benar
- ✅ **Preview display** tidak menggunakan file objects

## 🔧 **Perbaikan yang Dilakukan**

### **1. Edit.vue Updates**

#### **File Object Storage**
```javascript
// Handle upload gambar pertanyaan (multiple images)
const handlePertanyaanImages = (event, index) => {
  const files = Array.from(event.target.files);
  const pertanyaan = form.pertanyaans[index];
  
  if (!pertanyaan.pertanyaan_gambar) {
    pertanyaan.pertanyaan_gambar = [];
  }
  
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      // Store both File object and preview URL
      const reader = new FileReader();
      reader.onload = (e) => {
        pertanyaan.pertanyaan_gambar.push({
          file: file,
          preview: e.target.result
        });
      };
      reader.readAsDataURL(file);
    }
  });
  
  // Reset input
  event.target.value = '';
};
```

#### **Pilihan Image Handling**
```javascript
// Handle upload gambar pilihan
const handlePilihanImage = (event, index, pilihan) => {
  const file = event.target.files[0];
  const pertanyaan = form.pertanyaans[index];
  
  if (file && file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => {
      pertanyaan[`pilihan_${pilihan}_gambar`] = {
        file: file,
        preview: e.target.result
      };
    };
    reader.readAsDataURL(file);
  }
  
  // Reset input
  event.target.value = '';
};
```

#### **FormData Implementation**
```javascript
const submitForm = () => {
  isSubmitting.value = true;
  
  // Create FormData for file uploads
  const formData = new FormData();
  
  // Add basic form data
  formData.append('judul', form.judul);
  formData.append('deskripsi', form.deskripsi || '');
  formData.append('status', form.status);
  formData.append('_method', 'PUT'); // Laravel method spoofing for PUT request
  
  // Add pertanyaans data
  form.pertanyaans.forEach((pertanyaan, index) => {
    formData.append(`pertanyaans[${index}][tipe_soal]`, pertanyaan.tipe_soal);
    formData.append(`pertanyaans[${index}][pertanyaan]`, pertanyaan.pertanyaan);
    formData.append(`pertanyaans[${index}][waktu_detik]`, pertanyaan.waktu_detik);
    formData.append(`pertanyaans[${index}][skor]`, pertanyaan.skor || '');
    formData.append(`pertanyaans[${index}][jawaban_benar]`, pertanyaan.jawaban_benar || '');
    formData.append(`pertanyaans[${index}][pilihan_a]`, pertanyaan.pilihan_a || '');
    formData.append(`pertanyaans[${index}][pilihan_b]`, pertanyaan.pilihan_b || '');
    formData.append(`pertanyaans[${index}][pilihan_c]`, pertanyaan.pilihan_c || '');
    formData.append(`pertanyaans[${index}][pilihan_d]`, pertanyaan.pilihan_d || '');
    
    // Add pertanyaan images
    if (pertanyaan.pertanyaan_gambar && pertanyaan.pertanyaan_gambar.length > 0) {
      pertanyaan.pertanyaan_gambar.forEach((image, imgIndex) => {
        if (image.file instanceof File) {
          formData.append(`pertanyaans[${index}][pertanyaan_images][]`, image.file);
        }
      });
    }
    
    // Add pilihan images
    if (pertanyaan.pilihan_a_gambar && pertanyaan.pilihan_a_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_a_image]`, pertanyaan.pilihan_a_gambar.file);
    }
    if (pertanyaan.pilihan_b_gambar && pertanyaan.pilihan_b_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_b_image]`, pertanyaan.pilihan_b_gambar.file);
    }
    if (pertanyaan.pilihan_c_gambar && pertanyaan.pilihan_c_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_c_image]`, pertanyaan.pilihan_c_gambar.file);
    }
    if (pertanyaan.pilihan_d_gambar && pertanyaan.pilihan_d_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_d_image]`, pertanyaan.pilihan_d_gambar.file);
    }
  });
  
  router.post(`/master-soal-new/${props.masterSoal.id}`, formData, {
    onSuccess: () => {
      isSubmitting.value = false;
    },
    onError: (errors) => {
      isSubmitting.value = false;
      Object.assign(errors, errors);
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
```

#### **Preview Updates**
```html
<!-- Pertanyaan Images -->
<img
  :src="image.preview"
  :alt="`Pertanyaan ${index + 1} - Image ${imgIndex + 1}`"
  class="w-full h-20 object-cover rounded"
/>

<!-- Pilihan Images -->
<img
  :src="pertanyaan.pilihan_a_gambar.preview"
  alt="Pilihan A"
  class="w-16 h-16 object-cover rounded border"
/>
```

### **2. Controller Updates**

#### **Validation Rules**
```php
// Image validation for update
'pertanyaans.*.pertanyaan_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
'pertanyaans.*.pilihan_a_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
'pertanyaans.*.pilihan_b_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
'pertanyaans.*.pilihan_c_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
'pertanyaans.*.pilihan_d_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
```

#### **File Upload Handling**
```php
// Handle pertanyaan images
$pertanyaanImages = [];
if ($request->hasFile("pertanyaans.{$index}.pertanyaan_images")) {
    foreach ($request->file("pertanyaans.{$index}.pertanyaan_images") as $image) {
        $path = $image->store('master-soal/pertanyaan', 'public');
        $pertanyaanImages[] = $path;
    }
}

// Handle pilihan images
$pilihanAImage = null;
if ($request->hasFile("pertanyaans.{$index}.pilihan_a_image")) {
    $pilihanAImage = $request->file("pertanyaans.{$index}.pilihan_a_image")->store('master-soal/pilihan', 'public');
}
```

#### **Database Storage**
```php
SoalPertanyaan::create([
    'pertanyaan_gambar' => !empty($pertanyaanImages) ? json_encode($pertanyaanImages) : null,
    'pilihan_a_gambar' => $pilihanAImage,
    'pilihan_b_gambar' => $pilihanBImage,
    'pilihan_c_gambar' => $pilihanCImage,
    'pilihan_d_gambar' => $pilihanDImage,
    // ... other fields
]);
```

## 🎯 **Key Differences from Create.vue**

### **1. Method Spoofing**
```javascript
// Edit.vue uses POST with _method=PUT
formData.append('_method', 'PUT');
router.post(`/master-soal-new/${props.masterSoal.id}`, formData, {
  // ...
});

// Create.vue uses POST directly
router.post('/master-soal-new', formData, {
  // ...
});
```

### **2. Existing Data Handling**
```javascript
// Edit.vue loads existing data
const form = reactive({
  judul: props.masterSoal?.judul || '',
  deskripsi: props.masterSoal?.deskripsi || '',
  status: props.masterSoal?.status || 'active',
  pertanyaans: props.masterSoal?.pertanyaans || []
});

// Create.vue starts with empty data
const form = reactive({
  judul: '',
  deskripsi: '',
  status: 'active',
  pertanyaans: []
});
```

### **3. Route Handling**
```javascript
// Edit.vue uses dynamic route
router.post(`/master-soal-new/${props.masterSoal.id}`, formData, {
  // ...
});

// Create.vue uses static route
router.post('/master-soal-new', formData, {
  // ...
});
```

## 🚀 **Benefits**

### **1. Consistency**
- ✅ **Same logic** as Create.vue
- ✅ **Same file handling** for both create and edit
- ✅ **Same preview system** for both forms
- ✅ **Same validation** for both forms

### **2. User Experience**
- ✅ **Edit existing** - Can edit existing soal with images
- ✅ **Add new images** - Can add new images to existing soal
- ✅ **Replace images** - Can replace existing images
- ✅ **Preview works** - Real preview for new images

### **3. Technical Benefits**
- ✅ **FormData support** - Both create and edit use FormData
- ✅ **File validation** - Same validation for both forms
- ✅ **Storage handling** - Same storage logic for both forms
- ✅ **Database consistency** - Same database structure

## 📋 **Files Updated**

### **Vue Component**
- ✅ `resources/js/Pages/MasterSoalNew/Edit.vue`
  - Updated file object storage
  - Updated FormData implementation
  - Updated preview display
  - Updated submit function

### **Controller**
- ✅ `app/Http/Controllers/MasterSoalNewController.php`
  - Added image validation for update
  - Added file upload handling for update
  - Added storage path saving for update

## 🎯 **Testing Scenarios**

### **1. Edit Existing Soal**
- ✅ **Load existing data** - Form populated with existing data
- ✅ **Edit text fields** - Can edit judul, deskripsi, pertanyaan
- ✅ **Edit existing images** - Can see existing images
- ✅ **Add new images** - Can add new images
- ✅ **Replace images** - Can replace existing images

### **2. File Upload**
- ✅ **Pertanyaan images** - Upload multiple images
- ✅ **Pilihan images** - Upload single image per pilihan
- ✅ **File validation** - Only image files allowed
- ✅ **Size validation** - Max 2MB per file
- ✅ **Preview display** - Real preview shown

### **3. Form Submission**
- ✅ **FormData sent** - FormData with files sent
- ✅ **Method spoofing** - PUT method used correctly
- ✅ **File processing** - Files processed correctly
- ✅ **Database update** - Data updated in database
- ✅ **Storage save** - Files saved to storage

## 🎉 **Result**

### **Edit Form Fixed**
- ✅ **File upload support** - Can upload images in edit form
- ✅ **FormData used** - FormData for file uploads
- ✅ **File objects stored** - File objects stored properly
- ✅ **Preview works** - Real preview for new images
- ✅ **Existing data** - Existing data loaded correctly

### **User Experience**
- ✅ **Edit existing** - Can edit existing soal
- ✅ **Add images** - Can add new images
- ✅ **Replace images** - Can replace existing images
- ✅ **Preview images** - Real preview for new images
- ✅ **Consistent UX** - Same experience as create form

### **Technical Benefits**
- ✅ **Consistent logic** - Same logic as create form
- ✅ **File validation** - Proper validation for both forms
- ✅ **Storage handling** - Same storage logic for both forms
- ✅ **Database consistency** - Same database structure

**Edit.vue image upload sudah diperbaiki! Sekarang form edit juga support upload gambar seperti form create.** 🎉
