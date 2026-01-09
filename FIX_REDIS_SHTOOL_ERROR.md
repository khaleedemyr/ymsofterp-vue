# 🔴 Fix: Redis shtool Error

## 🎯 **MASALAH**

**Error saat phpize:**
```
shtool at '/tmp/redis-5.3.7/build/shtool' does not exist or is not executable.
Make sure that the file exists and is executable and then rerun this script.
```

**Konsekuensi:**
- Makefile tidak dibuat
- `make` gagal: "No targets specified and no makefile found"
- `make install` gagal: "No rule to make target 'install'"

---

## ⚡ **SOLUSI: Clean dan Download Ulang**

### **STEP 1: Clean Directory**

```bash
# Kembali ke /tmp
cd /tmp

# Hapus directory dan file lama
rm -rf redis-5.3.7
rm -f redis-5.3.7.tgz

# Pastikan directory bersih
ls -la | grep redis
```

---

### **STEP 2: Download Ulang Redis Source**

```bash
# Download ulang Redis source
cd /tmp
wget https://pecl.php.net/get/redis-5.3.7.tgz

# Verify download
ls -lh redis-5.3.7.tgz
```

---

### **STEP 3: Extract dengan Permission yang Benar**

```bash
# Extract dengan preserve permissions
tar -xzf redis-5.3.7.tgz

# Set permissions yang benar
cd redis-5.3.7
chmod +x build/shtool 2>/dev/null || true

# Check apakah shtool ada
ls -la build/shtool
```

---

### **STEP 4: Run phpize Lagi**

```bash
# Run phpize
/opt/cpanel/ea-php82/root/usr/bin/phpize

# Check apakah build/shtool ada setelah phpize
ls -la build/shtool

# Jika tidak ada, coba install shtool manual
```

---

## 🔧 **ALTERNATIVE: Install shtool Manual**

**Jika shtool masih tidak ada setelah phpize:**

```bash
# Install shtool
dnf install -y shtool

# Atau download shtool manual
cd /tmp/redis-5.3.7/build
wget https://www.gnu.org/software/shtool/shtool-2.0.8.tar.gz
tar -xzf shtool-2.0.8.tar.gz
cd shtool-2.0.8
./configure
make
cp shtool ../shtool
chmod +x ../shtool

# Run phpize lagi
cd /tmp/redis-5.3.7
/opt/cpanel/ea-php82/root/usr/bin/phpize
```

---

## 🔧 **ALTERNATIVE: Gunakan Versi Redis yang Lebih Baru**

**Coba versi Redis yang lebih baru atau stabil:**

```bash
cd /tmp
rm -rf redis-5.3.7 redis-5.3.7.tgz

# Download versi terbaru
wget https://pecl.php.net/get/redis-latest.tgz -O redis-latest.tgz
tar -xzf redis-latest.tgz
cd redis-*

# Run phpize
/opt/cpanel/ea-php82/root/usr/bin/phpize

# Configure
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config

# Make
make

# Install
make install
```

---

## ✅ **PROSEDUR LENGKAP - FIX shtool ERROR**

```bash
# 1. Clean directory
cd /tmp
rm -rf redis-5.3.7 redis-5.3.7.tgz

# 2. Download ulang
wget https://pecl.php.net/get/redis-5.3.7.tgz

# 3. Extract
tar -xzf redis-5.3.7.tgz
cd redis-5.3.7

# 4. Set permissions
chmod +x build/shtool 2>/dev/null || true

# 5. Run phpize
/opt/cpanel/ea-php82/root/usr/bin/phpize

# 6. Check shtool
ls -la build/shtool

# 7. Jika shtool ada, lanjut configure
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config

# 8. Make
make

# 9. Install
make install

# 10. Enable extension
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# 11. Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# 12. Verify
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

## 🎯 **KESIMPULAN**

**Error shtool = Source code tidak lengkap atau permissions issue**

**Solusi:**
1. ✅ **Clean dan download ulang** Redis source
2. ✅ **Set permissions** yang benar
3. ✅ **Run phpize** lagi
4. ✅ **Jika masih error**, install shtool manual atau coba versi Redis yang berbeda

**Status:** 🔴 **Clean dan download ulang biasanya fix masalah ini!**
