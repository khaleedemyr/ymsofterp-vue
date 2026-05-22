# Instagram DM — Jalur B (Instagram API with Instagram Login)

Ikuti alur **YouTube / Meta Dashboard → Instagram → API setup with Instagram login**.

Messenger/Page (jalur A) tetap bisa dipakai untuk Facebook Messenger; **Instagram DM** pakai konfigurasi di bawah.

## 1. Meta Dashboard (app YMSoft ERP)

1. **Use cases** → **Manage messaging & content on Instagram** → **API setup with Instagram login**
2. **Step 2 — Add account** → login IG Business → **Generate token** (long-lived ~60 hari)
3. **Step 3 — Configure webhooks**

| Field | Nilai |
|-------|--------|
| Callback URL | `https://{domain-erp}/api/webhooks/meta/instagram` |
| Verify token | sama dengan `META_WEBHOOK_VERIFY_TOKEN` |

Subscribe field: **`messages`** (dan opsional messaging_postbacks, messaging_reactions).

4. App sudah **Published** (Live).

## 2. Dapatkan IG Professional Account ID

Graph API Explorer, token dari **Generate token** di dashboard:

```
GET https://graph.instagram.com/v25.0/me?fields=user_id,username
```

- `user_id` = **IG Professional ID** (untuk key di `.env`)
- `username` = @IG bisnis Anda

## 3. `.env` server

```env
META_APP_ID=
META_APP_SECRET=
META_WEBHOOK_VERIFY_TOKEN=

# Satu akun IG (cukup untuk mulai):
META_INSTAGRAM_LOGIN_ACCESS_TOKEN=IGQ...
META_INSTAGRAM_LOGIN_DEFAULT_ID=17841400914429846

# Beberapa akun IG:
# META_INSTAGRAM_LOGIN_TOKENS='{"17841400914429846":"IGQ...","17841462873080478":"IGQ..."}'

# Badge inbox: nama akun bisnis (Justus, Tempayan, …)
# META_INSTAGRAM_LOGIN_ACCOUNT_LABELS='{"17841400914429846":"Justus Steakhouse","17841462873080478":"Tempayan"}'

META_INSTAGRAM_INBOX_SYNC_ENABLED=true
```
https://{domain-production-erp}/api/webhooks/meta/instagram
```bash
php artisan config:clear
```

## 4. Polling otomatis (Instagram ≠ WhatsApp)

| Kanal | Cara masuk inbox |
|-------|------------------|
| **WhatsApp** | Hampir selalu **webhook** Meta → langsung ke DB (tanpa command poll) |
| **Instagram** | **Polling** `meta:sync-instagram-inbox` tiap **1 menit** (cron) + webhook opsional |

Webhook Instagram Login sering **tidak push** di Development; andalkan polling + cron.

Webhook kadang tidak push; ERP **poll** conversations tiap 1 menit (wajib cron jalan):

```bash
php artisan meta:sync-instagram-inbox -v

# Isi nama & avatar profil (sekali / setelah impor riwayat)
php artisan migrate
# atau: database/sql/alter_omni_contacts_avatar_url.sql
php artisan meta:enrich-instagram-profiles --limit=200
```

Pastikan **cron** Laravel jalan (`schedule:run` per menit). Log: `storage/logs/meta-instagram-inbox-sync.log`.

### DM saya kirim tapi tidak muncul di inbox?

1. **Tes manual dulu** (di server production):
   ```bash
   php artisan config:clear
   php artisan meta:sync-instagram-inbox -v
   ```
   Lihat baris `imported=...`, `api_errors=0`, `conversations>0`. Kalau `error` token → perbarui token di Meta Dashboard (Generate token) lalu update `.env`.

2. **Cron harus jalan** — tanpa ini polling tidak pernah otomatis:
   ```cron
   * * * * * cd /path/ke/ymsofterp && php artisan schedule:run >> /dev/null 2>&1
   ```

3. **DM dari akun pribadi ke IG bisnis** (bukan balas dari akun bisnis sendiri). Pesan dari bisnis sendiri di-skip (outbound).

4. **App Meta Development**: akun IG yang DM harus jadi **Tester** di Meta App (Roles), atau App **Live**.

5. **DM ke akun yang benar** (Justus vs Tempayan) — cek badge / `phone_number_id` di DB.

6. **UI inbox** refresh daftar tiap ~8 detik. Sejak update terbaru, **buka halaman inbox** atau **poll AJAX** juga memicu sync Instagram otomatis (throttle ~30 detik) — tidak perlu `artisan` manual selama ada yang membuka inbox.

7. **Cron tetap disarankan** untuk DM masuk saat tidak ada yang buka inbox:
   ```cron
   * * * * * cd /path/ymsofterp && php artisan schedule:run >> /dev/null 2>&1
   ```
   Tanpa cron, DM hanya masuk saat halaman `/crm/omnichannel-inbox` terbuka.

8. Cek log: `storage/logs/meta-instagram-inbox-sync.log` dan `storage/logs/laravel.log` (cari `Instagram inbox sync`).

### `Imported 111` sekali lalu `Imported 0` terus?

Itu normal untuk impor riwayat pertama. DM **baru** kadang tidak terbaca karena API mengembalikan pesan **lama dulu** di halaman pertama — sudah diperbaiki (urut terbaru + pagination).

Kalau cron tetap 0 untuk DM baru: pastikan scheduler **tanpa** `runInBackground` (deploy terbaru), lalu:

```bash
php artisan schedule:clear-cache
php artisan meta:debug-instagram-inbox
php artisan meta:sync-instagram-inbox -v
```

### Error `Permission denied` pada `instagram_account_registry.json`

Sync gagal total jika folder tidak bisa ditulis. Perbaikan kode sudah pakai Laravel Cache (tidak wajib file). Opsional perbaiki permission:

```bash
mkdir -p storage/app/meta
chmod -R 775 storage/app/meta storage/framework/cache
chown -R ymsuperadmin:ymsuperadmin storage/app/meta storage/framework/cache
```

Lalu `php artisan meta:sync-instagram-inbox` lagi.

Output sync sekarang **selalu** menampilkan baris per akun, mis.:
`imported=0 skip_db=120 skip_out=5 api_err=0` — kirim screenshot baris ini jika masih bermasalah.

Lihat `skipped_db` vs `imported` — kalau DM baru tetap 0, kirim DM dari **akun pribadi** (bukan akun bisnis IG yang sama).

## 5. Verifikasi

```bash
# Token terbaca
php artisan tinker --execute="print_r(array_keys(\App\Support\MetaInstagramTokens::resolved()));"

# Manual sync
php artisan meta:sync-instagram-inbox

# Webhook trace
tail -f storage/logs/instagram-login-webhook.trace.log
```

DM baru → **CRM → Omnichannel**, channel **instagram**.

## 6. Balas chat dari inbox

Outbound IG memakai `graph.instagram.com` otomatis jika `META_INSTAGRAM_LOGIN_TOKENS` / `META_INSTAGRAM_LOGIN_ACCESS_TOKEN` terisi.

## Troubleshooting

| Gejala | Perbaikan |
|--------|-----------|
| Sync 0 pesan | Token expired → Generate ulang di dashboard |
| Webhook verify OK, DM tidak push | Normal; andalkan `meta:sync-instagram-inbox` + cron |
| Permission error | `instagram_business_manage_messages` + `instagram_business_basic` Added/Approved |
