# 🚀 Cara Menjalankan Script di Server

## ✅ Cara 1: Upload Script ke Server (RECOMMENDED)

### **Langkah 1: Upload File Script**

Ada beberapa cara upload script ke server:

#### **A. Via FTP/SFTP (FileZilla, WinSCP, dll)**
1. Buka FileZilla atau WinSCP
2. Connect ke server
3. Upload file script ke folder `/home/ymsuperadmin/public_html/`
   - Contoh: `fix-scheduler-not-detected.sh`
   - Upload ke: `/home/ymsuperadmin/public_html/fix-scheduler-not-detected.sh`

#### **B. Via cPanel File Manager**
1. Login ke cPanel
2. Buka **File Manager**
3. Navigate ke `/home/ymsuperadmin/public_html/`
4. Upload file script (drag & drop atau klik Upload)

#### **C. Via SSH (Copy-Paste)**
1. Buka file script di local (buka dengan text editor)
2. Copy semua isinya
3. Connect ke server via SSH
4. Buat file baru:
   ```bash
   nano /home/ymsuperadmin/public_html/fix-scheduler-not-detected.sh
   ```
5. Paste isi script
6. Save: `Ctrl+X`, lalu `Y`, lalu `Enter`

### **Langkah 2: Set Permission**

Setelah upload, set permission agar script bisa dijalankan:

```bash
cd /home/ymsuperadmin/public_html
chmod +x fix-scheduler-not-detected.sh
```

**Atau via cPanel File Manager:**
1. Klik kanan file script
2. Pilih **Change Permissions**
3. Centang **Execute** untuk Owner
4. Klik **Change Permissions**

### **Langkah 3: Jalankan Script**

```bash
cd /home/ymsuperadmin/public_html
bash fix-scheduler-not-detected.sh
```

**Atau:**
```bash
./fix-scheduler-not-detected.sh
```

---

## ✅ Cara 2: Copy-Paste Langsung ke Terminal

Jika tidak ingin upload file, bisa copy-paste isi script langsung:

### **Langkah 1: Buka File Script**

Buka file script di local (misal: `fix-scheduler-not-detected.sh`), copy semua isinya.

### **Langkah 2: Connect ke Server via SSH**

```bash
ssh root@your-server-ip
# atau
ssh ymsuperadmin@your-server-ip
```

### **Langkah 3: Paste dan Jalankan**

1. Paste isi script ke terminal
2. Atau buat file dulu, lalu jalankan:
   ```bash
   cd /home/ymsuperadmin/public_html
   nano fix-scheduler-not-detected.sh
   # Paste isi script, lalu save (Ctrl+X, Y, Enter)
   chmod +x fix-scheduler-not-detected.sh
   bash fix-scheduler-not-detected.sh
   ```

---

## ✅ Cara 3: Via cPanel Terminal (Jika Ada)

1. Login ke cPanel
2. Buka **Terminal** (jika tersedia)
3. Navigate ke folder:
   ```bash
   cd /home/ymsuperadmin/public_html
   ```
4. Upload script via File Manager dulu
5. Set permission:
   ```bash
   chmod +x fix-scheduler-not-detected.sh
   ```
6. Jalankan:
   ```bash
   bash fix-scheduler-not-detected.sh
   ```

---

## 📋 Daftar Script yang Tersedia

Berikut adalah script yang sudah dibuat dan cara menjalankannya:

### **1. fix-scheduler-not-detected.sh**
**Fungsi:** Fix scheduler yang tidak terdeteksi

```bash
cd /home/ymsuperadmin/public_html
chmod +x fix-scheduler-not-detected.sh
bash fix-scheduler-not-detected.sh
```

### **2. check-scheduler.sh**
**Fungsi:** Check status scheduler

```bash
cd /home/ymsuperadmin/public_html
chmod +x check-scheduler.sh
bash check-scheduler.sh
```

### **3. setup-schedule-run-cron.sh**
**Fungsi:** Setup cron job untuk schedule:run

