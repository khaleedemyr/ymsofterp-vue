# Setup OnlyOffice untuk Fitur File Sharing

## Overview
Fitur file sharing ini menggunakan OnlyOffice Document Server untuk mengedit dokumen Excel, Word, dan PowerPoint secara real-time. OnlyOffice adalah solusi self-hosted yang gratis dan open source.

## Persyaratan Sistem
- Docker dan Docker Compose
- Minimal 4GB RAM
- 10GB storage space
- Linux/Windows/macOS

## Setup OnlyOffice Document Server

### 1. Install dengan Docker (Recommended)

Buat file `docker-compose.yml`:

```yaml
version: '3.8'

services:
  onlyoffice:
    image: onlyoffice/documentserver:latest
    container_name: onlyoffice-documentserver
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - onlyoffice_data:/var/www/onlyoffice/Data
      - onlyoffice_logs:/var/log/onlyoffice
      - onlyoffice_cache:/var/cache/onlyoffice
    environment:
      - JWT_ENABLED=false
      - JWT_SECRET=your-secret-key
    restart: unless-stopped

volumes:
  onlyoffice_data:
  onlyoffice_logs:
  onlyoffice_cache:
```

### 2. Jalankan OnlyOffice

```bash
# Start OnlyOffice
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f onlyoffice
```

### 3. Konfigurasi Environment

Tambahkan ke file `.env`:

```env
ONLYOFFICE_URL=http://localhost:80
```

### 4. Test OnlyOffice

Buka browser dan akses: `http://localhost:80`

Anda akan melihat halaman welcome OnlyOffice jika setup berhasil.

## Setup Laravel Application

### 1. Jalankan Migration

```bash
php artisan migrate
```

### 2. Buat Storage Link

```bash
php artisan storage:link
```

### 3. Set Permission Storage

```bash
chmod -R 775 storage/app/public
```

### 4. Test Fitur

1. Akses `/shared-documents` untuk melihat daftar dokumen
2. Upload file Excel/Word/PowerPoint
3. Share dengan user lain
4. Edit dokumen secara real-time

## Fitur yang Tersedia

### ✅ Upload Dokumen
- Support Excel (.xlsx, .xls)
- Support Word (.docx, .doc)
- Support PowerPoint (.pptx, .ppt)
- Maksimal 10MB per file

### ✅ Real-time Collaboration
- Multiple user bisa edit bersamaan
- Live cursor tracking
- Comments dan suggestions
- Version history

### ✅ Permission Management
- View: Hanya bisa lihat
- Edit: Bisa edit dokumen
- Admin: Full control (hapus, share, dll)

### ✅ File Management
- Public/Private documents
- Share dengan user tertentu
- Version control
- File metadata

## Troubleshooting

### OnlyOffice tidak bisa diakses
```bash
# Check container status
docker ps

# Check logs
docker-compose logs onlyoffice

# Restart container
docker-compose restart onlyoffice
```

### File tidak bisa diupload
```bash
# Check storage permission
ls -la storage/app/public

# Fix permission
chmod -R 775 storage/app/public
chown -R www-data:www-data storage/app/public
```

### Editor tidak muncul
1. Check browser console untuk error
2. Pastikan OnlyOffice URL benar di `.env`
3. Check network connectivity ke OnlyOffice server

## Security Considerations

### 1. JWT Authentication (Optional)
Untuk production, aktifkan JWT:

```yaml
environment:
  - JWT_ENABLED=true
  - JWT_SECRET=your-very-secure-secret-key
```

### 2. HTTPS
Gunakan HTTPS untuk production:

```yaml
ports:
  - "443:443"
volumes:
  - ./ssl:/etc/onlyoffice/ssl
```

### 3. Firewall
Buka port 80/443 untuk OnlyOffice server.

## Performance Optimization

### 1. Resource Limits
```yaml
services:
  onlyoffice:
    deploy:
      resources:
        limits:
          memory: 4G
        reservations:
          memory: 2G
```

### 2. Caching
OnlyOffice sudah include caching, tapi bisa dioptimize dengan Redis jika diperlukan.

### 3. Load Balancing
Untuk multiple user, bisa setup load balancer dengan multiple OnlyOffice instances.

## Backup Strategy

### 1. Database Backup
```bash
# Backup database
php artisan db:backup

# Backup storage
tar -czf storage_backup.tar.gz storage/app/public/shared-documents/
```

### 2. OnlyOffice Data
```bash
# Backup OnlyOffice data
docker run --rm -v onlyoffice_data:/data -v $(pwd):/backup alpine tar czf /backup/onlyoffice_data.tar.gz -C /data .
```

## Monitoring

### 1. Health Check
```bash
# Check OnlyOffice health
curl http://localhost/healthcheck

# Check Laravel app
curl http://your-app.com/health
```

### 2. Logs
```bash
# OnlyOffice logs
docker-compose logs -f onlyoffice

# Laravel logs
tail -f storage/logs/laravel.log
```

## Support

Jika ada masalah:
1. Check logs terlebih dahulu
2. Pastikan semua requirement terpenuhi
3. Test dengan file kecil terlebih dahulu
4. Check browser compatibility

## Alternative Solutions

Jika OnlyOffice tidak sesuai, bisa gunakan:
1. **Google Sheets API** - Cloud-based, mudah setup
2. **Microsoft Office Online** - Integrasi dengan Office 365
3. **Collabora Office** - Alternative open source
4. **Custom Web Editor** - Full control tapi butuh development time lebih

## Update OnlyOffice

```bash
# Pull latest image
docker-compose pull

# Update container
docker-compose up -d

# Check version
docker exec onlyoffice-documentserver cat /etc/onlyoffice/documentserver/version
``` 