# Broadcast WhatsApp — setup

## Prasyarat

- `META_WHATSAPP_ACCESS_TOKEN`, `META_WHATSAPP_PHONE_NUMBER_ID`, `META_WHATSAPP_BUSINESS_ACCOUNT_ID`
- Template pesan **disetujui Meta** (Marketing / Utility) untuk broadcast di luar jendela 24 jam
- Queue worker: `php artisan queue:work --queue=wa-broadcast,omnichannel,notifications`

## Database

```bash
php artisan migrate
# atau database/sql/create_wa_broadcast_tables.sql
```

Menu: `database/sql/insert_wa_broadcast_menu.sql`

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
