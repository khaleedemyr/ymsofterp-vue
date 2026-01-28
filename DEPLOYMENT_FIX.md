# Fix CORS Error - Production Deployment

## Masalah
Error CORS terjadi karena aplikasi mencoba memuat assets dari Vite dev server (`http://[::1]:5173`) di production.

## Penyebab
1. Folder `public/build` tidak ada (assets belum di-build untuk production)
2. File `.env` di server masih menggunakan `APP_URL` untuk development

## Solusi

### 1. Build Assets untuk Production

Jalankan perintah berikut di folder project:

```bash
npm install
npm run build
```

Ini akan membuat folder `public/build` dengan file:
- `manifest.json`
- `assets/app-[hash].js`
- `assets/app-[hash].css`
- dll

### 2. Update File `.env` di Server

Pastikan file `.env` di server memiliki konfigurasi berikut:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ymsoft.justusku.co.id
```

**JANGAN** set `VITE_APP_URL` di production - biarkan kosong atau hapus baris tersebut.

### 3. Upload Folder `public/build` ke Server

Setelah build, upload seluruh folder `public/build` ke server. Pastikan struktur foldernya:
```
public/
  build/
    manifest.json
    assets/
      app-[hash].js
      app-[hash].css
      ...
```

### 4. Clear Cache Laravel

Setelah upload, jalankan di server:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 5. Set Permission yang Benar

Pastikan folder `public/build` bisa diakses:

```bash
chmod -R 755 public/build
```

## Checklist Deployment

- [ ] `npm run build` sudah dijalankan
- [ ] Folder `public/build` sudah ada dan berisi file assets
- [ ] File `.env` di server sudah diupdate dengan `APP_URL` yang benar
- [ ] `APP_ENV=production` dan `APP_DEBUG=false` di `.env`
- [ ] Folder `public/build` sudah diupload ke server
- [ ] Cache Laravel sudah di-clear
- [ ] Permission folder sudah benar

## Catatan Penting

- **JANGAN** menjalankan `npm run dev` di production
- **JANGAN** set `VITE_APP_URL` di production (hanya untuk development)
- Pastikan folder `public/build` selalu diupload setiap kali ada perubahan di frontend
- Jika masih error, cek browser console untuk melihat URL mana yang dicoba diakses
