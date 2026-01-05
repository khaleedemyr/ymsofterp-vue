# 🔧 Setup Supervisor untuk Queue Worker - Langkah Manual
## Server: AlmaLinux 9

## 🚨 **Masalah yang Ditemukan**

Error: `ymsofterp-queue-worker: ERROR (no such group)`

**Penyebab:** Config file supervisor belum dibuat atau belum terdeteksi.

---

## ✅ **LANGKAH-LANGKAH SETUP**

### **LANGKAH 0: Install Supervisor (Jika Belum)**

1. **Install EPEL repository (jika belum):**
   ```bash
   dnf install epel-release -y
   ```

2. **Install Supervisor:**
   ```bash
   dnf install supervisor -y
   ```

3. **Enable dan start supervisor service:**
   ```bash
   systemctl enable supervisord
   systemctl start supervisord
   ```

4. **Check status:**
   ```bash
   systemctl status supervisord
   ```
   
   Harusnya: `Active: active (running)`

---

### **LANGKAH 1: Buat Config File Supervisor**

1. **Buat file config (AlmaLinux 9):**
   ```bash
   # Pastikan folder ada
   mkdir -p /etc/supervisord.d
   
   # Buat file config
   nano /etc/supervisord.d/ymsofterp-queue.ini
   ```
   
   **Note:** Di AlmaLinux 9, config file biasanya di `/etc/supervisord.d/` dengan ekstensi `.ini`

2. **Copy isi berikut ke file:**
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

3. **Simpan file:**
   - Tekan `Ctrl + O` untuk save
   - Tekan `Enter` untuk confirm
   - Tekan `Ctrl + X` untuk exit

---

### **LANGKAH 2: Check Config File**

1. **Check apakah file sudah dibuat:**
   ```bash
   ls -la /etc/supervisord.d/ymsofterp-queue.ini
   ```

2. **Check isi file (optional):**
   ```bash
   cat /etc/supervisord.d/ymsofterp-queue.ini
   ```

---

### **LANGKAH 3: Reload Supervisor Config**

1. **Reread config:**
   ```bash
   supervisorctl reread
   ```
   
   **Expected output:**
   ```
   ymsofterp-queue-worker: available
   ```

2. **Update supervisor:**
   ```bash
   supervisorctl update
   ```
   
   **Expected output:**
   ```
   ymsofterp-queue-worker: added process group
   ```

---

### **LANGKAH 4: Start Queue Worker**

1. **Start queue worker:**
   ```bash
   supervisorctl start ymsofterp-queue-worker:*
   ```
   
   **Expected output:**
   ```
   ymsofterp-queue-worker:ymsofterp-queue-worker_00: started
   ymsofterp-queue-worker:ymsofterp-queue-worker_01: started
   ```

2. **Check status:**
   ```bash
   supervisorctl status
   ```
   
   **Expected output:**
   ```
   ymsofterp-queue-worker:ymsofterp-queue-worker_00   RUNNING   pid 12345, uptime 0:00:05
   ymsofterp-queue-worker:ymsofterp-queue-worker_01   RUNNING   pid 12346, uptime 0:00:05
   ```

---

### **LANGKAH 5: Verifikasi Queue Worker Berjalan**

1. **Check dengan ps:**
   ```bash
   ps aux | grep 'queue:work' | grep -v grep
   ```
   
   **Harusnya muncul 2 proses:**
   ```
   ymsuperadmin  12345  0.5  2.0  ... php artisan queue:work --queue=notifications ...
   ymsuperadmin  12346  0.5  2.0  ... php artisan queue:work --queue=notifications ...
   ```

2. **Check log:**
   ```bash
   tail -f /home/ymsuperadmin/public_html/storage/logs/queue-worker.log
   ```

---

### **LANGKAH 6: Hapus Queue Worker dari Cron**

**PENTING:** Setelah supervisor jalan, hapus queue worker dari cron!

1. **Edit cron:**
   ```bash
   crontab -e
   ```

2. **Cari dan hapus baris ini:**
   ```
   * * * * * cd /home/ymsuperadmin/public_html && php artisan queue:work --queue-notifications --tries=3 --timeout=380 --sleep=3 --max-jobs=1000 --max-time=3680 --stop-when-empty >> storage/logs/queue-worker.log 2>&1
   ```

3. **Save dan exit**

4. **Verifikasi sudah dihapus:**
   ```bash
   crontab -l | grep queue:work
   ```
   
   **Harusnya tidak ada output** (sudah dihapus)

---

## 🔍 **TROUBLESHOOTING**

### **Error: "no such group" setelah update?**

1. **Check apakah config file ada (AlmaLinux 9):**
   ```bash
   ls -la /etc/supervisord.d/ymsofterp-queue.ini
   ```

2. **Check syntax config file:**
   ```bash
   supervisorctl reread
   ```
   
   Jika ada error, berarti syntax salah. Check kembali file config.

