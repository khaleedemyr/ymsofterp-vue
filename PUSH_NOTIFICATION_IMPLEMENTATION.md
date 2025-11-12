# Push Notification Implementation Guide

## Overview
Fitur push notification untuk mengirim notifikasi ke member aplikasi mobile menggunakan Firebase Cloud Messaging (FCM). Fitur ini menggunakan database kedua (mysql_second) seperti menu Data Member.

## File yang Dibuat

### 1. Models
- ✅ `app/Models/PushNotification.php` - Model untuk tabel pushnotification
- ✅ `app/Models/PushNotificationTarget.php` - Model untuk tabel pushnotification_target
- ✅ `app/Models/PushNotificationProcessSend.php` - Model untuk tabel pushnotification_process_send

### 2. Controller
- ✅ `app/Http/Controllers/PushNotificationController.php` - Controller untuk mengelola push notification

### 3. Vue Pages
- ✅ `resources/js/Pages/PushNotification/Index.vue` - Halaman daftar notifikasi
- ✅ `resources/js/Pages/PushNotification/Create.vue` - Halaman buat notifikasi baru

### 4. Routes
- ✅ Routes ditambahkan di `routes/web.php`

### 5. Menu & Permission
- ✅ SQL file: `database/sql/insert_push_notification_menu.sql`
- ✅ Menu ditambahkan di `resources/js/Layouts/AppLayout.vue`

## Database Schema

