# Meta Messenger & Instagram DM — setup omnichannel ERP

> **Instagram DM (alur YouTube / Instagram Login):** gunakan panduan terpisah  
> [`meta_instagram_login_setup.md`](meta_instagram_login_setup.md) — webhook `/api/webhooks/meta/instagram` + polling `meta:sync-instagram-inbox`.

## 1. Meta Developer (use case: Messenger from Meta)

1. **Permissions** → Add: `pages_messaging`, `pages_manage_metadata`, `pages_read_engagement`, `pages_show_list`
2. **Instagram**: `instagram_basic`, `instagram_manage_messages` (sudah Added = OK)
3. **Messenger API Settings** → Connect Facebook Page → **Generate Page access token**
4. **Instagram settings** → hubungkan akun IG Business ke Page

## 2. Webhook (satu URL untuk Messenger + IG)

| Field | Nilai |
|-------|--------|
| Callback URL | `https://{domain-erp}/api/webhooks/meta/messenger` |
| Verify token | sama dengan `META_WEBHOOK_VERIFY_TOKEN` di `.env` |

Subscribe fields (minimal):

- `messages`
- `messaging_postbacks` (opsional)

Subscribe ke **Page** Anda. Untuk Instagram DM, pastikan IG terhubung ke Page yang sama.

WhatsApp tetap pakai URL terpisah:

`https://{domain-erp}/api/webhooks/meta/whatsapp`

## 3. Beberapa Facebook Page (multi outlet)

Setiap **Page = satu saluran Messenger** di inbox (terpisah dari Page lain).

| Langkah | Per Page yang dipakai |
|--------|------------------------|
| Webhook | **Add Subscriptions** → centang `messages` (+ opsional postbacks) |
| Token | Klik **Generate** → salin token Page itu |

Di `.env` gabungkan semua Page ID + token (JSON):

```env
META_PAGE_TOKENS={"1587793758107643":"EAA...AsianGrill","682421618556416":"EAA...JustusSteak","322480444605503":"EAA...JustusBurger"}
```

Opsional: satu Page default (fallback):

```env
META_PAGE_ID=1587793758107643
META_PAGE_ACCESS_TOKEN=EAA...token Page default
```

Pesan **masuk** dari semua Page yang webhook-nya aktif. Balas chat memakai token Page yang sama dengan percakapan itu (`phone_number_id` = Page ID di database).

## 4. `.env` server (lengkap)

```env
META_APP_ID=
META_APP_SECRET=
META_WEBHOOK_VERIFY_TOKEN=

META_PAGE_ACCESS_TOKEN=
META_PAGE_ID=
META_PAGE_TOKENS={}
META_INSTAGRAM_ACCOUNT_ID=

META_WHATSAPP_ACCESS_TOKEN=
META_WHATSAPP_PHONE_NUMBER_ID=
```

Lalu: `php artisan config:clear`

## 5. Queue

Pastikan worker queue `omnichannel` jalan (flow + notifikasi push pesan masuk).

## 6. Channel di database

| Channel value | Sumber |
|---------------|--------|
| `whatsapp` | Webhook WhatsApp |
| `messenger` | Webhook `object: page` |
| `instagram` | Webhook `object: instagram` |

Percakapan muncul otomatis di **CRM → Omnichannel** (web & app).

## 7. Troubleshooting — DM IG tidak masuk / tidak ada log

### Tombol "Send to My Server" = 200 **bukan** bukti DM live jalan

- Test dari Meta Dashboard hanya memanggil URL sekali (sample JSON). Server balas 200 → URL & verify token OK.
- **DM asli** butuh Meta benar-benar mengirim POST saat ada pesan masuk. Itu terpisah dari tombol test.
- Setelah deploy terbaru, cek file jejak (tidak bergantung `LOG_LEVEL`):
  - `storage/logs/messenger-webhook.trace.log`
  - Baris test: `object=... entries=...`
  - Saat kirim DM: **harus ada baris POST baru**. Tidak ada = Meta tidak mengirim event DM.

### Checklist Meta (paling sering terlewat)

1. **Webhook fields (app):** Subscribe **messages** untuk **Page** dan **Instagram** (bukan hanya `affiliation`).
2. **Per Page (WAJIB untuk DM live):** **Messenger API Settings** → pilih Page → **Add Subscriptions** → centang `messages`. Tanpa ini, test 200 tapi DM tidak pernah dikirim.
3. **IG ↔ Page:** Akun IG Professional terhubung ke Page yang sama (Connected assets).
4. **Recent deliveries:** Saat kirim DM, buka Meta → Webhooks → **Recent deliveries**. Harus ada POST ke `/api/webhooks/meta/messenger` dengan status 200. Kosong = subscription/Page/akun uji salah.
5. **App Development:** Pengirim DM harus akun **Tester** di Roles app Meta. Bukan tester → Meta tidak kirim webhook.
6. **App Live (Instagram Platform):** Beberapa alur butuh app **Live** + permission approved.
7. **DM via Messenger for Instagram** sering pakai `object: page` (bukan `instagram`) — chat tetap masuk inbox channel **messenger**, bukan filter Instagram saja.

### Log server

- `storage/logs/messenger-webhook.trace.log` — setiap GET verify & POST (termasuk `sig_invalid`)
- `storage/logs/laravel-*.log` — `Meta Messenger/Instagram webhook POST received`, `inbound stored`
- Ada trace POST tapi tidak ada `inbound stored` → payload sampai, format event kosong / bukan field `messages`
