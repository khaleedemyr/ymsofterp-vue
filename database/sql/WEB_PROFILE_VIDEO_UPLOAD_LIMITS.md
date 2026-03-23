# Upload video Home Blocks & error 413

Validasi di browser dibatasi **100 MB** (sama dengan Laravel `max:102400` kilobyte).

Jika tetap dapat **413 Content Too Large**, request tidak sampai ke Laravel — naikkan batas di **web server / PHP**:

## PHP (`php.ini`)

```ini
upload_max_filesize = 128M
post_max_size = 128M
```

## Nginx

```nginx
client_max_body_size 128M;
```

## Apache

```apache
LimitRequestBody 134217728
```

Setelah ubah konfigurasi, restart PHP-FPM / Nginx / Apache.
