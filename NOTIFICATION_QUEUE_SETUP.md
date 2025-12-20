# Setup Queue untuk Member Notification

## Overview
Sistem notifikasi member sekarang menggunakan Laravel Queue untuk menangani pengiriman notifikasi dalam jumlah besar (1000+ member). Ini mencegah timeout dan memungkinkan pengiriman ke puluhan ribu member.

## Konfigurasi

### 1. Setup Queue Connection
Pastikan di `.env` sudah dikonfigurasi queue connection:

```env
QUEUE_CONNECTION=database
# atau
QUEUE_CONNECTION=redis
```

### 2. Buat Queue Table (jika menggunakan database)
Jika menggunakan `QUEUE_CONNECTION=database`, jalankan:

```bash
php artisan queue:table
php artisan migrate
```

### 3. Jalankan Queue Worker
Jalankan queue worker untuk memproses jobs:

```bash
# Untuk semua queue
php artisan queue:work

# Atau untuk queue notifications khusus
php artisan queue:work --queue=notifications

# Dengan retry dan timeout
php artisan queue:work --queue=notifications --tries=3 --timeout=300
```

### 4. Supervisor (Production)
Untuk production, gunakan Supervisor untuk menjalankan queue worker secara otomatis:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=notifications --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
stopwaitsecs=3600
```

## Cara Kerja

1. **Notifikasi < 1000 member**: Diproses langsung (synchronous)
2. **Notifikasi ≥ 1000 member**: Diproses melalui Queue Jobs
   - Member dibagi menjadi batch 100 member per job
   - Setiap job memproses 50 member per chunk
   - Delay 0.1 detik setiap 10 member untuk menghindari rate limit FCM

## Monitoring

- Cek status notifikasi di halaman detail notifikasi
- Cek queue jobs di database table `jobs` (jika menggunakan database queue)
- Cek failed jobs di table `failed_jobs`

## Troubleshooting

### Queue tidak jalan
- Pastikan queue worker sedang berjalan
- Cek log di `storage/logs/laravel.log`
- Cek failed jobs: `php artisan queue:failed`

### Notifikasi tidak terkirim
- Cek error message di halaman detail notifikasi
- Pastikan FCM API key sudah dikonfigurasi dengan benar
- Cek device token member masih aktif

### Job stuck
- Restart queue worker
- Clear stuck jobs: `php artisan queue:restart`

