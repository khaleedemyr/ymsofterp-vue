# Fix: Authorization Header Tidak Terkirim ke Laravel

## 🔍 Masalah:
Header `Authorization` tidak sampai ke Laravel. Di debug response, `authorization_header` = `null`.

Ini berarti masalahnya di **web server configuration** (Nginx/Apache) yang tidak meneruskan header ke PHP-FPM.

## ✅ Solusi untuk Nginx:

### 1. Cek File Config Nginx

Biasanya ada di:
- `/etc/nginx/sites-available/ymsofterp.com`
- `/etc/nginx/nginx.conf`
- `/etc/nginx/conf.d/default.conf`

### 2. Pastikan Config Seperti Ini:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ymsofterp.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ymsofterp.com;
    
    root /var/www/ymsofterp/public;
    index index.php index.html;
    
    # SSL configuration...
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;  # Sesuaikan versi PHP
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # PENTING: Teruskan Authorization header
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass_request_headers on;
    }
    
    # Untuk API routes, pastikan header diteruskan
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
        
        # Teruskan semua headers termasuk Authorization
        proxy_set_header Authorization $http_authorization;
        proxy_pass_request_headers on;
    }
}
```

### 3. Atau Gunakan Config Ini (Lebih Sederhana):

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    
    # PENTING: Teruskan Authorization header
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
}
```

### 4. Reload Nginx

Setelah edit config:
```bash
# Test config dulu
sudo nginx -t

# Jika OK, reload
sudo systemctl reload nginx
```

---

## ✅ Solusi untuk Apache:

### 1. Cek File Config Apache

Biasanya ada di:
- `/etc/apache2/sites-available/ymsofterp.com.conf`
- `/etc/apache2/apache2.conf`
- `.htaccess` di root project

### 2. Pastikan Config Seperti Ini:

**Di VirtualHost atau .htaccess:**
```apache
<VirtualHost *:443>
    ServerName ymsofterp.com
    DocumentRoot /var/www/ymsofterp/public
    
    # PENTING: Teruskan Authorization header
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
    
    <Directory /var/www/ymsofterp/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Atau di .htaccess:**
```apache
RewriteEngine On

# Teruskan Authorization header
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

### 3. Enable mod_rewrite dan mod_headers

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

---

## 🔧 Test Setelah Fix:

### 1. Test dengan curl di Server

SSH ke server dan test:
```bash
# Login dulu
curl -X POST "https://ymsofterp.com/api/mobile/member/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"hendiroom@gmail.com","password":"Justus123!!"}'

# Copy token, lalu test
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/test-token" \
  -H "Authorization: Bearer TOKEN_DARI_LOGIN" \
  -H "Accept: application/json"
```

Lihat apakah `authorization_header` sekarang muncul di response.

### 2. Test dengan Script

Setelah fix, test lagi:
```powershell
.\test-auth.ps1
```

---

## 🚨 Checklist:

- [ ] Edit Nginx/Apache config untuk teruskan Authorization header
- [ ] Test config (nginx -t atau apache2ctl configtest)
- [ ] Reload/Restart web server
- [ ] Test dengan curl di server
- [ ] Test dengan script PowerShell

---

## 📝 Catatan:

Jika menggunakan **Cloudflare** atau **reverse proxy** lain, pastikan mereka juga tidak menghapus Authorization header. Cek di dashboard Cloudflare:
- **Rules** → **Transform Rules** → Pastikan tidak ada yang menghapus header

---

## 💡 Quick Fix untuk Nginx:

Jika tidak yakin file config mana yang perlu di-edit, coba tambahkan ini di semua `location ~ \.php$`:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

Lalu reload:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

