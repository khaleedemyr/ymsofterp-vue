# New Flexible Curriculum Structure

## **🎯 Overview**

Sistem kurikulum baru yang **fleksibel** dan **dinamis**, memungkinkan setiap sesi memiliki kombinasi item yang berbeda-beda.

## **🏗️ Struktur Database Baru**

### **1. Tabel `lms_sessions` (Container Utama)**
```sql
- id: Primary key
- course_id: Reference ke course
- session_number: Nomor urut sesi
- session_title: Judul sesi
- session_description: Deskripsi sesi
- order_number: Urutan sesi dalam course
- is_required: Apakah sesi wajib
- estimated_duration_minutes: Estimasi durasi
- status: active/inactive
- created_by, updated_by: User tracking
- timestamps: created_at, updated_at, deleted_at
```

### **2. Tabel `lms_session_items` (Item Fleksibel)**
```sql
- id: Primary key
- session_id: Reference ke session
- item_type: quiz/material/questionnaire
- item_id: Reference ke quiz/material/questionnaire
- title: Judul custom (optional)
- description: Deskripsi custom (optional)
- order_number: Urutan item dalam session
- is_required: Apakah item wajib
- estimated_duration_minutes: Estimasi durasi item
- status: active/inactive
- created_by, updated_by: User tracking
- timestamps: created_at, updated_at, deleted_at
```

## **🔄 Contoh Struktur Fleksibel**

### **Session 1: Introduction**
```
- Quiz: Pre Test (durasi: 15 menit)
- Material: Course Overview (durasi: 20 menit)
- Material: Learning Objectives (durasi: 10 menit)
```

### **Session 2: Core Content**
```
- Material: Main Content (durasi: 45 menit)
- Quiz: Mid Test (durasi: 20 menit)
```

### **Session 3: Practice**
```
- Material: Practice Exercise (durasi: 30 menit)
- Questionnaire: Feedback Form (durasi: 10 menit)
- Quiz: Final Test (durasi: 25 menit)
```

## **✨ Keunggulan Sistem Baru**

### **1. Fleksibilitas**
- ✅ Setiap sesi bisa punya kombinasi item yang berbeda
- ✅ Bisa multiple quiz dalam satu sesi
- ✅ Bisa multiple material dalam satu sesi
- ✅ Urutan item bisa diatur bebas

### **2. Scalability**
- ✅ Mudah tambah/hapus item
- ✅ Mudah reorder item
- ✅ Mudah manage durasi per item

### **3. User Experience**
- ✅ Interface yang lebih intuitif
- ✅ Drag & drop untuk reorder
- ✅ Preview durasi total per sesi

## **🚀 Implementation Steps**

### **Step 1: Run Database Refactor**
```bash
php run_refactor_curriculum.php
```

### **Step 2: Update Models**
- Create `LmsSession` model
- Create `LmsSessionItem` model
- Update relationships

### **Step 3: Update Controller**
- Refactor `LmsCurriculumController`
- Add methods for managing session items
- Support multiple items per session

### **Step 4: Update Frontend**
- New Vue components for flexible items
- Drag & drop interface
- Dynamic item management

## **🔧 API Endpoints Baru**

### **Sessions Management**
```
GET    /lms/courses/{course}/sessions
POST   /lms/courses/{course}/sessions
PUT    /lms/courses/{course}/sessions/{session}
DELETE /lms/courses/{course}/sessions/{session}
```

### **Session Items Management**
```
GET    /lms/sessions/{session}/items
POST   /lms/sessions/{session}/items
PUT    /lms/sessions/{session}/items/{item}
DELETE /lms/sessions/{session}/items/{item}
POST   /lms/sessions/{session}/items/reorder
```

## **📱 Frontend Components Baru**

### **1. SessionCard.vue**
- Header dengan judul dan durasi total
- List item yang bisa di-reorder
- Add/Edit/Delete session

### **2. SessionItemList.vue**
- List item dalam session
- Drag & drop untuk reorder
- Add new item button

### **3. AddItemModal.vue**
- Modal untuk tambah item baru
- Pilih tipe: Quiz/Material/Questionnaire
- Set durasi dan requirement

### **4. ItemCard.vue**
- Card untuk setiap item
- Show type, title, duration
- Edit/Delete actions

## **🎨 UI/UX Improvements**

### **1. Visual Hierarchy**
- Session sebagai container utama
- Items sebagai sub-items dengan indent
- Clear visual separation

### **2. Interactive Elements**
- Drag & drop untuk reorder
- Expand/collapse session details
- Quick actions (add, edit, delete)

### **3. Information Display**
- Total duration per session
- Item count per session
- Progress indicators

## **📊 Data Migration**

### **From Old Structure**
```
lms_curriculum_items (old)
├── course_id, session_number, session_title
├── quiz_id, questionnaire_id (rigid)
└── single item per session
```

### **To New Structure**
```
lms_sessions (new container)
├── course_id, session_number, session_title
└── lms_session_items (flexible)
    ├── quiz items
    ├── material items
    └── questionnaire items
```

## **🔍 Benefits for Users**

### **For Course Creators**
- ✅ Lebih fleksibel dalam merancang kurikulum
- ✅ Bisa buat sesi yang bervariasi
- ✅ Mudah manage durasi dan urutan

### **For Learners**
- ✅ Experience yang lebih terstruktur
- ✅ Bisa lihat progress per item
- ✅ Interface yang lebih jelas

## **📝 Next Steps**

1. **Run database refactor**: `php run_refactor_curriculum.php`
2. **Create new models**: `LmsSession`, `LmsSessionItem`
3. **Update controller**: Support flexible items
4. **Create new frontend**: Flexible item management
5. **Test functionality**: Add/edit/reorder items

---

**Status**: 🚧 **REFACTOR IN PROGRESS** - Sistem baru yang lebih fleksibel sedang dibuat.