### Tabel: `pushnotification` (di database kedua)
```sql
CREATE TABLE pushnotification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    target VARCHAR(255) NOT NULL,
    photo VARCHAR(255) NULL,
    status_send INT DEFAULT 0, -- 0: belum terkirim, 1: terkirim, 2: sedang di proses
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabel: `pushnotification_target` (di database kedua)
```sql
CREATE TABLE pushnotification_target (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email_member VARCHAR(255) NOT NULL,
    token TEXT NOT NULL,
    id_pushnotification INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pushnotification) REFERENCES pushnotification(id) ON DELETE CASCADE
);
```

### Tabel: `pushnotification_process_send` (di database kedua)
```sql
CREATE TABLE pushnotification_process_send (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pushnotification INT NOT NULL,
    status_send INT DEFAULT 0, -- 0: gagal, 1: berhasil
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pushnotification) REFERENCES pushnotification(id) ON DELETE CASCADE
);
```

## Setup

### 1. Database Setup
Pastikan tabel-tabel di atas sudah dibuat di database kedua (mysql_second).

### 2. Environment Variables
Tambahkan FCM Server Key di file `.env`:
```env
FCM_SERVER_KEY=your_fcm_server_key_here
```

Jika tidak ada, akan menggunakan default key dari sample code.

### 3. Folder Storage
Pastikan folder untuk menyimpan foto notifikasi sudah ada:
```bash
mkdir -p public/assets/file_photo_notification
chmod 755 public/assets/file_photo_notification
```

### 4. Menu & Permission
Jalankan SQL untuk menambahkan menu dan permission:
```bash
mysql -u username -p database_name < database/sql/insert_push_notification_menu.sql
```

Atau copy-paste isi file SQL ke phpMyAdmin.

## Cara Menggunakan

### 1. Membuat Push Notification
1. Buka menu **Push Notification** di sidebar (di bawah menu CRM)
2. Klik tombol **Buat Notifikasi Baru**
3. Isi form:
   - **Target Email Member**: 
     - Masukkan email member (pisahkan dengan koma untuk multiple target)
     - Atau ketik "all" untuk mengirim ke semua devices
   - **Title**: Judul notifikasi
   - **Body**: Isi notifikasi
   - **Foto** (opsional): Upload gambar (max 2MB, best resolution 1024x500px)
4. Klik **Simpan & Kirim**

### 2. Mengirim Notifikasi
- Jika target bukan "all", notifikasi akan otomatis terkirim saat dibuat
- Jika target adalah "all", klik tombol **Kirim** (icon paper plane) di tabel untuk mengirim

### 3. Melihat Status
- Status notifikasi ditampilkan di tabel:
  - **belum terkirim** (kuning): Notifikasi belum dikirim
  - **terkirim** (hijau): Notifikasi sudah dikirim
  - **sedang di proses** (biru): Notifikasi sedang diproses

## API Endpoints

### 1. Index
```
GET /push-notification
```
Menampilkan daftar notifikasi.

### 2. Create Form
```
GET /push-notification/create
```
Menampilkan form untuk membuat notifikasi baru.

### 3. Store
```
POST /push-notification
```
Menyimpan notifikasi baru.

**Request Body:**
- `txt_target`: Target email atau "all"
- `txt_title`: Title notifikasi
- `txt_body`: Body notifikasi
- `file_foto`: File gambar (optional)

### 4. Send
```
POST /push-notification/{id}/send
```
Mengirim notifikasi ke semua target.

### 5. Test
```
POST /push-notification/test
```
Testing pengiriman notifikasi.

**Request Body:**
- `token`: FCM token device
- `title`: Title notifikasi
- `body`: Body notifikasi

## Fitur

### ✅ Fitur yang Sudah Diimplementasikan
1. **Create Notification**: Membuat notifikasi baru dengan title, body, dan foto
2. **Target Selection**: 
   - Multiple email (pisahkan dengan koma)
   - Broadcast ke semua devices (ketik "all")
3. **Image Upload**: Upload foto untuk notifikasi (max 2MB)
4. **Send Notification**: Mengirim notifikasi via FCM
5. **Status Tracking**: Melacak status pengiriman notifikasi
6. **Statistics**: Menampilkan total devices dan notifikasi terkirim

### 🔄 Fitur yang Bisa Ditambahkan
1. **Scheduled Send**: Jadwalkan pengiriman notifikasi
2. **Notification Templates**: Template notifikasi yang bisa digunakan berulang
3. **Analytics**: Statistik detail pengiriman (delivered, opened, clicked)
4. **Segment Targeting**: Target berdasarkan segment member
5. **Rich Notifications**: Notifikasi dengan action buttons
6. **A/B Testing**: Test beberapa variasi notifikasi

## Troubleshooting

### Notifikasi Tidak Terkirim
1. **Cek FCM Server Key**: Pastikan FCM_SERVER_KEY di .env sudah benar
2. **Cek Token**: Pastikan member memiliki firebase_token_device yang valid
3. **Cek Network**: Pastikan server bisa mengakses FCM endpoint
4. **Cek Logs**: Lihat Laravel logs untuk error detail

### Foto Tidak Tampil
1. **Cek Folder**: Pastikan folder `public/assets/file_photo_notification` ada dan writable
2. **Cek Permission**: Pastikan folder memiliki permission 755
3. **Cek Path**: Pastikan path di database benar

### Menu Tidak Muncul
1. **Cek Permission**: Pastikan user memiliki permission `push_notification_view`
2. **Cek SQL**: Pastikan SQL menu sudah dijalankan
3. **Cek Code**: Pastikan code menu di AppLayout.vue sesuai dengan code di database

## Security Notes

1. **FCM Server Key**: Simpan di .env, jangan commit ke repository
2. **File Upload**: Validasi file type dan size
3. **Permission**: Gunakan permission system untuk akses menu
4. **Input Validation**: Validasi semua input dari user

## Database Connection

Semua operasi database menggunakan connection `mysql_second`:
- Models menggunakan `protected $connection = 'mysql_second'`
- Query menggunakan `DB::connection('mysql_second')`

## Testing

### Test Send Notification
1. Buka menu Push Notification
2. Buat notifikasi baru dengan target email yang valid
3. Klik tombol kirim
4. Cek di mobile app apakah notifikasi diterima

### Test Broadcast
1. Buat notifikasi dengan target "all"
2. Klik tombol kirim
3. Cek di beberapa device apakah notifikasi diterima

## Support

Jika ada masalah atau pertanyaan, hubungi developer atau lihat dokumentasi FCM:
- [Firebase Cloud Messaging Documentation](https://firebase.google.com/docs/cloud-messaging)

