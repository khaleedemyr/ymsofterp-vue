# 🔍 Troubleshooting: Email Verifikasi & Reset Password Tidak Sampai

## 🚨 **MASALAH**

- Email terkirim di folder **Sent** Gmail
- Member **tidak menerima** email
- Tidak ada di folder **Spam**
- Email verifikasi dan reset password tidak sampai

---

## 🔍 **PENYEBAB UMUM**

### **1. Email Masuk ke Folder Lain di Gmail**

Gmail memiliki beberapa folder:
- **Primary** (Inbox utama)
- **Promotions** (Promosi)
- **Updates** (Update)
- **Social** (Sosial)
- **Spam** (Spam)

**Email mungkin masuk ke folder Promotions atau Updates, bukan Inbox!**

**Solusi:**
- Minta member check semua folder Gmail (Promotions, Updates, Social)
- Atau search email dengan keyword: "Verify Your Email" atau "Reset Password"

---

### **2. SPF/DKIM/DMARC Records Tidak Setup**

Email dari server Anda mungkin tidak ter-authenticate dengan benar, sehingga Gmail memfilter email.

**Check SPF/DKIM/DMARC:**
```bash
# Check SPF record
dig TXT yourdomain.com | grep spf

# Check DKIM record (jika ada)
dig TXT default._domainkey.yourdomain.com

# Check DMARC record
dig TXT _dmarc.yourdomain.com
```

**Solusi:**
- Setup SPF record di DNS
- Setup DKIM record (jika pakai SMTP)
- Setup DMARC record

---

### **3. Email Di-Filter oleh Gmail**

Gmail mungkin memfilter email karena:
- Sender reputation rendah
- Email content mirip spam
- Domain/IP di blacklist

**Check:**
- Gunakan tool: https://www.mail-tester.com/
- Check sender reputation: https://mxtoolbox.com/blacklists.aspx

---

### **4. Masalah Konfigurasi SMTP**

Email mungkin terkirim tapi tidak sampai karena konfigurasi SMTP salah.

**Check konfigurasi:**
- MAIL_HOST
- MAIL_PORT
- MAIL_USERNAME
- MAIL_PASSWORD
- MAIL_ENCRYPTION
- MAIL_FROM_ADDRESS
- MAIL_FROM_NAME

---

## ✅ **SOLUSI STEP-BY-STEP**

### **LANGKAH 1: Check Email Masuk ke Folder Lain**

**Minta member:**
1. Check folder **Promotions** di Gmail
2. Check folder **Updates** di Gmail
3. Check folder **Social** di Gmail
4. Search email dengan keyword: "Verify Your Email" atau "Reset Password"
5. Check **All Mail** (semua email)

**Jika ditemukan di folder lain:**
- Minta member drag email ke **Primary** (Inbox)
- Gmail akan belajar dan email berikutnya masuk ke Inbox

---

### **LANGKAH 2: Check Konfigurasi Email di .env**

```bash
# Check file .env
cat .env | grep MAIL_
```

