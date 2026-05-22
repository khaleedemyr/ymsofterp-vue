# Facebook Messenger — polling (seperti Instagram Login)

Messenger di ERP **tidak wajib** mengandalkan webhook. DM diambil lewat **Page Conversations API** (`graph.facebook.com`), sama polanya dengan `meta:sync-instagram-inbox`.

## 1. Token Page (wajib)

Di Meta App → **Messenger API Settings** → pilih Page → **Generate** Page access token.

`.env` (satu baris JSON):

```env
META_PAGE_TOKENS='{"1587793758107643":"EAA...AsianGrill","682421618556416":"EAA...JustusSteak"}'
```

Atau satu Page:

```env
META_PAGE_ID=1587793758107643
META_PAGE_ACCESS_TOKEN=EAA...
```

**Jangan** masukkan token Instagram Login (`IGQ...`) atau IG professional id ke `META_PAGE_TOKENS` — itu field terpisah (`META_INSTAGRAM_LOGIN_TOKENS`).

Permission minimal: `pages_messaging`, `pages_manage_metadata`, `pages_read_engagement`.

## 2. Aktifkan polling

```env
META_MESSENGER_INBOX_SYNC_ENABLED=true
```

Cron Laravel (`schedule:run` tiap menit) menjalankan:

`php artisan meta:sync-messenger-inbox --recent=60`

Log: `storage/logs/meta-messenger-inbox-sync.log`

## 3. Perintah manual

```bash
php artisan config:clear
php artisan meta:debug-messenger-inbox
php artisan meta:sync-messenger-inbox --recent=60 -v
```

Impor riwayat lama (lambat):

```bash
php artisan meta:sync-messenger-inbox -v
```

## 4. Tanpa webhook

Webhook `/api/webhooks/meta/messenger` **opsional**. Bila kemarin gagal setup webhook, cukup polling + cron.

Buka **CRM → Omnichannel** juga memicu sync (max tiap ~30 detik, DM 45 menit terakhir).

## 5. Troubleshooting

| Gejala | Solusi |
|--------|--------|
| `Tidak ada Page token` | Isi `META_PAGE_TOKENS` / `META_PAGE_ACCESS_TOKEN` + `META_PAGE_ID` |
| `/me gagal` | Token bukan Page token atau expired — generate ulang di Meta |
| `conversations?platform=messenger gagal` | Permission / Page belum connect ke app |
| `imported=0`, `skip_out` tinggi | Pesan dari Page sendiri (outbound) — normal |
| `api_err` tinggi | Rate limit — tunggu atau kurangi frekuensi full sync |

## 6. Channel di inbox

| Channel | Sumber |
|---------|--------|
| `messenger` | Poll `meta:sync-messenger-inbox` (+ webhook opsional) |
| `instagram` | Poll `meta:sync-instagram-inbox` (Instagram Login) |
| `whatsapp` | Webhook |

Balas chat Messenger tetap pakai **Page token** (`MetaMessengerClient`).
