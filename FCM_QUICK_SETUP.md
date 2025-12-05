# Quick Setup FCM HTTP v1 API

## ⚠️ Legacy API Sudah Tidak Bisa Digunakan!

Karena Legacy API sudah deprecated, kita **WAJIB** pakai HTTP v1 API dengan Service Account.

## Langkah Cepat:

### 1. Download Service Account JSON

1. Firebase Console → Project Settings → Service accounts
2. Klik "Generate new private key"
3. Download file JSON
4. Simpan di: `storage/app/firebase-service-account.json`

### 2. Ambil Project ID

- Buka file JSON yang di-download
- Copy value dari field `"project_id"` (contoh: `"project_id": "my-app-12345"`)

### 3. Update .env

```env
FCM_SERVICE_ACCOUNT_PATH=firebase-service-account.json
FCM_PROJECT_ID=your-project-id-dari-json
FCM_USE_V1_API=true
```

### 4. Clear Config

```bash
php artisan config:clear
php artisan cache:clear
```

### 5. Test

```bash
php artisan fcm:test --member_id=1
```

## File Structure:

```
storage/
  app/
    firebase-service-account.json  ← File JSON dari Firebase
```

## .env Example:

```env
# FCM HTTP v1 API (REQUIRED)
FCM_SERVICE_ACCOUNT_PATH=firebase-service-account.json
FCM_PROJECT_ID=ym-member-app-12345
FCM_USE_V1_API=true
```

## Checklist:

- [ ] Service Account JSON sudah di-download dari Firebase Console
- [ ] File JSON sudah di-copy ke `storage/app/firebase-service-account.json`
- [ ] Project ID sudah di-copy dari JSON
- [ ] `.env` sudah di-update dengan `FCM_SERVICE_ACCOUNT_PATH` dan `FCM_PROJECT_ID`
- [ ] Config sudah di-clear (`php artisan config:clear`)
- [ ] Test berhasil (`php artisan fcm:test --member_id=1`)

## Troubleshooting:

**Error: Service Account file not found**
- Pastikan file ada di `storage/app/firebase-service-account.json`
- Pastikan path di `.env` benar: `firebase-service-account.json` (tanpa `storage/app/`)

**Error: Invalid project ID**
- Pastikan `FCM_PROJECT_ID` sesuai dengan `project_id` di JSON file
- Bisa cek di Firebase Console > Project Settings > General

**Error: Access token failed**
- Pastikan Service Account masih aktif
- Cek permission di Google Cloud Console

