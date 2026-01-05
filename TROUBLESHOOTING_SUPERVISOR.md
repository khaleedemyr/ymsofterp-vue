# 🔧 Troubleshooting Supervisor - "No config updates to processes"

## 🚨 **Masalah**

Setelah buat config file, masih dapat error:
- `No config updates to processes`
- `ERROR (no such group)`

**Penyebab:** Supervisor tidak mendeteksi config file atau config file tidak ter-include.

---

## ✅ **SOLUSI STEP-BY-STEP**

### **STEP 1: Check Apakah File Config Ada dan Ada Isinya**

```bash
# Check file ada
ls -la /etc/supervisord.d/ymsofterp-queue.ini

# Check isi file
cat /etc/supervisord.d/ymsofterp-queue.ini
```

**Harusnya muncul isi config.** Jika kosong, berarti file tidak tersimpan dengan benar.

**Fix:** Edit lagi dan pastikan save:
```bash
nano /etc/supervisord.d/ymsofterp-queue.ini
```

Copy paste isi ini (PASTIKAN SEMUA TERMASUK):
```ini
[program:ymsofterp-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/ymsuperadmin/public_html/artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=ymsuperadmin
numprocs=2
redirect_stderr=true
stdout_logfile=/home/ymsuperadmin/public_html/storage/logs/queue-worker.log
stopwaitsecs=3600
```

**Cara save di nano:**
1. Tekan `Ctrl + O` (huruf O, bukan angka 0)
2. Tekan `Enter` untuk confirm
3. Tekan `Ctrl + X` untuk exit

---

### **STEP 2: Check Apakah `/etc/supervisord.conf` Include Config File**

```bash
cat /etc/supervisord.conf | grep -A 2 "\[include\]"
```

**Harusnya muncul:**
```ini
[include]
files = /etc/supervisord.d/*.ini
```

**Jika TIDAK ADA atau berbeda:**

1. **Edit main config:**
   ```bash
   nano /etc/supervisord.conf
   ```

2. **Cari bagian `[include]`** (biasanya di bagian bawah file)

3. **Jika tidak ada, tambahkan di bagian bawah file:**
   ```ini
   [include]
   files = /etc/supervisord.d/*.ini
   ```

4. **Save dan exit**

5. **Restart supervisor:**
   ```bash
   systemctl restart supervisord
   ```

---

### **STEP 3: Check Syntax Config File**

```bash
# Test syntax config
supervisord -c /etc/supervisord.conf -n
```

**Jika ada error syntax, akan muncul error message.** Fix sesuai error.

**Jika tidak ada error, tekan `Ctrl + C` untuk exit.**

---

### **STEP 4: Check Permission File**

```bash
# Check permission
ls -la /etc/supervisord.d/ymsofterp-queue.ini
```

**Harusnya readable:**
```
-rw-r--r-- 1 root root ... ymsofterp-queue.ini
```

**Jika permission salah:**
```bash
chmod 644 /etc/supervisord.d/ymsofterp-queue.ini
chown root:root /etc/supervisord.d/ymsofterp-queue.ini
```

---

### **STEP 5: Restart Supervisor dan Reread**

```bash
# Restart supervisor service
systemctl restart supervisord

# Wait 2 seconds
sleep 2

# Reread config
supervisorctl reread
```

**Expected output:**
```
ymsofterp-queue-worker: available
```

**Jika masih "No config updates":**
- Check kembali STEP 1-4
- Check log supervisor:
  ```bash
  journalctl -u supervisord -n 50
  ```

---

### **STEP 6: Update dan Start**

```bash
# Update
supervisorctl update

# Expected output:
# ymsofterp-queue-worker: added process group

# Start
supervisorctl start ymsofterp-queue-worker:*

# Expected output:
# ymsofterp-queue-worker:ymsofterp-queue-worker_00: started
# ymsofterp-queue-worker:ymsofterp-queue-worker_01: started
```

---

## 🔍 **CHECKLIST VERIFIKASI**

Jalankan command berikut untuk verifikasi:

