# Setup Firebase Cloud Messaging HTTP v1 API

## ⚠️ Legacy API Sudah Deprecated!

Cloud Messaging API (Legacy) sudah **tidak bisa digunakan lagi** (disabled dan deprecated). Kita **WAJIB** migrasi ke **HTTP v1 API** yang menggunakan **Service Account JSON**.

## Step 1: Download Service Account JSON

1. **Buka Firebase Console**
   - https://console.firebase.google.com/
   - Login dan pilih project Firebase Anda

2. **Buka Project Settings**
   - Klik Settings (gear icon) di kiri atas
   - Pilih "Project settings"
   - Klik tab **"Service accounts"**

3. **Generate New Private Key**
   - Di bagian "Firebase Admin SDK", klik **"Generate new private key"**
   - Akan muncul dialog warning, klik **"Generate key"**
   - File JSON akan ter-download (contoh: `your-project-firebase-adminsdk-xxxxx.json`)

4. **Simpan File JSON**
   - Simpan file JSON di folder `storage/app/` 
   - Contoh nama: `storage/app/firebase-service-account.json`
   - **⚠️ PENTING: JANGAN commit file ini ke Git!**
   - Tambahkan ke `.gitignore`:
     ```
     /storage/app/firebase-service-account.json
     /storage/app/*-firebase-adminsdk-*.json
     ```

5. **Ambil Project ID**
   - Buka file JSON yang di-download
   - Cari field `project_id` (contoh: `"project_id": "my-project-12345"`)
   - Copy project_id tersebut

## Step 2: Update .env

Tambahkan konfigurasi berikut di `.env`:

```env
# Firebase HTTP v1 API (REQUIRED - Legacy API sudah deprecated)
FCM_SERVICE_ACCOUNT_PATH=firebase-service-account.json
FCM_PROJECT_ID=your-project-id-here
FCM_USE_V1_API=true
```

**Contoh:**
```env
FCM_SERVICE_ACCOUNT_PATH=firebase-service-account.json
FCM_PROJECT_ID=ym-member-app-12345
FCM_USE_V1_API=true
```

**Catatan:**
- `FCM_SERVICE_ACCOUNT_PATH` adalah path relatif dari `storage/app/`
- Jika file ada di `storage/app/firebase-service-account.json`, maka path-nya: `firebase-service-account.json`
- `FCM_PROJECT_ID` bisa dilihat di file JSON (field `project_id`) atau di Firebase Console

## Step 3: Clear Config & Test

Setelah update `.env`:

```bash
php artisan config:clear
php artisan cache:clear
```

Test dengan:
```bash
php artisan fcm:test --member_id=1
```

## Cara Kerja HTTP v1 API:

1. **Generate OAuth 2.0 Access Token**
   - Baca Service Account JSON
   - Generate JWT dengan private key
   - Exchange JWT untuk access token via Google OAuth2 API

2. **Send Notification**
   - Gunakan access token untuk authenticate
   - Endpoint: `https://fcm.googleapis.com/v1/projects/{project_id}/messages:send`
   - Format payload berbeda dengan Legacy API

3. **Auto Fallback**
   - Jika HTTP v1 API gagal, akan fallback ke Legacy API (jika masih ada server key)
   - Tapi karena Legacy sudah deprecated, sebaiknya pastikan HTTP v1 API bekerja

## Troubleshooting

### Error: Service Account file not found
- Pastikan file JSON sudah di-copy ke `storage/app/`
- Pastikan path di `.env` benar (relatif dari `storage/app/`)

### Error: Invalid private key
- Pastikan file JSON lengkap dan tidak corrupt
- Pastikan private key di JSON masih valid

### Error: Project ID not found
- Pastikan `FCM_PROJECT_ID` di `.env` sesuai dengan `project_id` di JSON
- Bisa cek di Firebase Console > Project Settings > General

### Error: Access token failed
- Pastikan Service Account masih aktif
- Cek permission Service Account di Google Cloud Console

