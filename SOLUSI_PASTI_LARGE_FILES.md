# Solusi Pasti untuk File Besar di Git

**Masalah:** File besar masih terdeteksi meskipun sudah di `.gitignore` karena file sudah ter-commit di Git history.

## ⚠️ SOLUSI PASTI: Buat Repository Baru (Paling Mudah) ⭐⭐⭐

Jika Git history tidak penting, buat repository baru:

```bash
cd /home/ymsuperadmin/public_html

# 1. Hapus .git folder (hapus semua history)
rm -rf .git

# 2. Init Git baru
git init

# 3. Pastikan .gitignore sudah benar
echo "*.zip" >> .gitignore
echo ".git.zip" >> .gitignore
echo "tms.ymsofterp.com/" >> .gitignore

# 4. Add semua file (file besar akan di-ignore otomatis)
git add .

# 5. Commit
git commit -m "Initial commit - server restore version"

# 6. Add remote
git remote add origin https://github.com/khaleedemyr/ymsofterp-vue.git
# Atau jika sudah ada:
# git remote set-url origin https://github.com/khaleedemyr/ymsofterp-vue.git

# 7. Force push
git push origin master --force
```

**⚠️ PERINGATAN:** Ini akan **hapus semua Git history** dan mulai dari awal.

---

## Alternatif: Hapus dari History (Jika History Penting)

```bash
cd /home/ymsuperadmin/public_html

# 1. Hapus dari tracking
git rm --cached .git.zip
git rm --cached "tms.ymsofterp.com/app.zip"

# 2. Hapus dari semua commit
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .git.zip 'tms.ymsofterp.com/app.zip'" \
  --prune-empty --tag-name-filter cat -- --all

# 3. Clean up
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# 4. Push
git push origin master --force
```

---

## ⭐⭐⭐ REKOMENDASI: TIDAK Perlu Push ke Git!

**Untuk sync dari server ke local, TIDAK perlu push ke Git sama sekali!**

### Download Langsung dari Server:

#### Via cPanel File Manager (Paling Mudah):
1. Login cPanel
2. File Manager → Navigate ke `public_html`
3. Select folder: `app`, `routes`, `bootstrap`, `config`, `database`
4. Klik "Compress" → ZIP
5. Download ZIP
6. Extract di local, replace folder

#### Via SSH/SCP:
```bash
# Di local (PowerShell)
cd D:\Gawean\YM\web\ymsofterp

scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/app ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/routes ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/bootstrap ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/config ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/database ./
```

#### Via WinSCP (GUI):
1. Download WinSCP: https://winscp.net/
2. Connect ke server (SFTP)
3. Drag & drop folder dari server ke local

**Keuntungan:**
- ✅ Tidak perlu hapus file besar
- ✅ Tidak perlu force push
- ✅ Tidak perlu setup Git auth
- ✅ Lebih cepat dan mudah
- ✅ Tidak ada limit file size

---

## Script Otomatis

Saya sudah buat script `QUICK_FIX_LARGE_FILES.sh` yang akan:
1. Tanya apakah mau buat repository baru (hapus history)
2. Atau hapus file besar dari history (jaga history)
3. Setup `.gitignore` otomatis
4. Siap untuk push

**Cara pakai:**
```bash
# Upload script ke server
# Jalankan:
bash QUICK_FIX_LARGE_FILES.sh
```

---

## Checklist

- [ ] Pilih metode: Buat repo baru ATAU hapus dari history
- [ ] Pastikan `.gitignore` sudah benar
- [ ] Hapus file besar dari Git
- [ ] Commit perubahan
- [ ] Force push ke GitHub

---

## Kesimpulan

**Untuk sync server ke local:**
1. ✅ **Download langsung dari server** (cPanel/SSH) - **TIDAK perlu Git**
2. ✅ **Replace folder di local**
3. ✅ **Commit ke Git dari local** (jika perlu backup)

**Jika tetap ingin push ke Git:**
1. ✅ **Buat repository baru** (hapus history) - **PALING MUDAH**
2. ✅ Atau hapus file besar dari history (jaga history) - lebih kompleks

**Rekomendasi:** Download langsung dari server via cPanel atau SSH. Lebih mudah, cepat, dan tidak ada masalah file besar!