```bash
cd /home/ymsuperadmin/public_html
chmod +x setup-schedule-run-cron.sh
bash setup-schedule-run-cron.sh
```

### **4. fix-schedule-run.sh**
**Fungsi:** Fix schedule:run agar berjalan terus

```bash
cd /home/ymsuperadmin/public_html
chmod +x fix-schedule-run.sh
bash fix-schedule-run.sh
```

### **5. fix-queue-worker.sh**
**Fungsi:** Fix queue worker yang berjalan terlalu banyak

```bash
cd /home/ymsuperadmin/public_html
chmod +x fix-queue-worker.sh
bash fix-queue-worker.sh
```

### **6. check-server-status.sh**
**Fungsi:** Check status server (CPU, memory, processes)

```bash
cd /home/ymsuperadmin/public_html
chmod +x check-server-status.sh
bash check-server-status.sh
```

### **7. monitor-schedule.sh**
**Fungsi:** Monitor schedule:run

```bash
cd /home/ymsuperadmin/public_html
chmod +x monitor-schedule.sh
bash monitor-schedule.sh 5  # Monitor selama 5 menit
```

---

## 🔧 Troubleshooting

### Problem 1: Permission Denied

**Error:**
```
bash: fix-scheduler-not-detected.sh: Permission denied
```

**Solusi:**
```bash
chmod +x fix-scheduler-not-detected.sh
```

### Problem 2: File Not Found

**Error:**
```
bash: fix-scheduler-not-detected.sh: No such file or directory
```

**Solusi:**
1. Check apakah file ada:
   ```bash
   ls -la fix-scheduler-not-detected.sh
   ```
2. Pastikan berada di directory yang benar:
   ```bash
   cd /home/ymsuperadmin/public_html
   pwd  # Check current directory
   ```

### Problem 3: Script Tidak Bisa Di-Jalankan

**Solusi:**
1. Check permission:
   ```bash
   ls -la fix-scheduler-not-detected.sh
   ```
   Harusnya ada `x` (execute permission)

2. Set permission:
   ```bash
   chmod 755 fix-scheduler-not-detected.sh
   ```

3. Jalankan dengan bash:
   ```bash
   bash fix-scheduler-not-detected.sh
   ```

### Problem 4: Line Endings Error

**Error:**
```
bash: $'\r': command not found
```

**Solusi:**
Ini terjadi karena file dibuat di Windows. Convert line endings:
```bash
dos2unix fix-scheduler-not-detected.sh
# atau
sed -i 's/\r$//' fix-scheduler-not-detected.sh
```

---

## 📝 Contoh Lengkap

### **Contoh: Menjalankan fix-scheduler-not-detected.sh**

```bash
# 1. Connect ke server via SSH
ssh root@your-server-ip

# 2. Navigate ke folder aplikasi
cd /home/ymsuperadmin/public_html

# 3. Check apakah file ada
ls -la fix-scheduler-not-detected.sh

# 4. Set permission (jika belum)
chmod +x fix-scheduler-not-detected.sh

# 5. Jalankan script
bash fix-scheduler-not-detected.sh

# 6. Lihat output
# Script akan menampilkan hasil check dan fix
```

---

## ⚠️ CATATAN PENTING

1. **Pastikan Anda punya akses SSH** atau cPanel Terminal
2. **Backup dulu** sebelum menjalankan script yang mengubah konfigurasi
3. **Check output script** untuk memastikan tidak ada error
4. **Jalankan sebagai root atau user yang punya permission** (biasanya root atau ymsuperadmin)
5. **Script bash** harus dijalankan di Linux/Unix server, tidak bisa di Windows (kecuali pakai WSL atau Git Bash)

---

## 🎯 Quick Reference

```bash
# Upload script → Set permission → Jalankan
cd /home/ymsuperadmin/public_html
chmod +x nama-script.sh
bash nama-script.sh
```

---

## 📞 Bantuan

Jika masih ada masalah:
1. Check apakah file script sudah di-upload dengan benar
2. Check permission file (harus executable)
3. Check apakah berada di directory yang benar
4. Check output error message untuk troubleshooting

