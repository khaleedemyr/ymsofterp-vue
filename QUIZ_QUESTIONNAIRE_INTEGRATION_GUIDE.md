# Panduan Integrasi Quiz dan Kuesioner dengan Curriculum Materials

## Masalah yang Ditemukan

Berdasarkan analisis database, tabel `lms_curriculum_materials` belum memiliki kolom untuk menyimpan referensi ke quiz dan kuesioner yang dipilih. Akibatnya:

1. **Judul quiz/kuesioner tidak bisa ditampilkan** di course detail
2. **Data quiz/kuesioner tidak terhubung** dengan curriculum materials
3. **Tampilan material menjadi tidak lengkap** dan kurang informatif

## Solusi yang Diberikan

### 1. Migration Database

File: `add_quiz_questionnaire_to_curriculum_materials.sql`

```sql
-- Menambahkan kolom baru
ALTER TABLE lms_curriculum_materials 
ADD COLUMN quiz_id BIGINT UNSIGNED NULL AFTER file_type,
ADD COLUMN questionnaire_id BIGINT UNSIGNED NULL AFTER quiz_id;

-- Menambahkan foreign key constraints
ALTER TABLE lms_curriculum_materials 
ADD CONSTRAINT fk_curriculum_materials_quiz 
FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL;

ALTER TABLE lms_curriculum_materials 
ADD CONSTRAINT fk_curriculum_materials_questionnaire 
FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE SET NULL;

-- Menambahkan index untuk performa
CREATE INDEX idx_curriculum_materials_quiz_id ON lms_curriculum_materials(quiz_id);
CREATE INDEX idx_curriculum_materials_questionnaire_id ON lms_curriculum_materials(questionnaire_id);
```

### 2. Script PHP untuk Migration

File: `run_quiz_questionnaire_migration.php`

Script ini akan:
- Mengecek apakah kolom sudah ada
- Menambahkan kolom yang diperlukan
- Menambahkan foreign key constraints
- Membuat index untuk performa
- Menampilkan struktur tabel yang baru

## Cara Menjalankan Migration

### Opsi 1: Menggunakan Script PHP (Direkomendasikan)

```bash
# Pastikan konfigurasi database sudah benar di file PHP
php run_quiz_questionnaire_migration.php
```

### Opsi 2: Menggunakan SQL Langsung

```bash
# Buka phpMyAdmin atau MySQL client
mysql -u username -p database_name < add_quiz_questionnaire_to_curriculum_materials.sql
```

## Struktur Tabel Setelah Migration

```sql
DESCRIBE lms_curriculum_materials;

+------------------------+------------------+------+-----+---------+----------------+
| Field                  | Type             | Null | Key | Default | Extra          |
+------------------------+------------------+------+-----+---------+----------------+
| id                     | bigint unsigned  | NO   | PRI | NULL    | auto_increment |
| title                  | varchar(255)     | NO   |     | NULL    |                |
| description            | text             | YES  |     | NULL    |                |
| file_path              | varchar(255)     | YES  |     | NULL    |                |
| file_type              | varchar(50)      | YES  |     | NULL    |                |
| quiz_id                | bigint unsigned  | YES  | MUL | NULL    |                | ← NEW
| questionnaire_id       | bigint unsigned  | YES  | MUL | NULL    |                | ← NEW
| estimated_duration_minutes | int          | YES  |     | NULL    |                |
| status                 | varchar(50)      | YES  |     | NULL    |                |
| created_by             | bigint unsigned  | YES  |     | NULL    |                |
| updated_by             | bigint unsigned  | YES  |     | NULL    |                |
| created_at             | timestamp        | YES  |     | NULL    |                |
| updated_at             | timestamp        | YES  |     | NULL    |                |
+------------------------+------------------+------+-----+---------+----------------+
```

## Cara Menggunakan Kolom Baru

### 1. Saat Membuat Material Quiz

