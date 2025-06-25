# Troubleshooting Email di Server Production

## Masalah Umum Email di Server

### 1. **Port Blocking**
Server production sering memblokir port SMTP (25, 587, 465)
**Solusi:**
- Gunakan port 587 dengan TLS
- Minta hosting provider untuk membuka port SMTP
- Gunakan SMTP relay service

### 2. **Firewall Issues**
Firewall server mungkin memblokir koneksi SMTP
**Solusi:**
- Tambahkan IP SMTP server ke whitelist
- Gunakan SMTP service yang diizinkan hosting

### 3. **SSL/TLS Certificate Issues**
Server mungkin tidak mendukung SSL/TLS yang dibutuhkan
**Solusi:**
- Gunakan `tls` encryption
- Set `verify_peer` dan `verify_peer_name` ke `false`

### 4. **Authentication Problems**
Gmail App Password mungkin expired atau tidak valid
**Solusi:**
- Generate ulang App Password di Gmail
- Pastikan 2FA aktif di Gmail

## Konfigurasi yang Direkomendasikan

### 1. **Gmail SMTP (Primary)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ymsofterp@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=ymsofterp@gmail.com
MAIL_FROM_NAME="YMSoft ERP"
```

### 2. **SendGrid (Alternative)**
```env
MAIL_ALT_HOST=smtp.sendgrid.net
MAIL_ALT_PORT=587
MAIL_ALT_USERNAME=apikey
MAIL_ALT_PASSWORD=your-sendgrid-api-key
MAIL_ALT_ENCRYPTION=tls
```

### 3. **Mailgun (Alternative)**
```env
MAIL_ALT_HOST=smtp.mailgun.org
MAIL_ALT_PORT=587
MAIL_ALT_USERNAME=your-mailgun-username
MAIL_ALT_PASSWORD=your-mailgun-password
MAIL_ALT_ENCRYPTION=tls
```

## Langkah Troubleshooting

### 1. **Test Koneksi SMTP**
```bash
# Test koneksi ke Gmail SMTP
telnet smtp.gmail.com 587

# Test dengan openssl
openssl s_client -connect smtp.gmail.com:587 -starttls smtp
```

### 2. **Check Log Laravel**
```bash
# Cek log Laravel
tail -f storage/logs/laravel.log

# Cek log email khusus
tail -f storage/logs/mail.log
```

### 3. **Test Email dari Command Line**
```bash
# Test email via artisan
php artisan tinker
Mail::raw('Test email', function($message) { $message->to('test@example.com')->subject('Test'); });
```

### 4. **Check Database Email Logs**
```sql
-- Cek email yang gagal
SELECT * FROM email_logs WHERE status = 'failed' ORDER BY created_at DESC;

-- Cek error message
SELECT to_email, error_message, created_at FROM email_logs WHERE status = 'failed';
```

## Solusi Fallback

### 1. **Gunakan Queue System**
```env
QUEUE_CONNECTION=database
MAIL_QUEUE_ENABLED=true
```

### 2. **Gunakan Sendmail**
```env
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH=/usr/sbin/sendmail -bs -i
```

### 3. **Gunakan Log Driver (untuk debugging)**
```env
MAIL_MAILER=log
MAIL_LOG_CHANNEL=mail
```

## Checklist Server

- [ ] Port 587 terbuka
- [ ] SSL/TLS support aktif
- [ ] Firewall tidak memblokir SMTP
- [ ] PHP extension `openssl` aktif
- [ ] PHP extension `mbstring` aktif
- [ ] Cron job untuk queue worker aktif
- [ ] Database connection stabil

## Command untuk Server

```bash
# Install queue worker
php artisan queue:work --queue=emails --tries=3

# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
```

## Monitoring Email

### 1. **Setup Monitoring**
```sql
-- Query untuk monitoring email
SELECT 
    DATE(created_at) as date,
    status,
    COUNT(*) as total
FROM email_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), status
ORDER BY date DESC;
```

### 2. **Alert System**
- Setup alert untuk email yang gagal > 5 dalam 1 jam
- Monitor queue worker status
- Setup backup email provider 