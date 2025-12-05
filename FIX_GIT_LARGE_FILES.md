# Fix Git Large Files Error

Error yang muncul:
```
remote: error: File .git.zip is 107.67 MB; this exceeds GitHub's file size limit of 100.00 MB
remote: error: File tms.ymsofterp.com/app.zip is 101.06 MB; this exceeds GitHub's file size limit of 100.00 MB
remote: error: GH001: Large files detected.
```

**Masalah:**
- File `.git.zip` (107.67 MB) dan `tms.ymsofterp.com/app.zip` (101.06 MB) melebihi limit GitHub 100 MB
- File ini seharusnya **tidak di-commit** ke Git (backup/zip files)

## Solusi: Hapus File Besar dari Git

### Langkah 1: Hapus File dari Git (Tapi Tetap di Server)

```bash
cd /home/ymsuperadmin/public_html

# Hapus file dari Git tracking (tapi tetap ada di server)
git rm --cached .git.zip
git rm --cached tms.ymsofterp.com/app.zip

# Atau jika file ada di subfolder
git rm --cached "tms.ymsofterp.com/app.zip"
```

### Langkah 2: Tambahkan ke .gitignore

```bash
# Edit .gitignore
nano .gitignore
# Atau via editor lain

# Tambahkan baris berikut:
.git.zip
*.zip
tms.ymsofterp.com/
*.zip

# Save dan exit
```

**Atau via command:**
```bash
# Tambahkan ke .gitignore
echo ".git.zip" >> .gitignore
echo "*.zip" >> .gitignore
echo "tms.ymsofterp.com/" >> .gitignore
```

### Langkah 3: Commit Perubahan

```bash
# Commit perubahan
git add .gitignore
git commit -m "Remove large zip files from Git and add to .gitignore"
```

### Langkah 4: Hapus dari Git History (Jika Sudah Ter-commit)

Jika file sudah ter-commit sebelumnya, perlu hapus dari history:

```bash
# Install git-filter-repo (jika belum ada)
# Atau gunakan git filter-branch

# Hapus file dari semua commit
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .git.zip tms.ymsofterp.com/app.zip" \
  --prune-empty --tag-name-filter cat -- --all

# Atau lebih mudah, gunakan BFG Repo-Cleaner (recommended)
```

**Opsi Lebih Mudah - Reset ke Commit Sebelum File Besar:**

```bash
# Cari commit sebelum file besar ditambahkan
git log --oneline --all

# Reset ke commit sebelum file besar (HATI-HATI! Ini akan hapus commit setelahnya)
# Ganti COMMIT_HASH dengan hash commit sebelum file besar
git reset --hard COMMIT_HASH

# Force push
git push origin master --force
```

### Langkah 5: Push

```bash
# Push setelah hapus file besar
git push origin master --force-with-lease
```

---

## Solusi Alternatif: Buat Branch Baru (Tapi Masih Perlu Hapus File Besar)

Buat branch baru **TETAPI** tetap perlu hapus file besar dulu:

```bash
cd /home/ymsuperadmin/public_html

# Hapus file besar dari Git
git rm --cached .git.zip
git rm --cached "tms.ymsofterp.com/app.zip"

# Tambahkan ke .gitignore
echo ".git.zip" >> .gitignore
echo "*.zip" >> .gitignore
echo "tms.ymsofterp.com/" >> .gitignore

# Commit
git add .gitignore
git commit -m "Remove large files and add to .gitignore"

# Buat branch baru
git checkout -b server-restore-clean

# Push branch baru
git push origin server-restore-clean

# Nanti bisa merge ke master di GitHub (via web interface)
```

**Tapi masalahnya tetap sama** - file besar masih akan ditolak saat push.

---

## Solusi Terbaik: Tidak Perlu Push ke Git ⭐⭐⭐

**Untuk sync dari server ke local, TIDAK perlu push ke Git!**

### Download Langsung dari Server:

1. **Via cPanel File Manager** (paling mudah)
   - Compress folder: `app`, `routes`, `bootstrap`, `config`, `database`
   - Download ZIP
   - Extract di local

2. **Via SSH/SCP**
   ```bash
   # Di local
   scp -r ymsuperadmin@server:/home/ymsuperadmin/public_html/app ./
   scp -r ymsuperadmin@server:/home/ymsuperadmin/public_html/routes ./
   # dst...
   ```

3. **Via WinSCP** (GUI, drag & drop)

**Keuntungan:**
- ✅ Tidak perlu hapus file besar
- ✅ Tidak perlu force push
- ✅ Lebih cepat dan mudah
- ✅ Tidak ada limit file size

---

## Quick Fix Command

```bash
# Di server
cd /home/ymsuperadmin/public_html

# 1. Hapus file besar dari Git
git rm --cached .git.zip
git rm --cached "tms.ymsofterp.com/app.zip"

# 2. Tambahkan ke .gitignore
echo ".git.zip" >> .gitignore
echo "*.zip" >> .gitignore
echo "tms.ymsofterp.com/" >> .gitignore

# 3. Commit
git add .gitignore
git commit -m "Remove large zip files from Git"

# 4. Push
git push origin master --force-with-lease
```

---

## File yang Seharusnya di .gitignore

Pastikan file-file berikut ada di `.gitignore`:

```
# Zip files
*.zip
.git.zip

# Backup files
*.backup
*.bak
*.old

# Temporary files
*.tmp
*.temp

# Large files
tms.ymsofterp.com/
*.log (kecuali jika perlu)

# Vendor (jika belum ada)
vendor/
node_modules/
```

---

## Rekomendasi

**Untuk sync server ke local:**
1. ✅ **Download langsung dari server** (cPanel/SSH) - **TIDAK perlu Git**
2. ✅ **Replace folder di local**
3. ✅ **Commit ke Git dari local** (jika perlu backup)

**Jika tetap ingin push ke Git:**
1. ✅ **Hapus file besar dari Git** (lihat langkah di atas)
2. ✅ **Tambahkan ke .gitignore**
3. ✅ **Commit dan push**

---

## Checklist

- [ ] Hapus file besar dari Git tracking
- [ ] Tambahkan ke .gitignore
- [ ] Commit perubahan
- [ ] Push ke GitHub
- [ ] Verify file besar tidak ada di GitHub