```php
// Saat menyimpan material dengan tipe quiz
$material = new LmsCurriculumMaterial();
$material->title = 'Quiz Materi 1';
$material->item_type = 'quiz';
$material->quiz_id = $quizId; // ID dari quiz yang dipilih
$material->save();
```

### 2. Saat Membuat Material Kuesioner

```php
// Saat menyimpan material dengan tipe questionnaire
$material = new LmsCurriculumMaterial();
$material->title = 'Kuesioner Feedback';
$material->item_type = 'questionnaire';
$material->questionnaire_id = $questionnaireId; // ID dari kuesioner yang dipilih
$material->save();
```

### 3. Saat Mengambil Data untuk Course Detail

```php
// Di controller atau model
$materials = LmsCurriculumMaterial::with(['quiz', 'questionnaire'])
    ->where('item_type', 'material')
    ->get();

// Atau menggunakan join
$materials = DB::table('lms_curriculum_materials as m')
    ->leftJoin('lms_quizzes as q', 'm.quiz_id', '=', 'q.id')
    ->leftJoin('lms_questionnaires as qu', 'm.questionnaire_id', '=', 'qu.id')
    ->select('m.*', 'q.title as quiz_title', 'qu.title as questionnaire_title')
    ->get();
```

## Update Frontend Vue.js

Setelah migration, frontend sudah bisa menampilkan judul quiz/kuesioner:

```vue
<!-- Di CourseDetail.vue -->
<div class="font-bold text-white text-lg drop-shadow-md mb-1">
  <span v-if="item.item_type === 'quiz' && item.quiz_data">
    {{ item.quiz_data.title || 'Quiz' }}
  </span>
  <span v-else-if="item.item_type === 'questionnaire' && item.questionnaire_data">
    {{ item.questionnaire_data.title || 'Kuesioner' }}
  </span>
  <span v-else>
    {{ item.title || `${item.item_type.charAt(0).toUpperCase() + item.item_type.slice(1)} ${item.order_number}` }}
  </span>
</div>
```

## Keuntungan Setelah Migration

1. **Judul Quiz/Kuesioner Terlihat**: User bisa melihat judul yang sebenarnya dari quiz/kuesioner yang dipilih
2. **Data Terhubung**: Quiz dan kuesioner terhubung langsung dengan curriculum materials
3. **Tampilan Lebih Informatif**: Course detail menjadi lebih lengkap dan mudah dipahami
4. **Integritas Data**: Foreign key constraints memastikan data tetap konsisten
5. **Performa Lebih Baik**: Index pada kolom baru meningkatkan kecepatan query

## Langkah Selanjutnya

1. **Backup Database** sebelum menjalankan migration
2. **Jalankan Migration** menggunakan script yang disediakan
3. **Update Data Existing** jika ada material quiz/kuesioner yang sudah ada
4. **Test Frontend** untuk memastikan judul quiz/kuesioner sudah muncul
5. **Update Logic Backend** untuk menggunakan kolom baru saat membuat material

## Troubleshooting

### Error: "Column already exists"
- Script akan skip kolom yang sudah ada
- Tidak ada data yang hilang

### Error: "Foreign key constraint fails"
- Pastikan tabel `lms_quizzes` dan `lms_questionnaires` sudah ada
- Pastikan data yang direferensikan valid

### Error: "Access denied"
- Periksa permission database user
- Pastikan user memiliki ALTER dan CREATE INDEX permission

## Backup dan Rollback

### Backup Sebelum Migration
```sql
-- Backup struktur tabel
SHOW CREATE TABLE lms_curriculum_materials;

-- Backup data (jika diperlukan)
SELECT * FROM lms_curriculum_materials;
```

### Rollback (jika diperlukan)
```sql
-- Hapus foreign key constraints
ALTER TABLE lms_curriculum_materials 
DROP FOREIGN KEY fk_curriculum_materials_quiz,
DROP FOREIGN KEY fk_curriculum_materials_questionnaire;

-- Hapus kolom
ALTER TABLE lms_curriculum_materials 
DROP COLUMN quiz_id,
DROP COLUMN questionnaire_id;
```
