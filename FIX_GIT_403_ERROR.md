# Fix Git 403 Forbidden Error

Error yang muncul:
```
remote: Permission to khaleedemyr/ymsofterp-vue.git denied to khaleedemyr.
fatal: unable to access 'https://github.com/khaleedemyr/ymsofterp-vue.git/': The requested URL returned error: 403
```

## Penyebab Umum

1. **Token tidak memiliki permission yang cukup** (scope `repo` tidak dicentang)
2. **Token sudah expired**
3. **Token tidak valid atau salah**
4. **Repository private dan token tidak punya akses**
5. **Format URL salah**

## Solusi 1: Buat Token Baru dengan Permission Lengkap ⭐

### Langkah 1: Buat Token Baru di GitHub

1. Login ke GitHub: https://github.com
2. Klik profile picture (kanan atas) → **Settings**
3. Scroll ke bawah → **Developer settings** (sidebar kiri)
4. Klik **Personal access tokens** → **Tokens (classic)**
5. Klik **Generate new token** → **Generate new token (classic)**
6. Isi form:
   - **Note**: `ymsofterp-server-push` (atau nama lain)
   - **Expiration**: Pilih durasi (90 days, 1 year, atau **No expiration**)
   - **Scopes**: **PENTING!** Centang minimal:
     - ✅ **`repo`** (Full control of private repositories)
       - Ini akan otomatis centang sub-scopes:
         - ✅ `repo:status`
         - ✅ `repo_deployment`
         - ✅ `public_repo`
         - ✅ `repo:invite`
         - ✅ `security_events`
7. Scroll ke bawah, klik **Generate token**
8. **COPY TOKEN SEKARANG!** (hanya muncul sekali, format: `ghp_xxxxxxxxxxxx`)

### Langkah 2: Update Remote URL dengan Token Baru

```bash
cd /home/ymsuperadmin/public_html

# Hapus remote URL lama
git remote remove origin

# Atau update dengan token baru
git remote set-url origin https://khaleedemyr:NEW_TOKEN_HERE@github.com/khaleedemyr/ymsofterp-vue.git

# Ganti NEW_TOKEN_HERE dengan token baru yang sudah dibuat
# Contoh:
# git remote set-url origin https://khaleedemyr:ghp_xxxxxxxxxxxx@github.com/khaleedemyr/ymsofterp-vue.git

# Verify
git remote -v

# Test push
git push origin main
```

---

## Solusi 2: Cek Token yang Sudah Ada

### Langkah 1: Cek Token di GitHub

1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Cari token yang sudah dibuat
3. Cek:
   - ✅ Apakah masih **Active**?
   - ✅ Apakah scope **`repo`** sudah dicentang?
   - ✅ Apakah sudah **expired**?

### Langkah 2: Regenerate Token (Jika Perlu)

Jika token sudah expired atau tidak punya permission:
1. Klik token yang ada
2. Klik **Regenerate token**
3. Pastikan scope **`repo`** dicentang
4. Copy token baru
5. Update remote URL (lihat Solusi 1)

---

## Solusi 3: Gunakan SSH Key (Lebih Aman) ⭐⭐⭐

Jika token masih bermasalah, gunakan SSH key:

### Langkah 1: Generate SSH Key di Server

```bash
cd /home/ymsuperadmin/public_html

# Generate SSH key
ssh-keygen -t ed25519 -C "ymsofterp-server"
# Tekan Enter 3x (default location, no passphrase)

# Copy public key
cat ~/.ssh/id_ed25519.pub
# COPY seluruh output
```

### Langkah 2: Tambahkan ke GitHub

1. Buka: https://github.com/settings/keys
2. Klik **"New SSH key"**
3. Isi:
   - **Title**: `ymsofterp-server`
   - **Key**: Paste public key
4. Klik **"Add SSH key"**

### Langkah 3: Update Remote URL ke SSH

```bash
cd /home/ymsuperadmin/public_html

# Update remote URL ke SSH
git remote set-url origin git@github.com:khaleedemyr/ymsofterp-vue.git

# Test connection
ssh -T git@github.com
# Harus muncul: "Hi khaleedemyr! You've successfully authenticated..."

# Push
git push origin main
```

---

## Solusi 4: Cek Repository Access

### Pastikan Token/SSH Key Punya Akses ke Repository

1. Buka repository: https://github.com/khaleedemyr/ymsofterp-vue
2. Cek apakah repository **private** atau **public**
3. Jika private, pastikan:
   - Token memiliki scope `repo` (untuk private repos)
   - Atau SSH key sudah ditambahkan ke GitHub account yang punya akses

---

## Solusi 5: Clear Credential Cache

Jika masih error setelah update token:

```bash
# Clear credential cache
git credential-cache exit

# Atau hapus credential helper
git config --global --unset credential.helper

# Update remote URL lagi dengan token baru
git remote set-url origin https://khaleedemyr:NEW_TOKEN@github.com/khaleedemyr/ymsofterp-vue.git

# Push
git push origin main
```

---

## Troubleshooting

### Error: "Token tidak valid"

**Solusi:**
- Pastikan token di-copy dengan lengkap (termasuk `ghp_` di awal)
- Pastikan tidak ada spasi di awal/akhir token
- Buat token baru jika perlu

### Error: "Repository not found"

**Solusi:**
- Pastikan repository name benar: `ymsofterp-vue`
- Pastikan username benar: `khaleedemyr`
- Cek apakah repository ada di: https://github.com/khaleedemyr/ymsofterp-vue

### Error: "Permission denied" meski sudah pakai token

**Solusi:**
- Pastikan scope **`repo`** sudah dicentang saat buat token
- Buat token baru dengan permission lengkap
- Atau gunakan SSH key (lebih aman)

---

## Checklist

- [ ] Token dibuat dengan scope **`repo`** dicentang
- [ ] Token belum expired
- [ ] Token di-copy dengan lengkap (termasuk `ghp_`)
- [ ] Remote URL sudah di-update dengan token baru
- [ ] Repository name dan username benar
- [ ] Repository accessible (bukan private tanpa akses)

---

## Rekomendasi

**Untuk keamanan dan kemudahan:**
1. ✅ Gunakan **SSH key** (lebih aman, tidak perlu update token)
2. ✅ Atau gunakan **Personal Access Token dengan scope `repo`**
3. ❌ Jangan hardcode token di URL (lebih baik pakai SSH)

**Alternatif: Tidak Perlu Push ke Git**
- Jika tujuan hanya sync dari server ke local, **tidak perlu push ke Git**
- Download langsung via SSH/SCP atau cPanel File Manager
- Lihat `SYNC_VIA_SSH.md` untuk panduan

---

## Quick Fix Command

```bash
# 1. Buat token baru di GitHub dengan scope 'repo'
# 2. Update remote URL
cd /home/ymsuperadmin/public_html
git remote set-url origin https://khaleedemyr:NEW_TOKEN@github.com/khaleedemyr/ymsofterp-vue.git

# 3. Test push
git push origin main
```

**Ganti `NEW_TOKEN` dengan token baru yang sudah dibuat di GitHub.**

