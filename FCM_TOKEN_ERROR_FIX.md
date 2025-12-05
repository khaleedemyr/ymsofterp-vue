# Fix: "The registration token is not a valid FCM registration token"

## 🔴 Masalah

Error yang muncul:
```
"The registration token is not a valid FCM registration token"
Status: INVALID_ARGUMENT
```

## 🔍 Penyebab

Device token yang ada di database adalah **test/dummy token**, bukan token FCM yang valid.

Contoh token yang tidak valid:
- `test_device_124|u0JZ...`
- `test_device_125|l8tu...`
- Token yang dimulai dengan `test_`

**Token FCM yang valid** hanya bisa didapat dari **mobile app yang sudah terintegrasi dengan Firebase**.

## ✅ Solusi

### 1. Pastikan Mobile App Sudah Terintegrasi dengan Firebase

Mobile app harus:
- ✅ Sudah install Firebase SDK
- ✅ Sudah register dengan Firebase project yang sama dengan backend
- ✅ Sudah request permission untuk push notification
- ✅ Sudah mendapatkan FCM token dari Firebase SDK

### 2. Dapatkan Token FCM dari Mobile App

Token FCM yang valid biasanya:
- Panjang (150+ karakter)
- Format: alphanumeric dengan beberapa karakter khusus
- **TIDAK** dimulai dengan `test_` atau `test_device_`

Contoh token FCM yang valid:
```
dK8xYz9AbC3DeF4GhI5JkL6MnO7PqR8StU9VwX0Yz1AbC2DeF3GhI4JkL5MnO6PqR7StU8VwX9Yz0AbC1DeF2GhI3JkL4MnO5PqR6StU7VwX8Yz9AbC0DeF1GhI2JkL3MnO4PqR5StU6VwX7Yz8AbC9DeF0GhI1JkL2MnO3PqR4StU5VwX6Yz7AbC8DeF9GhI0JkL1MnO2PqR3StU4VwX5Yz6AbC7DeF8GhI9JkL0MnO1PqR2StU3VwX4Yz5AbC6
```

### 3. Register Token ke Backend

Setelah mobile app mendapatkan token FCM, kirim ke backend via API:

**Endpoint:** `POST /api/mobile/device-token/register`

**Request:**
```json
{
  "device_token": "dK8xYz9AbC3DeF4GhI5JkL6MnO7PqR8StU9VwX0Yz1AbC2DeF3GhI4JkL5MnO6PqR7StU8VwX9Yz0AbC1DeF2GhI3JkL4MnO5PqR6StU7VwX8Yz9AbC0DeF1GhI2JkL3MnO4PqR5StU6VwX7Yz8AbC9DeF0GhI1JkL2MnO3PqR4StU5VwX6Yz7AbC8DeF9GhI0JkL1MnO2PqR3StU4VwX5Yz6AbC7DeF8GhI9JkL0MnO1PqR2StU3VwX4Yz5AbC6",
  "device_type": "android",  // atau "ios"
  "device_id": "optional-device-id",
  "app_version": "1.0.0"
}
```

### 4. Hapus Test Token dari Database

Jika ada test token di database, bisa dihapus atau diabaikan. Kode sudah di-update untuk:
- ✅ Skip test token (tidak akan mencoba kirim ke test token)
- ✅ Log warning (bukan error) untuk test token
- ✅ Hanya kirim ke token FCM yang valid

## 🧪 Testing

### Test dengan Token Valid

```bash
php artisan fcm:test --device_token="VALID_FCM_TOKEN_HERE" --device_type=android
```

### Check Device Tokens

```bash
php artisan fcm:check-setup
```

Ini akan menampilkan:
- Device tokens yang terdaftar
- Token mana yang valid (bukan test token)
- Token mana yang perlu dihapus/diupdate

## 📝 Catatan

1. **Test token tidak akan menyebabkan error lagi** - sekarang hanya log warning
2. **Token FCM harus dari mobile app yang sama project Firebase-nya** dengan backend
3. **Token FCM bisa expired** - mobile app harus refresh token secara berkala
4. **Pastikan Firebase project ID sama** antara mobile app dan backend

## 🔧 Code Changes

Kode sudah di-update untuk:
- ✅ Validasi token format sebelum kirim
- ✅ Skip test token (return false, log warning)
- ✅ Handle invalid token error dengan lebih baik (log warning, bukan error)
- ✅ Hanya kirim ke token FCM yang valid

## 📱 Mobile App Integration Checklist

- [ ] Firebase SDK sudah di-install di mobile app
- [ ] Firebase project ID sama dengan backend (`justusgroup-46e18`)
- [ ] Permission untuk push notification sudah di-request
- [ ] FCM token sudah didapat dari Firebase SDK
- [ ] Token sudah di-register ke backend via API
- [ ] Test kirim notifikasi dari backend

