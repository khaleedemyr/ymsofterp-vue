# 🔧 Cara Fix Line Endings Error

## ⚡ SOLUSI CEPAT - Copy Paste Langsung

Jalankan command berikut di terminal server:

```bash
cd /home/ymsuperadmin/public_html
sed -i 's/\r$//' fix-scheduler-not-detected.sh
chmod +x fix-scheduler-not-detected.sh
bash fix-scheduler-not-detected.sh
```

---

## 📋 Langkah Detail

### **Langkah 1: Fix Line Endings**

```bash
cd /home/ymsuperadmin/public_html
sed -i 's/\r$//' fix-scheduler-not-detected.sh
```

**Penjelasan:** Command ini menghapus semua `\r` (carriage return) di akhir setiap baris.

### **Langkah 2: Set Permission**

```bash
chmod +x fix-scheduler-not-detected.sh
```

### **Langkah 3: Jalankan Script**

```bash
bash fix-scheduler-not-detected.sh
```

---

## 🔄 Alternatif: Buat Script Baru Langsung di Server

Jika masih error, buat script baru langsung di server:

```bash
cd /home/ymsuperadmin/public_html

# Buat file baru
cat > fix-scheduler-simple.sh << 'EOF'
#!/bin/bash
cd /home/ymsuperadmin/public_html
PHP_PATH=$(which php 2>/dev/null || echo "/usr/bin/php")
echo "Clearing cache..."
$PHP_PATH artisan config:clear
$PHP_PATH artisan cache:clear
echo "Rebuilding cache..."
$PHP_PATH artisan config:cache
echo "Testing schedule:list..."
$PHP_PATH artisan schedule:list
echo "Done!"
EOF

# Set permission
chmod +x fix-scheduler-simple.sh

# Jalankan
bash fix-scheduler-simple.sh
```

---

## 🎯 Command Manual (Jika Script Masih Error)

Jika script masih tidak bisa, jalankan command manual:

```bash
cd /home/ymsuperadmin/public_html

# Clear cache
php artisan config:clear
php artisan cache:clear

# Rebuild cache
php artisan config:cache

# Test schedule:list
php artisan schedule:list

# Check scheduled tasks di code
grep -c "\$schedule->command" app/Console/Kernel.php
```

---

## ✅ Verifikasi

Setelah fix, check apakah sudah benar:

```bash
# Check line endings sudah benar
file fix-scheduler-not-detected.sh
# Harusnya output: "Bourne-Again shell script" atau "ASCII text"
# BUKAN "CRLF line terminators"

# Check tidak ada \r
cat -A fix-scheduler-not-detected.sh | head -5
# Harusnya tidak ada ^M di akhir baris
```

---

## 🚀 Quick Fix All Scripts

Jika ada banyak script yang perlu di-fix:

```bash
cd /home/ymsuperadmin/public_html

# Fix semua .sh files
for file in *.sh; do
    echo "Fixing $file..."
    sed -i 's/\r$//' "$file"
    chmod +x "$file"
done

echo "All scripts fixed!"
```

---

## ⚠️ CATATAN PENTING

1. **Selalu jalankan `sed -i 's/\r$//'` setelah upload script dari Windows**
2. **Atau buat script langsung di server** untuk menghindari masalah ini
3. **Check dengan `file` command** untuk memastikan line endings sudah benar

---

## 📞 Jika Masih Error

Jika setelah fix masih error, coba:

1. **Check apakah file ada:**
   ```bash
   ls -la fix-scheduler-not-detected.sh
   ```

2. **Check syntax:**
   ```bash
   bash -n fix-scheduler-not-detected.sh
   ```

3. **Jalankan command manual** (lihat di atas)

4. **Buat script baru langsung di server** (lihat alternatif di atas)

