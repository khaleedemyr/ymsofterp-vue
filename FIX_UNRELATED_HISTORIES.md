# Fix "refusing to merge unrelated histories"

Error yang muncul:
```
fatal: refusing to merge unrelated histories
```

**Penyebab:**
- Local dan remote memiliki history yang berbeda
- Biasanya terjadi setelah repository di server dibuat ulang (reset atau init baru)

## Solusi: Allow Unrelated Histories

### Opsi 1: Pull dengan --allow-unrelated-histories ⭐

```powershell
# Di PowerShell (local)
cd D:\Gawean\YM\web\ymsofterp

# Pull dengan allow unrelated histories
git pull origin master --allow-unrelated-histories

# Jika ada conflict, resolve manual
# Kemudian commit
git add .
git commit -m "Merge unrelated histories"
```

### Opsi 2: Fetch dan Merge Manual

```powershell
# Fetch dulu
git fetch origin

# Merge dengan allow unrelated histories
git merge origin/master --allow-unrelated-histories

# Jika ada conflict, resolve manual
git add .
git commit -m "Merge unrelated histories"
```

### Opsi 3: Reset Local ke Remote (Jika Local History Tidak Penting)

**⚠️ PERINGATAN:** Ini akan **hapus semua commit local** dan replace dengan versi remote.

```powershell
# Fetch
git fetch origin

# Reset local ke remote
git reset --hard origin/master
```

### Opsi 4: Buat Branch Baru dari Remote

```powershell
# Fetch
git fetch origin

# Buat branch baru dari remote
git checkout -b server-version origin/master

# Atau merge ke master
git checkout master
git merge server-version --allow-unrelated-histories
```

---

## Setelah Merge

### 1. Resolve Conflicts (Jika Ada)

Jika ada conflict:
```powershell
# Cek file yang conflict
git status

# Edit file yang conflict, pilih versi yang diinginkan
# Kemudian:
git add .
git commit -m "Resolve merge conflicts"
```

### 2. Push ke GitHub (Jika Perlu)

```powershell
git push origin master
```

---

## Rekomendasi

**Untuk sync dari server ke local:**

**TIDAK perlu pull dari Git!** Lebih mudah download langsung:

### Via cPanel File Manager:
1. Login cPanel
2. File Manager → Navigate ke `public_html`
3. Compress folder: `app`, `routes`, `bootstrap`, `config`, `database`
4. Download ZIP
5. Extract di local, replace folder

### Via SSH/SCP:
```powershell
# Di PowerShell
cd D:\Gawean\YM\web\ymsofterp

scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/app ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/routes ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/bootstrap ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/config ./
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/database ./
```

**Keuntungan:**
- ✅ Tidak perlu resolve unrelated histories
- ✅ Tidak perlu merge conflicts
- ✅ Lebih cepat dan mudah
- ✅ Langsung dapat versi server

---

## Quick Fix Command

```powershell
# Di PowerShell (local)
cd D:\Gawean\YM\web\ymsofterp

# Pull dengan allow unrelated histories
git pull origin master --allow-unrelated-histories

# Jika ada conflict, resolve manual lalu:
git add .
git commit -m "Merge unrelated histories"
```

---

## Checklist

- [ ] Pull dengan `--allow-unrelated-histories`
- [ ] Resolve conflicts (jika ada)
- [ ] Commit merge
- [ ] Test aplikasi
- [ ] Push ke GitHub (jika perlu)

