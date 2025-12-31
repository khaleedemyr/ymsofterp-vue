# Web Profile Tables Structure

## Daftar Table

### 1. `web_profile_pages`
Table untuk menyimpan halaman website.

**Columns:**
- `id` - Primary key
- `title` - Judul halaman
- `slug` - URL-friendly slug (unique)
- `content` - Konten halaman (HTML/text)
- `meta_title` - Meta title untuk SEO
- `meta_description` - Meta description untuk SEO
- `is_published` - Status publish (0/1)
- `order` - Urutan tampil
- `created_at` - Timestamp
- `updated_at` - Timestamp

### 2. `web_profile_page_sections`
Table untuk menyimpan sections/flexible content per halaman.

**Columns:**
- `id` - Primary key
- `page_id` - Foreign key ke `web_profile_pages`
- `type` - Tipe section (hero, content, gallery, testimonial, etc)
- `title` - Judul section
- `content` - Konten section
- `data` - Data tambahan dalam format JSON
- `order` - Urutan tampil
- `is_active` - Status aktif (0/1)
- `created_at` - Timestamp
- `updated_at` - Timestamp

### 3. `web_profile_menu_items`
Table untuk menyimpan menu items website.

**Columns:**
- `id` - Primary key
- `label` - Label menu
- `url` - URL manual (jika tidak link ke page)
- `page_id` - Foreign key ke `web_profile_pages` (nullable)
- `parent_id` - ID parent menu (untuk submenu)
- `order` - Urutan tampil
- `is_active` - Status aktif (0/1)
- `created_at` - Timestamp
- `updated_at` - Timestamp

### 4. `web_profile_galleries`
Table untuk menyimpan gallery/foto.

**Columns:**
- `id` - Primary key
- `title` - Judul foto
- `description` - Deskripsi foto
- `image_path` - Path ke file gambar
- `category` - Kategori foto
- `order` - Urutan tampil
- `is_active` - Status aktif (0/1)
- `created_at` - Timestamp
- `updated_at` - Timestamp

### 5. `web_profile_settings`
Table untuk menyimpan settings website.

**Columns:**
- `id` - Primary key
- `key` - Key setting (unique)
- `value` - Value setting
- `type` - Tipe data (text, image, json)
- `created_at` - Timestamp
- `updated_at` - Timestamp

### 6. `web_profile_contacts`
Table untuk menyimpan submission form kontak.

**Columns:**
- `id` - Primary key
- `name` - Nama pengirim
- `email` - Email pengirim
- `phone` - Nomor telepon (nullable)
- `subject` - Subjek pesan (nullable)
- `message` - Isi pesan
- `is_read` - Status sudah dibaca (0/1)
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Cara Membuat Table

### Via Migration (Laravel)
```bash
php artisan migrate
```

### Via SQL Langsung
Jalankan file: `WEB_PROFILE_TABLES_CREATE.sql`

## Cara Menghapus Table

### Via Migration (Laravel)
```bash
php artisan migrate:rollback --step=1
```

### Via SQL Langsung
Jalankan file: `WEB_PROFILE_TABLES_DROP.sql`
**HATI-HATI: Akan menghapus semua data!**

