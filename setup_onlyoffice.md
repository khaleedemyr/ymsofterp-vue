# Setup OnlyOffice Document Server

## Masalah Saat Ini
OnlyOffice server tidak running di `http://localhost:80`, sehingga file tidak bisa ditampilkan di editor.

## Solusi

### Option 1: Install OnlyOffice Document Server

#### Docker (Recommended)
```bash
# Pull OnlyOffice Document Server
docker pull onlyoffice/documentserver

# Run OnlyOffice Document Server
docker run -i -t -d -p 80:80 \
  -v /app/onlyoffice/DocumentServer/logs:/var/log/onlyoffice \
  -v /app/onlyoffice/DocumentServer/data:/var/www/onlyoffice/Data \
  -v /app/onlyoffice/DocumentServer/lib:/var/lib/onlyoffice \
  -v /app/onlyoffice/DocumentServer/db:/var/lib/postgresql \
  onlyoffice/documentserver
```

#### Manual Installation
1. Download OnlyOffice Document Server dari https://www.onlyoffice.com/download
2. Install sesuai OS
3. Configure untuk port 80

### Option 2: Use OnlyOffice Cloud (Free Tier)
1. Daftar di https://www.onlyoffice.com/cloud.aspx
2. Update `.env` file:
```env
ONLYOFFICE_URL=https://your-subdomain.onlyoffice.com
```

### Option 3: Alternative Document Viewers

#### Google Docs Viewer (Current Implementation)
- Menggunakan Google Docs Viewer untuk preview
- Tidak perlu server tambahan
- Terbatas untuk view only

#### LibreOffice Online
```bash
# Install LibreOffice Online
sudo apt-get install libreoffice-online

# Configure untuk port 9980
```

#### PDF.js (untuk PDF files)
```bash
# Install PDF.js
npm install pdfjs-dist
```

## Testing

### 1. Test OnlyOffice Server
```bash
# Test connectivity
curl -I http://localhost:80/web-apps/apps/api/documents/api.js

# Expected response: HTTP/1.1 200 OK
```

### 2. Test Document Download
```bash
# Test download URL
curl -I http://localhost:8000/shared-documents/1/download

# Expected response: HTTP/1.1 200 OK
```

### 3. Test OnlyOffice Integration
1. Buka browser
2. Akses `http://localhost:8000/shared-documents/1`
3. Cek console untuk error
4. File harus tampil di editor

## Configuration

### Environment Variables
```env
# .env file
ONLYOFFICE_URL=http://localhost:80
APP_URL=http://localhost:8000
```

### Laravel Config
```php
// config/app.php
'onlyoffice_url' => env('ONLYOFFICE_URL', 'http://localhost:80'),
```

## Troubleshooting

### OnlyOffice Server Not Starting
1. Cek port 80 tidak digunakan aplikasi lain
2. Cek firewall settings
3. Cek Docker logs: `docker logs <container_id>`

### Document Not Loading
1. Cek file permissions
2. Cek CORS settings
3. Cek network connectivity

### Authentication Issues
1. Cek Laravel session
2. Cek document permissions
3. Cek user authentication

## Current Status
- ✅ File storage working
- ✅ Download URL working
- ✅ Route configuration correct
- ❌ OnlyOffice server not running
- ✅ Fallback viewer implemented

## Next Steps
1. Install OnlyOffice Document Server
2. Test integration
3. Configure for production 