```bash
# 1. File config ada dan ada isinya
echo "=== 1. Check file config ==="
ls -la /etc/supervisord.d/ymsofterp-queue.ini
cat /etc/supervisord.d/ymsofterp-queue.ini
echo ""

# 2. Main config include file
echo "=== 2. Check main config ==="
cat /etc/supervisord.conf | grep -A 2 "\[include\]"
echo ""

# 3. Supervisor service running
echo "=== 3. Check supervisor service ==="
systemctl status supervisord | head -5
echo ""

# 4. Test syntax
echo "=== 4. Test syntax (akan exit dengan Ctrl+C) ==="
supervisord -c /etc/supervisord.conf -n
```

---

## ⚠️ **MASALAH UMUM**

### **Masalah 1: File Config Kosong**

**Gejala:** `cat /etc/supervisord.d/ymsofterp-queue.ini` tidak ada output

**Solusi:**
1. Edit file lagi dengan nano
2. Copy paste isi config (lihat STEP 1)
3. Pastikan save dengan benar (`Ctrl + O`, `Enter`, `Ctrl + X`)

---

### **Masalah 2: Main Config Tidak Include**

**Gejala:** `cat /etc/supervisord.conf | grep include` tidak ada output

**Solusi:**
1. Edit `/etc/supervisord.conf`
2. Tambahkan di bagian bawah:
   ```ini
   [include]
   files = /etc/supervisord.d/*.ini
   ```
3. Restart supervisor: `systemctl restart supervisord`

---

### **Masalah 3: Syntax Error**

**Gejala:** `supervisord -c /etc/supervisord.conf -n` muncul error

**Solusi:**
- Check syntax config file
- Pastikan tidak ada typo
- Pastikan semua baris ada (tidak terpotong)

---

### **Masalah 4: Permission Denied**

**Gejala:** Supervisor tidak bisa read file

**Solusi:**
```bash
chmod 644 /etc/supervisord.d/ymsofterp-queue.ini
chown root:root /etc/supervisord.d/ymsofterp-queue.ini
```

---

## 📋 **LANGKAH CEPAT (Jika Masih Error)**

1. **Hapus file config lama:**
   ```bash
   rm /etc/supervisord.d/ymsofterp-queue.ini
   ```

2. **Buat file baru dengan isi lengkap:**
   ```bash
   cat > /etc/supervisord.d/ymsofterp-queue.ini << 'EOF'
   [program:ymsofterp-queue-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /home/ymsuperadmin/public_html/artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=ymsuperadmin
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/home/ymsuperadmin/public_html/storage/logs/queue-worker.log
   stopwaitsecs=3600
   EOF
   ```

3. **Check file:**
   ```bash
   cat /etc/supervisord.d/ymsofterp-queue.ini
   ```

4. **Check main config include:**
   ```bash
   grep -A 2 "\[include\]" /etc/supervisord.conf
   ```
   
   Jika tidak ada, tambahkan:
   ```bash
   echo "" >> /etc/supervisord.conf
   echo "[include]" >> /etc/supervisord.conf
   echo "files = /etc/supervisord.d/*.ini" >> /etc/supervisord.conf
   ```

5. **Restart dan reread:**
   ```bash
   systemctl restart supervisord
   sleep 2
   supervisorctl reread
   supervisorctl update
   supervisorctl start ymsofterp-queue-worker:*
   ```

---

## 🎯 **EXPECTED OUTPUT**

Setelah semua langkah benar:

```bash
$ supervisorctl reread
ymsofterp-queue-worker: available

$ supervisorctl update
ymsofterp-queue-worker: added process group

$ supervisorctl start ymsofterp-queue-worker:*
ymsofterp-queue-worker:ymsofterp-queue-worker_00: started
ymsofterp-queue-worker:ymsofterp-queue-worker_01: started

$ supervisorctl status
ymsofterp-queue-worker:ymsofterp-queue-worker_00   RUNNING   pid 12345, uptime 0:00:05
ymsofterp-queue-worker:ymsofterp-queue-worker_01   RUNNING   pid 12346, uptime 0:00:05
```

---

**Coba langkah-langkah di atas secara berurutan!** ✅