3. **Check supervisor config (AlmaLinux 9):**
   ```bash
   cat /etc/supervisord.conf | grep -A 5 "\[include\]"
   ```
   
   Harusnya ada:
   ```
   [include]
   files = /etc/supervisord.d/*.ini
   ```

4. **Jika tidak ada, tambahkan ke `/etc/supervisord.conf`:**
   ```bash
   nano /etc/supervisord.conf
   ```
   
   Tambahkan di bagian bawah:
   ```ini
   [include]
   files = /etc/supervisord.d/*.ini
   ```
   
   Lalu restart supervisor:
   ```bash
   systemctl restart supervisord
   ```

---

### **Error: "permission denied" saat start?**

1. **Check user di config:**
   ```ini
   user=ymsuperadmin
   ```
   
   Pastikan user `ymsuperadmin` ada dan bisa execute PHP.

2. **Check permission file artisan:**
   ```bash
   ls -la /home/ymsuperadmin/public_html/artisan
   ```
   
   Harusnya executable:
   ```
   -rwxr-xr-x 1 ymsuperadmin ymsuperadmin ... artisan
   ```

3. **Check permission folder:**
   ```bash
   ls -la /home/ymsuperadmin/public_html/storage/logs/
   ```
   
   Pastikan bisa write log.

---

### **Queue Worker tidak jalan?**

1. **Check status:**
   ```bash
   supervisorctl status ymsofterp-queue-worker:*
   ```

2. **Check log:**
   ```bash
   tail -50 /home/ymsuperadmin/public_html/storage/logs/queue-worker.log
   ```

3. **Test manual:**
   ```bash
   cd /home/ymsuperadmin/public_html
   php artisan queue:work --queue=notifications --once
   ```
   
   Jika error, berarti ada masalah di command. Check path PHP dan artisan.

---

### **Supervisor tidak jalan?**

1. **Check service:**
   ```bash
   systemctl status supervisord
   ```

2. **Start service (AlmaLinux 9):**
   ```bash
   systemctl start supervisord
   systemctl enable supervisord
   ```

3. **Check log:**
   ```bash
   journalctl -u supervisord -n 50
   ```

4. **Jika supervisor tidak terinstall:**
   ```bash
   # Install EPEL repository
   dnf install epel-release -y
   
   # Install supervisor
   dnf install supervisor -y
   
   # Enable dan start
   systemctl enable supervisord
   systemctl start supervisord
   ```

---

## 📋 **CHECKLIST**

- [ ] Supervisor terinstall (AlmaLinux 9: `dnf install supervisor`)
- [ ] Supervisor service running (`systemctl status supervisord`)
- [ ] Config file dibuat: `/etc/supervisord.d/ymsofterp-queue.ini`
- [ ] Config file syntax benar
- [ ] `supervisorctl reread` berhasil (muncul "available")
- [ ] `supervisorctl update` berhasil (muncul "added process group")
- [ ] `supervisorctl start` berhasil (status RUNNING)
- [ ] Queue worker berjalan (check dengan `ps aux`)
- [ ] Log file terbuat dan bisa di-write
- [ ] Queue worker dihapus dari cron
- [ ] Verifikasi hanya 1-2 queue worker yang berjalan (bukan 60+)

---

## 🎯 **EXPECTED RESULTS**

Setelah setup:

1. **Supervisor status:**
   ```bash
   supervisorctl status
   ```
   ```
   ymsofterp-queue-worker:ymsofterp-queue-worker_00   RUNNING   pid 12345, uptime 0:05:00
   ymsofterp-queue-worker:ymsofterp-queue-worker_01   RUNNING   pid 12346, uptime 0:05:00
   ```

2. **Queue workers count:**
   ```bash
   ps aux | grep 'queue:work' | grep -v grep | wc -l
   ```
   ```
   2
   ```
   (Bukan 60+!)

3. **CPU usage:**
   ```bash
   top
   ```
   Harusnya turun dari 100% ke 30-50%

---

## ⚠️ **CATATAN PENTING**

1. **Jangan lupa hapus queue worker dari cron** setelah supervisor jalan
2. **Monitor selama 24 jam** setelah setup
3. **Jika ada masalah, check log:** `/home/ymsuperadmin/public_html/storage/logs/queue-worker.log`
4. **Supervisor akan auto-restart** queue worker jika crash

---

## 📚 **COMMAND REFERENCE**

```bash
# Check status
supervisorctl status

# Start queue worker
supervisorctl start ymsofterp-queue-worker:*

# Stop queue worker
supervisorctl stop ymsofterp-queue-worker:*

# Restart queue worker
supervisorctl restart ymsofterp-queue-worker:*

# Reload config
supervisorctl reread
supervisorctl update

# Check log
tail -f /home/ymsuperadmin/public_html/storage/logs/queue-worker.log
```

---

**Ikuti langkah-langkah di atas secara berurutan!** ✅