**Harusnya ada:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Your App Name"
```

**Jika pakai Gmail SMTP:**
- Harus pakai **App Password** (bukan password biasa)
- Enable **2-Step Verification** di Gmail
- Generate **App Password** di: https://myaccount.google.com/apppasswords

---

### **LANGKAH 3: Test Email Delivery**

**Test manual:**
```bash
cd /home/ymsuperadmin/public_html
php artisan tinker
```

```php
use Illuminate\Support\Facades\Mail;
Mail::raw('Test email', function($message) {
    $message->to('test-email@gmail.com')
            ->subject('Test Email');
});
```

**Check apakah email sampai.**

---

### **LANGKAH 4: Check Email Logs**

**Check Laravel log:**
```bash
tail -f storage/logs/laravel.log | grep -i mail
```

**Check mail log (jika ada):**
```bash
tail -f /var/log/mail.log
# atau
tail -f /var/log/maillog
```

**Check apakah ada error saat kirim email.**

---

### **LANGKAH 5: Setup SPF Record**

**Tambahkan SPF record di DNS:**

```
TXT record:
Name: @ (atau yourdomain.com)
Value: v=spf1 include:_spf.google.com ~all
```

**Jika pakai Gmail SMTP:**
```
TXT record:
Name: @
Value: v=spf1 include:_spf.google.com ~all
```

**Jika pakai server sendiri:**
```
TXT record:
Name: @
Value: v=spf1 ip4:YOUR_SERVER_IP ~all
```

**Verifikasi:**
```bash
dig TXT yourdomain.com | grep spf
```

---

### **LANGKAH 6: Setup DKIM (Jika Pakai SMTP Server Sendiri)**

**Jika pakai Gmail SMTP, skip langkah ini.**

**Jika pakai server sendiri:**
1. Generate DKIM key
2. Tambahkan DKIM record di DNS
3. Konfigurasi di mail server

---

### **LANGKAH 7: Setup DMARC**

**Tambahkan DMARC record di DNS:**

```
TXT record:
Name: _dmarc
Value: v=DMARC1; p=none; rua=mailto:admin@yourdomain.com
```

**Verifikasi:**
```bash
dig TXT _dmarc.yourdomain.com
```

---

### **LANGKAH 8: Improve Email Content**

**Email mungkin di-filter karena content mirip spam.**

**Check email template:**
- File: `resources/views/emails/member-verification.blade.php`
- File: `resources/views/emails/member-password-reset.blade.php`

**Tips:**
- Gunakan plain text version juga
- Hindari kata-kata spam (FREE, CLICK HERE, dll)
- Gunakan proper HTML structure
- Include unsubscribe link (jika perlu)

---

### **LANGKAH 9: Gunakan Email Service Provider**

**Jika masalah masih ada, consider gunakan:**
- **SendGrid** (recommended)
- **Mailgun**
- **Amazon SES**
- **Postmark**

**Keuntungan:**
- Better deliverability
- Analytics & tracking
- SPF/DKIM/DMARC sudah setup
- Better reputation

---

## 🔍 **DIAGNOSIS CEPAT**

### **1. Check Email Masuk ke Folder Lain**

**Minta member:**
- Check semua folder Gmail
- Search email dengan keyword
- Check All Mail

---

### **2. Test Email Delivery**

```bash
# Test kirim email
php artisan tinker
```

```php
Mail::raw('Test', function($m) {
    $m->to('test@gmail.com')->subject('Test');
});
```

**Check apakah email sampai.**

---

### **3. Check Email Logs**

```bash
# Check Laravel log
tail -f storage/logs/laravel.log | grep -i mail

# Check mail log
tail -f /var/log/mail.log
```

---

### **4. Check SPF/DKIM/DMARC**

```bash
# Check SPF
dig TXT yourdomain.com | grep spf

# Check DMARC
dig TXT _dmarc.yourdomain.com
```

---

## 📋 **CHECKLIST**

- [ ] Check email masuk ke folder lain (Promotions, Updates)
- [ ] Check konfigurasi .env (MAIL_*)
- [ ] Test email delivery manual
- [ ] Check email logs (Laravel & mail log)
- [ ] Setup SPF record
- [ ] Setup DMARC record
- [ ] Improve email content
- [ ] Consider email service provider

---

## 🎯 **SOLUSI CEPAT (Paling Sering)**

### **1. Email Masuk ke Folder Promotions/Updates**

**Minta member:**
- Check folder **Promotions** dan **Updates**
- Drag email ke **Primary** (Inbox)
- Gmail akan belajar

---

### **2. Setup SPF Record**

**Tambahkan di DNS:**
```
TXT record: @ → v=spf1 include:_spf.google.com ~all
```

**Jika pakai Gmail SMTP, ini wajib!**

---

### **3. Gunakan App Password (Gmail)**

**Jika pakai Gmail SMTP:**
1. Enable 2-Step Verification
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Gunakan App Password di .env (bukan password biasa)

---

## ⚠️ **CATATAN PENTING**

1. **Email masuk ke folder lain adalah masalah paling umum!**
   - Check Promotions, Updates, Social
   - Search email dengan keyword

2. **SPF record wajib jika pakai Gmail SMTP!**
   - Tanpa SPF, Gmail akan memfilter email

3. **Gunakan App Password untuk Gmail!**
   - Password biasa tidak akan work
   - Harus generate App Password

4. **Consider email service provider!**
   - Better deliverability
   - Analytics & tracking
   - SPF/DKIM/DMARC sudah setup

---

## 📚 **DOKUMENTASI TERKAIT**

- `config/mail.php` - Konfigurasi email
- `app/Mail/MemberEmailVerification.php` - Email verification class
- `resources/views/emails/member-verification.blade.php` - Email template

---

**Mulai dari Langkah 1: Check email masuk ke folder lain!** ✅

**Kemungkinan besar email masuk ke folder Promotions atau Updates, bukan Inbox!**

