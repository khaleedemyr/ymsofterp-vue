# Fix Git Authentication di Server

Error yang muncul:
```
remote: Invalid username or token. Password authentication is not supported for Git operations.
fatal: Authentication failed
```

## Solusi: Setup Personal Access Token (PAT)

### Langkah 1: Buat Personal Access Token di GitHub

1. Login ke GitHub: https://github.com
2. Klik profile picture (kanan atas) -> **Settings**
3. Scroll ke bawah -> **Developer settings** (di sidebar kiri)
4. Klik **Personal access tokens** -> **Tokens (classic)**
5. Klik **Generate new token** -> **Generate new token (classic)**
6. Isi:
   - **Note**: `ymsofterp-server` (atau nama lain)
   - **Expiration**: Pilih durasi (90 days, 1 year, atau no expiration)
   - **Scopes**: Centang minimal:
     - ✅ `repo` (Full control of private repositories)
7. Klik **Generate token**
8. **COPY TOKEN SEKARANG** (hanya muncul sekali!)

### Langkah 2: Setup di Server

**Opsi A: Gunakan Token sebagai Password (Paling Mudah)**

Di server, saat push:
```bash
cd /home/ymsuperadmin/public_html

# Saat diminta password, gunakan TOKEN (bukan password GitHub)
git push origin main

# Username: khaleedemyr
# Password: <paste token yang sudah dibuat>
```

**Opsi B: Setup Credential Helper (Permanen)**

```bash
cd /home/ymsuperadmin/public_html

# Set credential helper untuk menyimpan token
git config --global credential.helper store

# Atau untuk sementara saja (tidak disimpan)
git config credential.helper 'cache --timeout=3600'

# Push sekali dengan token
git push origin main
# Username: khaleedemyr
# Password: <paste token>
```

**Opsi C: Update Remote URL dengan Token (Paling Aman)**

```bash
cd /home/ymsuperadmin/public_html

# Cek remote URL saat ini
git remote -v

# Update remote URL dengan token
git remote set-url origin https://khaleedemyr:YOUR_TOKEN_HERE@github.com/khaleedemyr/ymsofterp-vue.git

# Ganti YOUR_TOKEN_HERE dengan token yang sudah dibuat
# Contoh:
# git remote set-url origin https://khaleedemyr:ghp_xxxxxxxxxxxx@github.com/khaleedemyr/ymsofterp-vue.git

# Push
git push origin main
```

**Opsi D: Setup SSH Key (Paling Recommended untuk Production)**

```bash
# Di server, generate SSH key
ssh-keygen -t ed25519 -C "server@ymsofterp"
# Tekan Enter untuk semua prompt (default location, no passphrase)

# Copy public key
cat ~/.ssh/id_ed25519.pub

# Copy output dan tambahkan ke GitHub:
# 1. GitHub -> Settings -> SSH and GPG keys
# 2. Klik "New SSH key"
# 3. Paste public key
# 4. Save

# Update remote URL ke SSH
git remote set-url origin git@github.com:khaleedemyr/ymsofterp-vue.git

# Test connection
ssh -T git@github.com

# Push
git push origin main
```

## Alternatif: Tidak Perlu Push ke Git

Jika tujuan hanya sync dari server ke local, **tidak perlu push ke Git**. Gunakan metode download langsung:

### Via cPanel File Manager (Paling Mudah)
1. Login cPanel
2. File Manager -> Navigate ke project
3. Compress folder: `app`, `routes`, `bootstrap`, `config`, `database`
4. Download ZIP
5. Extract di local

### Via FTP (FileZilla)
1. Connect via FTP
2. Download folder dari server ke local

Lihat `SYNC_FROM_SERVER.md` untuk panduan lengkap.

## Rekomendasi

Untuk sync dari server ke local, **tidak perlu push ke Git**. Lebih mudah:
1. Download langsung dari server via cPanel/FTP
2. Replace folder di local
3. Commit ke Git dari local (jika perlu backup)

Jika tetap ingin setup Git di server, gunakan **Opsi C (Token di URL)** atau **Opsi D (SSH Key)**.

