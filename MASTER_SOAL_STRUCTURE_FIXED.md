# Master Soal - Struktur yang Benar

## 🎯 **Konsep yang Benar**

**1 Judul Soal** → **Bisa berisi beberapa pertanyaan**

### Contoh:
- **Judul**: "Matematika Dasar"
  - Pertanyaan 1: "Berapa hasil dari 5 + 3?" (Pilihan Ganda)
  - Pertanyaan 2: "Berapa hasil dari 10 - 4?" (Pilihan Ganda)  
  - Pertanyaan 3: "Jelaskan cara menghitung luas persegi panjang!" (Essay)
  - Pertanyaan 4: "Apakah 15 adalah bilangan ganjil?" (Ya/Tidak)

## 📊 **Struktur Database yang Benar**

### 1. **Tabel `master_soal`** (Judul Soal)
```sql
- id (BIGINT, PRIMARY KEY)
- judul (VARCHAR(255)) - Judul soal
- deskripsi (TEXT) - Deskripsi soal
- kategori_id (BIGINT, FK) - Kategori soal
- waktu_total_detik (INT) - Waktu total untuk semua pertanyaan
- skor_total (DECIMAL) - Total skor untuk semua pertanyaan
- status (ENUM) - Status soal
- created_by, updated_by (BIGINT, FK ke users)
- created_at, updated_at (TIMESTAMP)
```

### 2. **Tabel `soal_pertanyaan`** (Pertanyaan Individual)
```sql
- id (BIGINT, PRIMARY KEY)
- master_soal_id (BIGINT, FK ke master_soal)
- urutan (INT) - Urutan pertanyaan (1, 2, 3, dst)
- tipe_soal (ENUM) - essay, pilihan_ganda, yes_no
- pertanyaan (TEXT) - Teks pertanyaan
- waktu_detik (INT) - Waktu untuk pertanyaan ini
- jawaban_benar (VARCHAR) - Jawaban benar
- pilihan_a, pilihan_b, pilihan_c, pilihan_d (VARCHAR) - Untuk pilihan ganda
- skor (DECIMAL) - Skor untuk pertanyaan ini
- status (ENUM) - Status pertanyaan
- created_at, updated_at (TIMESTAMP)
```

### 3. **Kategori dihapus** sesuai permintaan

## 🚀 **File yang Sudah Dibuat**

### Database
- ✅ `create_master_soal_tables_fixed.sql` - Query SQL dengan struktur yang benar

### Models
- ✅ `app/Models/MasterSoal.php` - Model untuk judul soal
- ✅ `app/Models/SoalPertanyaan.php` - Model untuk pertanyaan individual

### Controller
- ✅ `app/Http/Controllers/MasterSoalNewController.php` - Controller dengan struktur yang benar

### Views
- ✅ `resources/js/Pages/MasterSoalNew/Index.vue` - Daftar judul soal
- ✅ `resources/js/Pages/MasterSoalNew/Create.vue` - Form dengan card pertanyaan

### Routes
- ✅ Routes untuk `/master-soal-new/*` sudah ditambahkan

## 🎨 **Fitur yang Tersedia**

### 1. **Daftar Judul Soal**
- Menampilkan judul soal dengan jumlah pertanyaan
- Filter berdasarkan status
- Informasi waktu total dan skor total

### 2. **Form Create/Edit**
- **Informasi Soal**: Judul, deskripsi, waktu total, status
- **Pertanyaan Cards**: Setiap pertanyaan ditampilkan dalam card terpisah
- **Dynamic Form**: Bisa tambah/hapus pertanyaan
- **Tipe Soal**: Essay, Pilihan Ganda, Ya/Tidak dengan form yang sesuai

### 3. **Card Pertanyaan**
- Setiap pertanyaan ditampilkan dalam card terpisah
- Form dinamis berdasarkan tipe soal
- Validasi per pertanyaan
- Urutan pertanyaan otomatis

## 📋 **Cara Implementasi**

### Step 1: Jalankan Database
```bash
mysql -u username -p database_name < create_master_soal_tables_fixed.sql
```

### Step 2: Test Fitur
- Akses `/master-soal-new` untuk melihat daftar judul soal
- Klik "Tambah Soal" untuk membuat judul soal baru
- Tambahkan pertanyaan dengan klik "Tambah Pertanyaan"
- Setiap pertanyaan akan muncul dalam card terpisah

### Step 3: Update Menu (Opsional)
Jika ingin mengganti menu lama dengan yang baru:
```javascript
// Di AppLayout.vue, ganti route
{ name: () => 'Master Soal', icon: 'fa-solid fa-clipboard-question', route: '/master-soal-new', code: 'master_soal' }
```

## 🔄 **Perbedaan dengan Struktur Lama**

### ❌ **Struktur Lama (Salah)**
- 1 judul = 1 pertanyaan
- Semua data dalam 1 tabel
- Tidak fleksibel

### ✅ **Struktur Baru (Benar)**
- 1 judul = banyak pertanyaan
- 2 tabel terpisah (master_soal + soal_pertanyaan)
- Fleksibel dan scalable

## 🎯 **Contoh Penggunaan**

### 1. **Buat Judul Soal**
- Judul: "Matematika Kelas 5"
- Deskripsi: "Soal matematika untuk kelas 5 SD"
- Waktu Total: 30 menit

### 2. **Tambah Pertanyaan**
- **Pertanyaan 1**: "Berapa hasil dari 5 + 3?" (Pilihan Ganda, 2 menit, 1 skor)
- **Pertanyaan 2**: "Jelaskan cara menghitung luas!" (Essay, 10 menit, 2 skor)
- **Pertanyaan 3**: "Apakah 10 adalah bilangan genap?" (Ya/Tidak, 1 menit, 1 skor)

### 3. **Hasil**
- 1 judul soal dengan 3 pertanyaan
- Total waktu: 13 menit
- Total skor: 4

## 📁 **File Structure**
```
app/Models/
├── MasterSoal.php (Judul soal)
├── SoalPertanyaan.php (Pertanyaan individual)
└── KategoriSoal.php (Kategori)

app/Http/Controllers/
└── MasterSoalNewController.php

resources/js/Pages/MasterSoalNew/
├── Index.vue (Daftar judul soal)
├── Create.vue (Form dengan card pertanyaan)
├── Edit.vue (Edit dengan card pertanyaan)
└── Show.vue (Detail dengan daftar pertanyaan)
```

## 🎉 **Keunggulan Struktur Baru**

1. **Fleksibel**: 1 judul bisa punya banyak pertanyaan
2. **Scalable**: Mudah ditambah pertanyaan baru
3. **Organized**: Data terstruktur dengan baik
4. **User Friendly**: Card pertanyaan mudah dipahami
5. **Maintainable**: Kode lebih mudah di-maintain

Sekarang struktur sudah benar sesuai dengan kebutuhan: **1 Judul Soal → Banyak Pertanyaan** dengan tampilan card yang user-friendly!
