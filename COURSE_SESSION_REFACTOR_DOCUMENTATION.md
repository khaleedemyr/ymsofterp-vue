# Course Session Refactoring Documentation

## **🎯 Overview**

Refactor menu course yang sudah ada untuk mendukung **session-based curriculum** dengan item yang fleksibel (quiz, materi, questionnaire). Setiap sesi bisa memiliki kombinasi item yang berbeda-beda dengan urutan yang bisa diatur secara dinamis.

## **✨ Fitur Utama**

### **1. Session Management**
- ✅ **Multiple Sessions**: Setiap course bisa memiliki multiple sesi
- ✅ **Flexible Ordering**: Urutan sesi bisa diatur bebas
- ✅ **Session Metadata**: Title, description, duration, status

### **2. Flexible Item Types**
- ✅ **Quiz**: Test dan evaluasi
- ✅ **Material**: Konten pembelajaran (PDF, video, dokumen)
- ✅ **Questionnaire**: Survey dan feedback

### **3. Dynamic Item Arrangement**
- ✅ **Custom Order**: Urutan item dalam sesi bisa diatur bebas
- ✅ **Mixed Types**: Satu sesi bisa berisi quiz + materi + questionnaire
- ✅ **Flexible Duration**: Setiap item bisa punya durasi sendiri

## **🏗️ Struktur Database Baru**

### **1. Tabel `lms_sessions`**
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

### **2. Tabel `lms_session_items`**
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

### **3. Tabel `lms_curriculum_materials`**
```sql
- id: Primary key
- title: Judul material
- description: Deskripsi material
- content: Konten HTML
- file_path: Path file
- file_type: pdf/image/video/document/link
- estimated_duration_minutes: Estimasi durasi
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

## **📱 UI/UX Changes**

### **1. Form Create Course**
- **Section Baru**: "Sesi Training (Fleksibel)"
- **Session Management**: Add/remove sessions
- **Item Management**: Add/remove items per session
- **Drag & Drop**: Reorder sessions and items
- **Validation**: Ensure each session has items

### **2. Course Detail View**
- **Session Display**: Hierarchical view of sessions
- **Item Icons**: Different icons for quiz/material/questionnaire
- **Duration Info**: Individual and total durations
- **Item Count**: Number of items per session

### **3. Responsive Design**
- **Mobile Friendly**: Works on all screen sizes
- **Touch Support**: Swipe gestures for mobile
- **Accessibility**: Screen reader friendly

## **🔧 Technical Implementation**

### **1. Backend Changes**

#### **Models**
- `LmsCourse`: Added sessions relationship
- `LmsSession`: New model for session management
- `LmsSessionItem`: New model for flexible items
- `LmsCurriculumMaterial`: New model for materials

#### **Controllers**
- `LmsController`: Updated storeCourse method
- **Validation**: Added session and item validation
- **Data Processing**: Handle nested session data
- **Legacy Support**: Maintain backward compatibility

#### **Database**
- **Migration**: `refactor_course_sessions.sql`
- **Foreign Keys**: Proper relationships
- **Indexes**: Performance optimization
- **Views**: Easy session management

### **2. Frontend Changes**

#### **Vue Components**
- **Courses.vue**: Updated form with session management
- **CourseDetail.vue**: Display sessions and items
- **Session Management**: Add/remove/reorder functions

#### **Form Handling**
- **Dynamic Fields**: Sessions and items
- **Validation**: Client-side validation
- **Data Submission**: Proper FormData handling

## **🚀 Migration Guide**

### **1. Database Migration**
```bash
# Run the migration script
mysql -u username -p database_name < database/sql/refactor_course_sessions.sql
```

### **2. Code Deployment**
```bash
# Update models and controllers
# Deploy frontend changes
# Test functionality
```

### **3. Data Migration**
- **Existing Courses**: Automatically get default session
- **Legacy Support**: Old curriculum still works
- **Gradual Migration**: Move courses to new structure

## **✅ Benefits**

### **1. Flexibility**
- **Custom Arrangement**: Set urutan sesuai kebutuhan
- **Mixed Content**: Quiz, materi, questionnaire dalam satu sesi
- **Dynamic Structure**: Mudah diubah tanpa hapus course

### **2. Scalability**
- **Unlimited Sessions**: Tidak ada batasan jumlah sesi
- **Unlimited Items**: Tidak ada batasan item per sesi
- **Performance**: Optimized database queries

### **3. User Experience**
- **Intuitive Interface**: Mudah digunakan
- **Visual Feedback**: Clear session structure
- **Mobile Friendly**: Responsive design

## **⚠️ Important Notes**

### **1. Backward Compatibility**
- ✅ **Legacy Support**: Old curriculum still works
- ✅ **Gradual Migration**: No forced migration
- ✅ **Data Preservation**: No data loss

### **2. Performance**
- ✅ **Optimized Queries**: Efficient database access
- ✅ **Lazy Loading**: Load sessions on demand
- ✅ **Caching**: Session data caching

### **3. Security**
- ✅ **Input Validation**: Server-side validation
- ✅ **Access Control**: User permission checks
- ✅ **Data Sanitization**: XSS protection

## **🔍 Testing**

### **1. Unit Tests**
- **Model Tests**: Session and item creation
- **Controller Tests**: Session management
- **Validation Tests**: Form validation

### **2. Integration Tests**
- **API Tests**: Session endpoints
- **Database Tests**: Data integrity
- **Frontend Tests**: UI functionality

### **3. User Acceptance Tests**
- **Create Course**: Test session creation
- **Edit Course**: Test session modification
- **View Course**: Test session display

## **📚 API Endpoints**

### **1. Course Management**
```
POST /lms/courses - Create course with sessions
PUT /lms/courses/{id} - Update course sessions
GET /lms/courses/{id} - Get course with sessions
```

### **2. Session Management**
```
POST /lms/courses/{id}/sessions - Add session
PUT /lms/courses/{id}/sessions/{session_id} - Update session
DELETE /lms/courses/{id}/sessions/{session_id} - Delete session
```

### **3. Item Management**
```
POST /lms/sessions/{id}/items - Add item
PUT /lms/sessions/{id}/items/{item_id} - Update item
DELETE /lms/sessions/{id}/items/{item_id} - Delete item
```

## **🎉 Conclusion**

Refactor ini memberikan **fleksibilitas maksimal** untuk mengatur curriculum training dengan:

- **Session-based structure** yang mudah dikelola
- **Flexible item types** (quiz, materi, questionnaire)
- **Dynamic ordering** yang bisa di-custom
- **Backward compatibility** untuk existing courses
- **Modern UI/UX** yang responsive dan intuitive

Sistem baru ini memungkinkan admin training untuk membuat curriculum yang lebih engaging dan sesuai dengan kebutuhan pembelajaran modern.
