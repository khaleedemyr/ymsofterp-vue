# 🔧 Fix Line Endings Error

## Masalah
Saat menjalankan script, muncul error:
```
fix-scheduler-not-detected.sh: line X: $'\r': command not found
cd: $'/home/ymsuperadmin/public_html\r\r': No such file or directory
syntax error: unexpected end of file
```

**Penyebab:** File script dibuat di Windows yang menggunakan CRLF (`\r\n`) sedangkan Linux menggunakan LF (`\n`).

---

## ✅ SOLUSI

### **Solusi 1: Convert Line Endings di Server (RECOMMENDED)**

Setelah upload script ke server, jalankan:

```bash
cd /home/ymsuperadmin/public_html

# Convert line endings
dos2unix fix-scheduler-not-detected.sh

# Atau jika dos2unix tidak ada:
sed -i 's/\r$//' fix-scheduler-not-detected.sh

# Set permission
chmod +x fix-scheduler-not-detected.sh

# Jalankan
bash fix-scheduler-not-detected.sh
```

---

### **Solusi 2: Install dos2unix (Jika Belum Ada)**

```bash
# CentOS/RHEL
yum install dos2unix

# Ubuntu/Debian
apt-get install dos2unix
```

Kemudian convert:
```bash
dos2unix fix-scheduler-not-detected.sh
```

---

### **Solusi 3: Buat File Langsung di Server**

Jangan upload dari Windows, buat langsung di server:

```bash
cd /home/ymsuperadmin/public_html
nano fix-scheduler-not-detected.sh
# Copy-paste isi script, lalu save
chmod +x fix-scheduler-not-detected.sh
bash fix-scheduler-not-detected.sh
```

---

### **Solusi 4: Gunakan Script yang Sudah Diperbaiki**

Saya sudah buatkan versi yang sudah diperbaiki: `fix-scheduler-not-detected-fixed.sh`

Upload file tersebut dan jalankan:
```bash
chmod +x fix-scheduler-not-detected-fixed.sh
bash fix-scheduler-not-detected-fixed.sh
```

---

### **Solusi 5: Fix Semua Script Sekaligus**

Jika ada banyak script yang perlu di-fix:

```bash
cd /home/ymsuperadmin/public_html

# Fix semua .sh files
for file in *.sh; do
    sed -i 's/\r$//' "$file"
    chmod +x "$file"
done
```

---

## 🔍 Cara Check Line Endings

### **Check apakah file punya CRLF:**

```bash
# Method 1: Check dengan file command
file fix-scheduler-not-detected.sh
# Jika output: "CRLF line terminators" berarti perlu di-fix

# Method 2: Check dengan cat -A
cat -A fix-scheduler-not-detected.sh | head -5
# Jika ada ^M$ berarti ada CRLF

# Method 3: Check dengan od
od -c fix-scheduler-not-detected.sh | head -5
# Jika ada \r berarti ada CRLF
```

---

## ✅ Quick Fix Command

Jalankan ini setelah upload script:

```bash
cd /home/ymsuperadmin/public_html
sed -i 's/\r$//' fix-scheduler-not-detected.sh
chmod +x fix-scheduler-not-detected.sh
bash fix-scheduler-not-detected.sh
```

---

## 📝 Prevent Future Issues

### **Untuk Windows Users:**

1. **Gunakan Git dengan autocrlf:**
   ```bash
   git config --global core.autocrlf input
   ```

2. **Gunakan editor yang support line endings:**
   - VS Code: Set line ending ke LF
   - Notepad++: Edit → EOL Conversion → Unix (LF)

3. **Atau buat file langsung di server** via SSH/nano

---

## 🎯 Langkah Lengkap Setelah Upload

```bash
# 1. Masuk ke folder
cd /home/ymsuperadmin/public_html

# 2. Fix line endings
sed -i 's/\r$//' fix-scheduler-not-detected.sh

# 3. Set permission
chmod +x fix-scheduler-not-detected.sh

# 4. Verify (optional)
file fix-scheduler-not-detected.sh
# Harusnya: "ASCII text" atau "Bourne-Again shell script"

# 5. Jalankan
bash fix-scheduler-not-detected.sh
```

---

## ⚠️ CATATAN

1. **Line endings penting** untuk script bash di Linux
2. **Windows menggunakan CRLF** (`\r\n`)
3. **Linux menggunakan LF** (`\n`)
4. **Selalu convert** setelah upload dari Windows
5. **Atau buat langsung di server** untuk menghindari masalah ini

---

## 🔗 File Terkait

- `fix-scheduler-not-detected-fixed.sh` - Versi yang sudah diperbaiki
- `FIX_LINE_ENDINGS.md` - Dokumentasi ini

