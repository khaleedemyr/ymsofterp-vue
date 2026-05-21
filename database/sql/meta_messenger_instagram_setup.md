# Meta Messenger & Instagram DM — setup omnichannel ERP

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
