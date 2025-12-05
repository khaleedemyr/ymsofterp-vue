# Hapus File Besar dari Git History

**Masalah:** File besar sudah ter-commit di Git history, jadi `.gitignore` tidak membantu. Perlu hapus dari history.

## Solusi 1: Hapus dari Git Tracking (Jika Belum Ter-commit)

```bash
cd /home/ymsuperadmin/public_html

# Hapus dari Git tracking (tapi tetap ada di server)
git rm --cached .git.zip
git rm --cached "tms.ymsofterp.com/app.zip"

# Commit
git commit -m "Remove large files from Git"
git push origin master --force-with-lease
```

## Solusi 2: Hapus dari Git History (Jika Sudah Ter-commit) ⭐

### Opsi A: Reset ke Commit Sebelum File Besar

```bash
cd /home/ymsuperadmin/public_html

# 1. Cari commit sebelum file besar ditambahkan
git log --oneline --all | head -20

# 2. Reset ke commit sebelum file besar (GANTI COMMIT_HASH)
git reset --hard COMMIT_HASH

# 3. Force push
git push origin master --force
```

**⚠️ PERINGATAN:** Ini akan **hapus semua commit setelah commit tersebut**.

### Opsi B: Hapus File dari Semua Commit (Git Filter-Branch)

```bash
cd /home/ymsuperadmin/public_html

# Hapus file dari semua commit di history
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .git.zip tms.ymsofterp.com/app.zip" \
  --prune-empty --tag-name-filter cat -- --all

# Force push
git push origin master --force
```

### Opsi C: Gunakan BFG Repo-Cleaner (Lebih Mudah) ⭐⭐⭐

```bash
# Download BFG (jika belum ada)
# https://rtyley.github.io/bfg-repo-cleaner/

# Di server
cd /home/ymsuperadmin/public_html

# Hapus file besar
java -jar bfg.jar --delete-files .git.zip
java -jar bfg.jar --delete-files "tms.ymsofterp.com/app.zip"

# Atau hapus semua file > 100MB
java -jar bfg.jar --strip-blobs-bigger-than 100M

# Clean up
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Force push
git push origin master --force
```

## Solusi 3: Buat Repository Baru (Paling Mudah) ⭐⭐⭐

Jika history tidak penting, buat repository baru:

```bash
cd /home/ymsuperadmin/public_html

# 1. Hapus file besar dari working directory (jika tidak perlu)
# Atau pastikan sudah di .gitignore

# 2. Hapus .git folder
rm -rf .git

# 3. Init Git baru
git init
git add .
git commit -m "Initial commit - server restore version"

# 4. Add remote baru (atau update existing)
git remote add origin https://github.com/khaleedemyr/ymsofterp-vue.git
# Atau jika sudah ada:
# git remote set-url origin https://github.com/khaleedemyr/ymsofterp-vue.git

# 5. Force push
git push origin master --force
```

**⚠️ PERINGATAN:** Ini akan **hapus semua Git history** dan mulai dari awal.

## Solusi 4: Hapus File Besar dari Working Directory

Jika file besar tidak diperlukan di server:

```bash
cd /home/ymsuperadmin/public_html

# Hapus file besar (jika tidak perlu)
rm -f .git.zip
rm -rf tms.ymsofterp.com/app.zip

# Hapus dari Git
git rm --cached .git.zip
git rm --cached "tms.ymsofterp.com/app.zip"

# Commit
git add .gitignore
git commit -m "Remove large files"

# Push
git push origin master --force-with-lease
```

## Quick Fix (Recommended)

```bash
cd /home/ymsuperadmin/public_html

# 1. Hapus file besar dari Git tracking
git rm --cached .git.zip 2>/dev/null
git rm --cached "tms.ymsofterp.com/app.zip" 2>/dev/null

# 2. Pastikan .gitignore sudah benar
echo "*.zip" >> .gitignore
echo ".git.zip" >> .gitignore
echo "tms.ymsofterp.com/" >> .gitignore

# 3. Commit
git add .gitignore
git commit -m "Remove large zip files from Git"

# 4. Jika masih error, reset ke commit sebelum file besar
# Cari commit hash sebelum file besar:
git log --oneline --all | grep -v "zip\|backup" | head -1

# Reset (GANTI COMMIT_HASH dengan hash yang ditemukan)
# git reset --hard COMMIT_HASH

# 5. Force push
git push origin master --force-with-lease
```

## Alternatif: Tidak Perlu Push ke Git ⭐⭐⭐

**Untuk sync dari server ke local, TIDAK perlu push ke Git!**

### Download Langsung:

1. **Via cPanel File Manager**
   - Compress folder: `app`, `routes`, `bootstrap`, `config`, `database`
   - Download ZIP
   - Extract di local

2. **Via SSH/SCP**
   ```bash
   scp -r ymsuperadmin@server:/home/ymsuperadmin/public_html/app ./
   ```

**Keuntungan:**
- ✅ Tidak perlu hapus file besar
- ✅ Tidak perlu force push
- ✅ Lebih cepat dan mudah
- ✅ Tidak ada limit file size

## Checklist

- [ ] File besar sudah dihapus dari Git tracking (`git rm --cached`)
- [ ] File besar sudah ditambahkan ke `.gitignore`
- [ ] File besar sudah dihapus dari Git history (jika perlu)
- [ ] Commit perubahan
- [ ] Force push ke GitHub

