# Fix Git Push Rejected - Server Version Lebih Baru

Error yang muncul:
```
! [rejected] master -> master (fetch first)
error: failed to push some refs
hint: Updates were rejected because the remote contains work that you do not have locally.
```

**Masalah:**
- Server punya versi **lebih baru** (dari restore)
- GitHub masih punya versi **lama**
- Kalau `git pull`, server akan kembali ke versi lama (tidak diinginkan)
- Perlu **force push** dari server untuk update GitHub dengan versi baru

## Solusi: Force Push dari Server ⭐

### Opsi 1: Force Push (Jika Yakin Server Versi Lebih Baik)

```bash
cd /home/ymsuperadmin/public_html

# Force push (replace GitHub dengan versi server)
git push origin master --force

# Atau lebih aman, gunakan --force-with-lease
git push origin master --force-with-lease
```

**Perbedaan:**
- `--force`: Force push tanpa cek (lebih agresif)
- `--force-with-lease`: Force push tapi cek dulu apakah ada perubahan yang tidak terlihat (lebih aman)

### Opsi 2: Force Push dengan Branch Baru (Lebih Aman)

Jika tidak yakin, buat branch baru dulu:

```bash
cd /home/ymsuperadmin/public_html

# Buat branch baru dari current state
git checkout -b server-restore-version

# Push branch baru
git push origin server-restore-version

# Setelah yakin, merge ke master di GitHub (via web interface)
# Atau force push master nanti
```

---

## ⚠️ PERINGATAN: Force Push

**Force push akan:**
- ✅ Replace semua commit di GitHub dengan versi server
- ❌ **Hapus commit yang ada di GitHub** (jika berbeda)
- ❌ **Tidak bisa di-undo** dengan mudah

**Pastikan:**
- ✅ Server versi memang lebih baik
- ✅ Tidak ada perubahan penting di GitHub yang akan hilang
- ✅ Sudah backup jika perlu

---

## Langkah-langkah Force Push

### 1. Cek Status di Server

```bash
cd /home/ymsuperadmin/public_html

# Cek status
git status

# Cek commit history
git log --oneline -10

# Cek remote
git remote -v
```

### 2. Commit Perubahan di Server (Jika Belum)

```bash
# Jika ada perubahan yang belum di-commit
git add .
git commit -m "Server restore version - latest code"
```

### 3. Force Push

```bash
# Force push (recommended: --force-with-lease)
git push origin master --force-with-lease

# Jika masih error, gunakan --force
git push origin master --force
```

### 4. Verify

```bash
# Cek apakah push berhasil
git log --oneline -5

# Atau cek di GitHub web interface
```

---

## Alternatif: Tidak Perlu Push ke Git

**Jika tujuan hanya sync dari server ke local:**
- ✅ **TIDAK perlu push ke Git**
- ✅ Download langsung dari server via SSH/SCP atau cPanel
- ✅ Lebih mudah dan cepat

**Metode download:**
1. **Via cPanel File Manager** (paling mudah)
   - Compress folder: `app`, `routes`, `bootstrap`, `config`, `database`
   - Download ZIP
   - Extract di local

2. **Via SSH/SCP** (lihat `SYNC_VIA_SSH.md`)
   ```bash
   scp -r ymsuperadmin@server:/home/ymsuperadmin/public_html/app ./
   # dst...
   ```

3. **Via WinSCP** (GUI, drag & drop)

---

## Setelah Force Push

### Di Local (Setelah Server Push):

```bash
cd D:\Gawean\YM\web\ymsofterp

# Fetch dari GitHub
git fetch origin

# Reset local ke versi GitHub (yang sudah di-update server)
git reset --hard origin/master

# Atau pull dengan force
git pull origin master --force
```

**⚠️ PERINGATAN:** Ini akan **replace semua file local** dengan versi dari GitHub (yang sudah di-update server).

---

## Rekomendasi

### Untuk Sync Server ke Local:

**TIDAK perlu push ke Git.** Lebih mudah:

1. ✅ **Download langsung dari server** via cPanel/SSH
2. ✅ **Replace folder di local**
3. ✅ **Commit ke Git dari local** (jika perlu backup)

**Keuntungan:**
- ✅ Tidak perlu force push
- ✅ Tidak ada risiko kehilangan commit
- ✅ Lebih cepat dan mudah
- ✅ Tidak perlu setup Git auth di server

### Jika Tetap Ingin Push ke Git:

1. ✅ **Force push dari server** dengan `--force-with-lease`
2. ✅ **Pastikan server versi memang lebih baik**
3. ✅ **Backup dulu** jika ada perubahan penting di GitHub

---

## Quick Command

```bash
# Di server
cd /home/ymsuperadmin/public_html

# Commit perubahan (jika ada)
git add .
git commit -m "Server restore version"

# Force push
git push origin master --force-with-lease
```

---

## Checklist

- [ ] Pastikan server versi memang lebih baik
- [ ] Backup perubahan penting (jika ada)
- [ ] Commit perubahan di server (jika ada)
- [ ] Force push dengan `--force-with-lease`
- [ ] Verify di GitHub web interface
- [ ] Update local dari GitHub (jika perlu)

