# Fix: Authorization Header Tidak Terkirim

## 🔍 Masalah:
Header `Authorization` tidak terkirim ke server. Di response debug, `authorization_header` = `null`.

## ✅ Kemungkinan Penyebab:

### 1. Web Server Configuration (Nginx/Apache)

**Nginx:**
Pastikan tidak ada konfigurasi yang menghapus header. Cek file config nginx:

```nginx
location /api {
    # Pastikan proxy_pass mengirim semua header
    proxy_pass http://php-fpm;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    # PASTIKAN Authorization header diteruskan
    proxy_set_header Authorization $http_authorization;
    proxy_pass_request_headers on;
}
```

**Apache:**
Cek `.htaccess` atau virtual host config, pastikan tidak ada yang menghapus header.

### 2. PHP-FPM Configuration

Pastikan PHP-FPM tidak menghapus header. Cek `php.ini`:
```ini
; Pastikan tidak ada yang menghapus HTTP headers
```

### 3. Laravel Middleware

Cek apakah ada middleware yang menghapus header. File yang perlu dicek:
- `app/Http/Middleware/HandleCors.php`
- `app/Http/Kernel.php` atau `bootstrap/app.php`

### 4. Cloudflare / Reverse Proxy

Jika menggunakan Cloudflare atau reverse proxy lain, pastikan mereka tidak menghapus `Authorization` header.

---

## 🔧 Solusi:

### Opsi 1: Test dengan Postman/curl

Test langsung dengan Postman atau curl untuk pastikan bukan masalah PowerShell:

```bash
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/test-token" \
  -H "Authorization: Bearer 103|vIFDJ7vRJIN5kgLUJCQGwhaFECcrCpYCKiNwQICg90fd0eef" \
  -H "Accept: application/json"
```

Jika dengan curl/Postman berhasil, berarti masalahnya di PowerShell.

### Opsi 2: Cek Nginx Config

Jika pakai Nginx, pastikan config seperti di atas.

### Opsi 3: Test dengan Mobile App

Test langsung dari mobile app untuk pastikan authentication bekerja di production.

---

## 📝 Next Steps:

1. **Test dengan Postman/curl** untuk pastikan server menerima header
2. **Cek Nginx/Apache config** di server
3. **Cek PHP-FPM config** di server
4. **Test dengan mobile app** langsung

---

## 🚨 Quick Test:

Test dengan curl di server:
```bash
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/test-token" \
  -H "Authorization: Bearer TOKEN_DARI_LOGIN" \
  -H "Accept: application/json"
```

Jika ini juga tidak ada Authorization header, berarti masalahnya di server configuration.

