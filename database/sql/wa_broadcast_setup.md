# Broadcast WhatsApp — setup

## Prasyarat

- `META_WHATSAPP_ACCESS_TOKEN`, `META_WHATSAPP_PHONE_NUMBER_ID`, `META_WHATSAPP_BUSINESS_ACCOUNT_ID`
- Template pesan **disetujui Meta** (Marketing / Utility) untuk broadcast di luar jendela 24 jam
- Queue worker via Supervisor (lihat `deployment/supervisor-laravel-queue.conf.example`)

## Database

```bash
php artisan migrate
# atau database/sql/create_wa_broadcast_tables.sql (3 tabel: campaigns, recipients, daily_usage)

**Error `wa_broadcast_daily_usages doesn't exist`:** deploy `app/Models/WaBroadcastDailyUsage.php` (property `$table = 'wa_broadcast_daily_usage'`) lalu pastikan tabel sudah dibuat di DB.
```

Menu: `database/sql/insert_wa_broadcast_menu.sql` atau `php artisan migrate` (migration `2026_05_22_120001_insert_wa_broadcast_menu.php`)

**Sidebar tidak muncul?**

1. Di server: `php database/scripts/diagnose_wa_broadcast_sidebar.php <USER_ID>` (ganti USER_ID)
2. Perbaiki otomatis: tambahkan `--fix` atau jalankan SQL `database/sql/fix_wa_broadcast_sidebar.sql`
3. Logout → login, hard refresh browser
4. Pastikan `npm run build` sudah di-deploy (AppLayout harus ada `wa_broadcast`)

**Akar bug yang pernah terjadi:** permission `view` salah pakai `code='wa_broadcast'` (harus `wa_broadcast_view`), dan sync role gagal karena `erp_role_permission` tidak punya kolom `created_at`.

## Env

```env
WA_BROADCAST_DAILY_CAP=100000
WA_BROADCAST_QUEUE=wa-broadcast
WA_BROADCAST_BATCH_SIZE=50
```

## Filter penerima (JSON)

**Filter statis (selalu):**
- Nomor HP / `phone_normalized` terisi (tidak null & tidak kosong)
- Member `is_active = 1` (kontak omni terhubung member ikut aturan ini)

**Opsional:**
- **transaction_from** / **transaction_to** — order `paid` di tabel `orders` (`orders.member_id` = `member_apps_members.member_id`, `created_at` dalam rentang)
- **member** — level, spending, search, allow_notification, dll.
- **omni_contact** — `omni_contacts.phone_normalized`
- **manual_member_ids** — ID member dipilih manual
- **dedupe** — satu nomor sekali per campaign

## Performa hitung penerima

Preview memakai `COUNT` SQL (bukan scan seluruh baris). Jika filter transaksi masih lambat, jalankan index (sekali):

```sql
CREATE INDEX idx_orders_status_created_member ON orders (status, created_at, member_id);
```

## Alur

1. `/crm/wa-broadcast/create` → atur filter → klik **Hitung penerima** (manual, tidak auto)
2. Template: **Buat template baru** (ajukan ke Meta via API) atau muat yang sudah **Approved**
3. Simpan draft atau Kirim sekarang
3. Job `BuildWaBroadcastRecipientsJob` → isi `wa_broadcast_recipients`
4. Job `SendWaBroadcastMessageJob` → kirim per nomor, hormati kuota harian

## Template dari ERP

- `POST /crm/wa-broadcast/templates` — body teks + variabel `{{1}}`, `{{2}}`, … + contoh nilai
- Status awal **PENDING**; setelah **APPROVED** muncul di dropdown campaign
- Token Meta perlu izin **whatsapp_business_management**

## WhatsApp production → ERP (bukan Sleekflow)

Jika `GET /{WABA_ID}/subscribed_apps` hanya menampilkan **Sleekflow** (`812364635796464`), webhook DM production masuk ke Sleekflow, bukan ERP.

**ID referensi (Justus Steakhouse production):**

| Item | ID |
|------|-----|
| WABA | `830741246688763` |
| Phone Number ID | `896059726934135` (`+62 811-1018-8808`) |
| Meta App ERP (YMSoft) | `1302269045204850` |
| Meta App Sleekflow | `812364635796464` |

### 1. Lepas Sleekflow dari WABA

Salah satu (wajib, supaya slot webhook tidak dipegang Sleekflow):

- Di **Sleekflow**: putuskan / unsubscribe nomor WhatsApp Business ini, atau
- **Meta Business Suite** → Settings → Partners / Integrated apps → cabut akses Sleekflow ke WABA ini.

### 2. Subscribe app ERP ke WABA

Token harus dari **app ERP** (`1302269045204850`) — System User dengan akses WABA + izin `whatsapp_business_management`, `whatsapp_business_messaging`.

**Di server (setelah Sleekflow dilepas):**

```bash
php artisan meta:whatsapp-waba-subscribe              # cek siapa yang subscribe
php artisan meta:whatsapp-waba-subscribe --subscribe  # daftarkan app ERP ke WABA
```

Harus muncul **YMSoft ERP** (`1302269045204850`), bukan hanya Sleekflow (`812364635796464`).

Atau manual API:

```http
POST https://graph.facebook.com/v21.0/830741246688763/subscribed_apps
Authorization: Bearer {META_WHATSAPP_ACCESS_TOKEN_ERP}
```

### 3. Webhook di Meta App Dashboard (ERP)

[developers.facebook.com](https://developers.facebook.com) → app **YMSoft ERP** → WhatsApp → Configuration:

| Field | Nilai |
|-------|--------|
| Callback URL | `https://ymsofterp.com/api/webhooks/meta/whatsapp` |
| Verify token | sama dengan `META_WEBHOOK_VERIFY_TOKEN` di `.env` production |
| Webhook fields | centang **`messages`** (dan field lain jika perlu) |

