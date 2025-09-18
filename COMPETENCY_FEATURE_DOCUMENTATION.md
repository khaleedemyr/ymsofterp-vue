# Competency Feature Documentation

## Overview
Fitur competency memungkinkan setiap training course untuk memiliki multiple kompetensi yang akan dikembangkan. Setiap kompetensi memiliki level kemahiran (proficiency level) yang dapat disesuaikan.

## Fitur Utama

### 1. Competency Management
- **Multiple Competencies per Course**: Setiap course dapat memiliki banyak kompetensi
- **Proficiency Levels**: Setiap kompetensi memiliki level kemahiran (Basic, Intermediate, Advanced, Expert)
- **Notes**: Catatan khusus untuk setiap kompetensi
- **Categories**: Kompetensi dikelompokkan berdasarkan kategori (Technical, Soft Skills, Leadership, dll.)

### 2. Competency Categories
- **Technical**: Programming, Database Management, System Administration, dll.
- **Soft Skills**: Communication, Teamwork, Time Management, dll.
- **Leadership**: Strategic Thinking, Decision Making, Team Leadership, dll.
- **Communication**: Public Speaking, Written Communication, Presentation Skills, dll.
- **Management**: Project Management, Budget Management, Resource Planning, dll.
- **Analytical**: Data Analysis, Critical Thinking, Research Skills, dll.
- **Creative**: Creative Thinking, Design Thinking, Innovation, dll.

## Database Structure

### 1. `competencies` Table
```sql
- id (Primary Key)
- name (Competency name)
- description (Competency description)
- category (Competency category)
- level (beginner/intermediate/advanced)
- is_active (Active status)
- created_at, updated_at, deleted_at
```

### 2. `course_competencies` Table (Pivot)
```sql
- id (Primary Key)
- course_id (Foreign Key to lms_courses)
- competency_id (Foreign Key to competencies)
- proficiency_level (basic/intermediate/advanced/expert)
- notes (Optional notes)
- created_at, updated_at
```

## Models

### 1. Competency Model
```php
class Competency extends Model
{
    // Relationships
    public function courses() // Many-to-many with LmsCourse
    
    // Scopes
    public function scopeActive($query)
    public function scopeByCategory($query, $category)
    public function scopeByLevel($query, $level)
    
    // Static Methods
    public static function getCategories()
    public static function getLevels()
    public static function getProficiencyLevels()
}
```

### 2. LmsCourse Model (Updated)
```php
class LmsCourse extends Model
{
    // New Relationship
    public function competencies() // Many-to-many with Competency
    
    // New Accessors
    public function getCompetencyNamesAttribute()
    public function getCompetencyNamesStringAttribute()
    public function getCompetenciesWithLevelsAttribute()
}
```

## Frontend Implementation

### 1. Course Form
- **Competency Selection**: Checkbox list dengan search functionality
- **Proficiency Level**: Dropdown untuk setiap kompetensi yang dipilih
- **Notes**: Input field untuk catatan khusus
- **Dynamic Management**: Add/remove kompetensi secara dinamis

### 2. Course Display
- **Competency Badges**: Menampilkan kompetensi dengan level kemahiran
- **Limited Display**: Maksimal 3 kompetensi ditampilkan, sisanya dengan "+X lainnya"
- **Tooltip**: Hover untuk melihat detail kompetensi

### 3. Form Structure
```javascript
form: {
  competencies: {
    [competencyId]: {
      competency_id: id,
      proficiency_level: 'basic|intermediate|advanced|expert',
      notes: 'optional notes'
    }
  }
}
```

## Backend Implementation

### 1. Controller Updates
- **Validation**: Validasi kompetensi dan proficiency level
- **Sync Logic**: Sync kompetensi dengan course menggunakan pivot table
- **Data Loading**: Eager load kompetensi untuk performa optimal

### 2. API Endpoints
- **GET /lms/courses**: Include competencies data
- **POST /lms/courses**: Handle competency data in form submission
- **PUT /lms/courses/{id}**: Update course competencies

## Usage Examples

### 1. Creating Course with Competencies
```javascript
// Frontend form data
{
  title: "Advanced Programming Course",
  competencies: {
    1: { competency_id: 1, proficiency_level: 'advanced', notes: 'Focus on OOP' },
    2: { competency_id: 2, proficiency_level: 'intermediate', notes: 'Database design' }
  }
}
```

### 2. Displaying Competencies
```vue
<!-- Course card display -->
<div v-if="course.competencies && course.competencies.length > 0">
  <div class="flex flex-wrap gap-1">
    <span v-for="competency in course.competencies.slice(0, 3)" 
          :key="competency.id" 
          class="competency-badge">
      {{ competency.name }} ({{ competency.pivot.proficiency_level }})
    </span>
  </div>
</div>
```

## Seeder Data

### CompetencySeeder
- **30+ Predefined Competencies**: Mencakup berbagai kategori
- **Realistic Data**: Kompetensi yang relevan dengan dunia kerja
- **Categorized**: Terorganisir berdasarkan kategori yang jelas

## Benefits

### 1. For Training Management
- **Clear Learning Objectives**: Setiap course memiliki kompetensi yang jelas
- **Progress Tracking**: Dapat melacak pengembangan kompetensi peserta
- **Skill Mapping**: Mapping kompetensi dengan jabatan/divisi

### 2. For Participants
- **Clear Expectations**: Tahu kompetensi apa yang akan dikembangkan
- **Skill Development**: Fokus pada pengembangan kompetensi spesifik
- **Career Path**: Memahami kompetensi yang dibutuhkan untuk karir

### 3. For Organization
- **Competency Framework**: Framework kompetensi yang terstruktur
- **Training ROI**: Mengukur efektivitas training berdasarkan kompetensi
- **Talent Development**: Mengembangkan talent sesuai kebutuhan organisasi

## Future Enhancements

### 1. Competency Assessment
- **Pre/Post Assessment**: Assessment sebelum dan sesudah training
- **Competency Gap Analysis**: Analisis gap kompetensi
- **Progress Tracking**: Tracking perkembangan kompetensi per peserta

### 2. Advanced Features
- **Competency Matrix**: Matrix kompetensi per jabatan
- **Learning Path**: Path belajar berdasarkan kompetensi
- **Certification**: Sertifikasi berdasarkan pencapaian kompetensi

## Technical Notes

### 1. Performance Considerations
- **Eager Loading**: Competencies di-load bersamaan dengan course
- **Indexing**: Index pada course_id dan competency_id untuk performa optimal
- **Caching**: Cache kompetensi yang sering digunakan

### 2. Data Integrity
- **Foreign Key Constraints**: Memastikan referential integrity
- **Soft Deletes**: Competencies menggunakan soft delete
- **Validation**: Validasi level kemahiran dan data kompetensi

### 3. Scalability
- **Pagination**: Support pagination untuk kompetensi
- **Search**: Search kompetensi berdasarkan nama dan kategori
- **Filtering**: Filter kompetensi berdasarkan kategori dan level
