# Panduan Setup SendGrid untuk YMSoft ERP

## Overview
Aplikasi YMSoft ERP sekarang dikonfigurasi untuk menggunakan SendGrid sebagai email service utama, dengan Gmail sebagai fallback.

## Langkah-langkah Setup

### 1. Daftar/Login ke SendGrid
- Kunjungi https://sendgrid.com
- Buat akun baru atau login ke akun yang sudah ada
- Verifikasi email Anda

### 2. Buat API Key
1. Login ke SendGrid Dashboard
2. Pergi ke **Settings** > **API Keys**
3. Klik **Create API Key**
4. Beri nama (contoh: "YMSoft ERP Production")
5. Pilih permission: **Full Access** (atau minimal **Mail Send**)
6. Klik **Create & View**
7. **Copy API Key** (hanya muncul sekali, simpan dengan aman!)

### 3. Verifikasi Sender Identity (Domain atau Single Sender)
**Opsi A: Single Sender Verification (Lebih Mudah)**
1. Pergi ke **Settings** > **Sender Authentication** > **Single Sender Verification**
2. Klik **Create a Sender**
3. Isi informasi:
   - **From Email**: noreply@yourdomain.com (atau email yang ingin digunakan)
   - **From Name**: YMSoft ERP
   - **Reply To**: (opsional)
   - **Company Address**: (wajib)
   - **City**: (wajib)
   - **State**: (wajib)
   - **Country**: (wajib)
   - **Zip Code**: (wajib)
4. Klik **Create**
5. **Verifikasi email** yang dikirim ke alamat yang Anda masukkan

**Opsi B: Domain Authentication (Lebih Profesional)**
1. Pergi ke **Settings** > **Sender Authentication** > **Domain Authentication**
2. Klik **Authenticate Your Domain**
3. Pilih DNS provider atau pilih "I'm not sure"
4. Ikuti instruksi untuk menambahkan DNS records
5. Tunggu verifikasi selesai (bisa beberapa jam)

### 4. Update File .env

Edit file `.env` di root project dan update konfigurasi berikut:

```env
# Mail Configuration - SendGrid
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="YMSoft ERP"

# Optional: Gmail Fallback (jika ingin menggunakan failover)
MAIL_ALT_HOST=smtp.gmail.com
MAIL_ALT_PORT=587
MAIL_ALT_USERNAME=your_gmail@gmail.com
MAIL_ALT_PASSWORD=your_gmail_app_password
MAIL_ALT_ENCRYPTION=tls
```

**Catatan Penting:**
- `MAIL_USERNAME` harus selalu `apikey` (literal string)
- `MAIL_PASSWORD` adalah API Key yang Anda copy dari SendGrid (dimulai dengan `SG.`)
- `MAIL_FROM_ADDRESS` harus menggunakan email yang sudah diverifikasi di SendGrid
- Jika menggunakan Single Sender, gunakan email yang sama dengan yang Anda verifikasi
- Jika menggunakan Domain Authentication, gunakan email dengan domain yang sudah diverifikasi

### 5. Test Konfigurasi

Setelah update `.env`, test dengan cara berikut:

**Via Tinker:**
```bash
php artisan tinker
```

Kemudian jalankan:
```php
Mail::raw('Test email dari SendGrid', function ($message) {
    $message->to('your-test-email@example.com')
            ->subject('Test SendGrid Configuration');
});
```

**Via Route Test (temporary):**
Tambahkan route test di `routes/web.php`:
```php
Route::get('/test-email', function() {
    try {
        Mail::raw('Test email dari SendGrid', function ($message) {
            $message->to('your-test-email@example.com')
                    ->subject('Test SendGrid Configuration');
        });
        return 'Email sent successfully!';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
```

Akses `/test-email` di browser, lalu hapus route tersebut setelah testing.

### 6. Clear Config Cache

Setelah update `.env`, clear config cache:
```bash
php artisan config:clear
php artisan cache:clear
```

## Konfigurasi yang Sudah Diupdate

### File `config/mail.php`
- Menambahkan mailer `sendgrid` dengan konfigurasi SMTP SendGrid

### File `config/mail_server_production.php`
- Mengubah primary mailer dari Gmail ke SendGrid
- Gmail menjadi fallback option
- Failover configuration: SendGrid → Gmail → Sendmail → Log

## Troubleshooting

### Error: "Authentication failed"
- Pastikan `MAIL_USERNAME` adalah `apikey` (literal string, bukan API key Anda)
- Pastikan `MAIL_PASSWORD` adalah API key yang benar (dimulai dengan `SG.`)
- Pastikan API key memiliki permission "Mail Send"

### Error: "Sender email not verified"
- Pastikan `MAIL_FROM_ADDRESS` menggunakan email yang sudah diverifikasi di SendGrid
- Jika menggunakan Single Sender, pastikan email sudah diklik verifikasi link
- Jika menggunakan Domain Authentication, pastikan domain sudah terverifikasi

### Email tidak terkirim
- Check SendGrid dashboard > Activity untuk melihat status email
- Check log Laravel: `storage/logs/laravel.log`
- Pastikan tidak ada rate limit (SendGrid free tier: 100 email/hari)
- Pastikan tidak ada spam filter yang memblokir

### Email masuk ke spam
- Gunakan Domain Authentication (lebih baik dari Single Sender)
- Setup SPF, DKIM, dan DMARC records dengan benar
- Pastikan konten email tidak mengandung kata-kata spam
- Warm up domain/IP jika baru setup

## SendGrid Free Tier Limits
- **100 emails per day** (permanen)
- **40,000 emails per month** untuk 3 bulan pertama
- Setelah itu kembali ke 100 emails/day

Untuk production dengan volume tinggi, pertimbangkan untuk upgrade ke paid plan.

## Referensi
- SendGrid Documentation: https://docs.sendgrid.com
- Laravel Mail Documentation: https://laravel.com/docs/mail
- SendGrid SMTP Settings: https://docs.sendgrid.com/for-developers/sending-email/getting-started-smtp
