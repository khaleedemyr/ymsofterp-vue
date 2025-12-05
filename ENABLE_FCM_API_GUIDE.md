# Cara Enable Cloud Messaging API di Firebase

## Method 1: Via Firebase Console (Paling Mudah)

### Langkah-langkah:

1. **Buka Firebase Console**
   - https://console.firebase.google.com/
   - Login dengan Google account
   - Pilih project Firebase Anda

2. **Buka Project Settings**
   - Klik ikon Settings (gear icon) di kiri atas
   - Pilih "Project settings"

3. **Tab Cloud Messaging**
   - Klik tab "Cloud Messaging" di bagian atas
   - Scroll ke bawah ke bagian "Cloud Messaging API (Legacy)"

4. **Enable API (Jika Belum Enabled)**
   - Jika sudah ada "Server key", berarti API sudah enabled
   - Jika belum ada atau tombol "Enable" muncul, klik "Enable"
   - Atau klik link "Manage API in Google Cloud Console" untuk enable manual

5. **Ambil Server Key**
   - Setelah enabled, akan muncul "Server key"
   - Klik icon copy untuk copy server key
   - Format: `AAAA...` (panjang)

## Method 2: Via Google Cloud Console (Jika Method 1 Tidak Berhasil)

### Langkah-langkah:

1. **Buka Google Cloud Console**
   - https://console.cloud.google.com/
   - Login dengan Google account yang sama dengan Firebase
   - Pilih project Firebase yang sama

2. **Enable Cloud Messaging API**
   - Di search bar, ketik: "Cloud Messaging API"
   - Atau langsung ke: https://console.cloud.google.com/apis/library/fcm.googleapis.com
   - Pastikan project Firebase yang benar sudah dipilih (cek di dropdown atas)
   - Klik "Enable" atau "ENABLE"

3. **Verifikasi di Firebase Console**
   - Kembali ke Firebase Console
   - Settings > Project Settings > Cloud Messaging
   - Pastikan "Server key" sudah muncul

## Method 3: Enable via Firebase Console dengan Link Langsung

1. **Buka Firebase Console**
   - https://console.firebase.google.com/
   - Pilih project

2. **Settings > Project Settings > Cloud Messaging**
   - Scroll ke "Cloud Messaging API (Legacy)"
   - Jika ada tombol "Enable" atau link "Manage API in Google Cloud Console", klik

3. **Di Google Cloud Console**
   - Akan redirect ke Google Cloud Console
   - Klik "Enable" untuk enable Cloud Messaging API
   - Tunggu beberapa detik

4. **Kembali ke Firebase Console**
   - Refresh halaman
   - "Server key" seharusnya sudah muncul

## Troubleshooting

### Problem: Tidak Ada Tab "Cloud Messaging"
**Solusi:**
- Pastikan sudah add Android/iOS app di Firebase project
- Jika belum, add app dulu:
  - Firebase Console > Project Overview > Add app
  - Pilih Android atau iOS
  - Isi package name/bundle ID
  - Register app

### Problem: Tombol "Enable" Tidak Muncul
**Solusi:**
- Coba enable via Google Cloud Console langsung
- Pastikan punya permission "Owner" atau "Editor" di project

### Problem: API Sudah Enabled Tapi Server Key Tidak Muncul
**Solusi:**
- Refresh halaman Firebase Console
- Clear cache browser
- Coba buka di incognito mode
- Pastikan menggunakan project Firebase yang benar

### Problem: Error "API Not Enabled"
**Solusi:**
1. Buka Google Cloud Console
2. APIs & Services > Library
3. Search "Cloud Messaging API" atau "Firebase Cloud Messaging API"
4. Klik "Enable"
5. Tunggu beberapa menit
6. Refresh Firebase Console

## Verifikasi API Sudah Enabled

### Cek di Firebase Console:
- Settings > Project Settings > Cloud Messaging
- Harus ada "Server key" (bukan kosong)
- Format: `AAAA...` (panjang)

### Cek di Google Cloud Console:
- APIs & Services > Enabled APIs
- Harus ada "Firebase Cloud Messaging API" atau "Cloud Messaging API" dalam list

## Catatan Penting

1. **Legacy API vs V1 API**
   - Legacy API (`/fcm/send`) menggunakan Server Key
   - V1 API menggunakan Service Account (lebih kompleks)
   - Untuk sekarang, kita pakai Legacy API dengan Server Key

2. **Server Key Format**
   - Format: `AAAA...` (sangat panjang, dimulai dengan "AAAA")
   - Bukan format `AIzaSy...` (itu Google API Key, berbeda)

3. **Project Firebase**
   - Pastikan enable API di project Firebase yang sama dengan yang digunakan di mobile app
   - Jika project berbeda, server key tidak akan bekerja

## Quick Checklist

- [ ] Firebase project sudah dibuat
- [ ] Android/iOS app sudah di-register di Firebase
- [ ] Cloud Messaging API sudah enabled
- [ ] Server key sudah muncul di Firebase Console
- [ ] Server key sudah di-copy (format: AAAA...)
- [ ] Server key sudah di-set di `.env` backend

