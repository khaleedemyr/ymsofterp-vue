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

- **member** — `member_apps_members.mobile_phone`
- **omni_contact** — `omni_contacts.phone_normalized`
- **manual_member_ids** — ID member dipilih manual
- **dedupe** — satu nomor sekali per campaign

## Alur

1. `/crm/wa-broadcast` → atur filter → Preview jumlah
2. Pilih template Meta → Simpan draft atau Kirim sekarang
3. Job `BuildWaBroadcastRecipientsJob` → isi `wa_broadcast_recipients`
4. Job `SendWaBroadcastMessageJob` → kirim per nomor, hormati kuota harian

## Catatan Meta

- Broadcast massal wajib **template resmi**, bukan teks bebas
- Teks bebas hanya untuk kontak dalam jendela layanan 24 jam
- Patuhi kebijakan spam & opt-in pelanggan