Klik **Verify and save**. Test dari Meta harus HTTP 200.

### 4. `.env` production (nomor asli, bukan test)

```env
META_WHATSAPP_ACCESS_TOKEN=...   # token app ERP, bukan Sleekflow
META_WHATSAPP_PHONE_NUMBER_ID=896059726934135
META_WHATSAPP_BUSINESS_ACCOUNT_ID=830741246688763
META_WEBHOOK_VERIFY_TOKEN=...
```

Lalu `php artisan config:clear`.

### 5. Uji

Kirim DM ke `0811-1018-8808` → harus muncul di **Omnichannel Inbox** ERP.

**Penting:** `subscribed_apps` (Postman) ≠ webhook aktif. Di **App Dashboard → WhatsApp → Configuration** wajib **Verify and save** callback + field **`messages`**.

### 6. Chat dikirim tapi tidak masuk inbox

```bash
php artisan meta:debug-whatsapp-webhook
tail -20 storage/logs/whatsapp-webhook.trace.log
grep "Meta WhatsApp" storage/logs/laravel.log | tail -30
```

| Gejala | Penyebab umum |
|--------|----------------|
| Trace log **kosong** | Callback salah (harus ada `/api/`) atau belum Verify di App Dashboard |
| `sig_invalid` di trace | `META_APP_SECRET` bukan secret app ERP `1302269045204850` |
| POST ada, DB kosong | Error simpan — lihat `webhook processing failed` di laravel.log |
| DB ada, UI kosong | Filter inbox / hak akses tim |

Debug sementara: `META_WEBHOOK_SKIP_SIGNATURE_VERIFY=true` lalu `config:clear` (matikan setelah beres).

## Catatan Meta

- Broadcast massal wajib **template resmi**, bukan teks bebas
- Teks bebas hanya untuk kontak dalam jendela layanan 24 jam
- Patuhi kebijakan spam & opt-in pelanggan
