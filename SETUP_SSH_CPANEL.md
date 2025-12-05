# Setup SSH di cPanel

## Metode 1: Via cPanel Terminal (Jika Tersedia)

### Langkah 1: Akses Terminal di cPanel

1. Login ke cPanel
2. Cari "Terminal" atau "Advanced" -> "Terminal"
3. Jika ada, klik untuk buka terminal
4. Atau cari "SSH Access" di cPanel

### Langkah 2: Generate SSH Key

```bash
# Generate SSH key (ed25519 lebih aman dan cepat)
ssh-keygen -t ed25519 -C "ymsofterp-server"

# Atau jika ed25519 tidak support, gunakan RSA:
ssh-keygen -t rsa -b 4096 -C "ymsofterp-server"

# Tekan Enter untuk semua prompt:
# - File location: Enter (default: ~/.ssh/id_ed25519)
# - Passphrase: Enter (kosongkan, atau isi jika ingin lebih aman)
# - Confirm passphrase: Enter
```

### Langkah 3: Copy Public Key

```bash
# Tampilkan public key
cat ~/.ssh/id_ed25519.pub

# Atau jika pakai RSA:
cat ~/.ssh/id_rsa.pub

# COPY seluruh output (mulai dari ssh-ed25519 atau ssh-rsa sampai email)
```

### Langkah 4: Tambahkan ke GitHub

1. Buka: https://github.com/settings/keys
2. Klik **"New SSH key"**
3. Isi:
   - **Title**: `ymsofterp-server` (atau nama lain)
   - **Key**: Paste public key yang sudah di-copy
4. Klik **"Add SSH key"**

### Langkah 5: Test SSH Connection

```bash
# Test koneksi ke GitHub
ssh -T git@github.com

# Jika berhasil, akan muncul:
# Hi khaleedemyr! You've successfully authenticated, but GitHub does not provide shell access.
```

### Langkah 6: Update Git Remote URL

```bash
cd /home/ymsuperadmin/public_html

# Cek remote URL saat ini
git remote -v

# Update ke SSH (ganti username jika berbeda)
git remote set-url origin git@github.com:khaleedemyr/ymsofterp-vue.git

# Verify
git remote -v

# Test push
git push origin main
```

---

## Metode 2: Via SSH Access di cPanel

### Langkah 1: Enable SSH Access

1. Login ke cPanel
2. Cari **"SSH Access"** atau **"SSH/Shell Access"**
3. Jika belum enable, klik **"Manage SSH Keys"**
4. Atau hubungi hosting provider untuk enable SSH

### Langkah 2: Generate Key via cPanel Interface

1. Di **SSH Access**, klik **"Generate a New Key"**
2. Isi:
   - **Key Name**: `github_key` (atau nama lain)
   - **Key Password**: (optional, bisa kosong)
3. Klik **"Generate Key"**
4. Klik **"View/Download"** untuk melihat public key
5. Copy public key

### Langkah 3: Authorize Key

1. Di **SSH Access**, klik **"Manage"** pada key yang sudah dibuat
2. Klik **"Authorize"** untuk enable key ini
3. Copy public key dan tambahkan ke GitHub (langkah sama seperti di atas)

---

## Metode 3: Via File Manager (Manual)

Jika Terminal tidak tersedia:

1. **Buka File Manager** di cPanel
2. Navigate ke folder home: `/home/ymsuperadmin/`
3. Buat folder `.ssh` jika belum ada (hidden folder, pastikan "Show Hidden Files" enabled)
4. Upload file SSH key yang sudah dibuat di local
5. Set permission:
   - `.ssh` folder: `700`
   - `id_ed25519` (private key): `600`
   - `id_ed25519.pub` (public key): `644`

---

## Troubleshooting

### Error: "Permission denied (publickey)"

**Solusi:**
```bash
# Set permission yang benar
chmod 700 ~/.ssh
chmod 600 ~/.ssh/id_ed25519
chmod 644 ~/.ssh/id_ed25519.pub

# Test lagi
ssh -T git@github.com
```

### Error: "Could not resolve hostname github.com"

**Solusi:**
- Cek koneksi internet server
- Cek DNS settings
- Coba ping: `ping github.com`

### SSH Access Tidak Tersedia di cPanel

**Solusi:**
1. Hubungi hosting provider untuk enable SSH
2. Atau gunakan **Metode Alternatif** (lihat bawah)

---

## Metode Alternatif: Tanpa SSH (Personal Access Token)

Jika SSH tidak tersedia atau terlalu rumit, gunakan Personal Access Token:

### Langkah 1: Buat Token di GitHub

1. GitHub.com → Profile → Settings
2. Developer settings → Personal access tokens → Tokens (classic)
3. Generate new token (classic)
4. Centang `repo` scope
5. Generate dan **COPY TOKEN**

### Langkah 2: Update Git Remote dengan Token

```bash
cd /home/ymsuperadmin/public_html

# Update remote URL dengan token
git remote set-url origin https://khaleedemyr:YOUR_TOKEN@github.com/khaleedemyr/ymsofterp-vue.git

# Ganti YOUR_TOKEN dengan token yang sudah dibuat
# Contoh:
# git remote set-url origin https://khaleedemyr:ghp_xxxxxxxxxxxx@github.com/khaleedemyr/ymsofterp-vue.git

# Push
git push origin main
```

⚠️ **Catatan**: Token akan tersimpan di remote URL. Untuk keamanan lebih baik, gunakan SSH key.

---

## Checklist Setup SSH

- [ ] SSH Access enabled di cPanel
- [ ] SSH key generated (ed25519 atau RSA)
- [ ] Public key ditambahkan ke GitHub
- [ ] SSH connection tested (`ssh -T git@github.com`)
- [ ] Git remote URL di-update ke SSH
- [ ] Test push berhasil

---

## Quick Reference Commands

```bash
# Generate SSH key
ssh-keygen -t ed25519 -C "ymsofterp-server"

# View public key
cat ~/.ssh/id_ed25519.pub

# Test GitHub connection
ssh -T git@github.com

# Update Git remote to SSH
git remote set-url origin git@github.com:khaleedemyr/ymsofterp-vue.git

# Set permissions (jika error)
chmod 700 ~/.ssh
chmod 600 ~/.ssh/id_ed25519
chmod 644 ~/.ssh/id_ed25519.pub
```

---

## Tips

- ✅ **SSH Key lebih aman** daripada Personal Access Token
- ✅ **ed25519 lebih cepat** daripada RSA
- ✅ **Simpan private key dengan aman** - jangan share ke siapa pun
- ✅ **Gunakan passphrase** untuk keamanan ekstra (optional)
- ⚠️ **Jika SSH tidak tersedia**, gunakan Personal Access Token

