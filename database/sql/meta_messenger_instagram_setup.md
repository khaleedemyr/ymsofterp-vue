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

## 3. `.env` server

```env
META_APP_ID=
META_APP_SECRET=
META_WEBHOOK_VERIFY_TOKEN=

META_PAGE_ACCESS_TOKEN=   # Page token dari Messenger API Settings
META_PAGE_ID=             # ID Facebook Page
META_INSTAGRAM_ACCOUNT_ID=  # opsional, referensi saja

META_WHATSAPP_ACCESS_TOKEN=
META_WHATSAPP_PHONE_NUMBER_ID=
```

Lalu: `php artisan config:clear`

## 4. Queue

Pastikan worker queue `omnichannel` jalan (flow + notifikasi push pesan masuk).

## 5. Channel di database

| Channel value | Sumber |
|---------------|--------|
| `whatsapp` | Webhook WhatsApp |
| `messenger` | Webhook `object: page` |
| `instagram` | Webhook `object: instagram` |

Percakapan muncul otomatis di **CRM → Omnichannel** (web & app).
