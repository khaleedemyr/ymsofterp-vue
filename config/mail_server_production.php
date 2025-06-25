<?php
/**
 * Konfigurasi Email untuk Server Production
 * 
 * File ini berisi konfigurasi email yang dioptimalkan untuk server production
 * dengan berbagai fallback options dan error handling yang lebih robust
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mailer untuk Production
    |--------------------------------------------------------------------------
    |
    | Gunakan 'failover' untuk multiple mailer fallback
    |
    */
    'default' => env('MAIL_MAILER', 'failover'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations untuk Production
    |--------------------------------------------------------------------------
    |
    | Konfigurasi multiple mailer dengan fallback
    |
    */
    'mailers' => [
        // Primary SMTP (Gmail)
        'smtp_gmail' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],

        // Alternative SMTP (SendGrid, Mailgun, dll)
        'smtp_alternative' => [
            'transport' => 'smtp',
            'host' => env('MAIL_ALT_HOST', 'smtp.sendgrid.net'),
            'port' => env('MAIL_ALT_PORT', 587),
            'username' => env('MAIL_ALT_USERNAME'),
            'password' => env('MAIL_ALT_PASSWORD'),
            'encryption' => env('MAIL_ALT_ENCRYPTION', 'tls'),
            'timeout' => 30,
        ],

        // Sendmail fallback
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        // Log fallback (untuk debugging)
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL', 'mail'),
        ],

        // Failover configuration
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp_gmail',
                'smtp_alternative', 
                'sendmail',
                'log',
            ],
            'retry_after' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@ymsofterp.com'),
        'name' => env('MAIL_FROM_NAME', 'YMSoft ERP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Untuk production, gunakan queue untuk email
    |
    */
    'queue' => [
        'enabled' => env('MAIL_QUEUE_ENABLED', true),
        'connection' => env('MAIL_QUEUE_CONNECTION', 'database'),
        'queue' => env('MAIL_QUEUE_NAME', 'emails'),
        'delay' => env('MAIL_QUEUE_DELAY', 0),
        'tries' => env('MAIL_QUEUE_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => env('MAIL_RETRY_MAX_ATTEMPTS', 3),
        'delay_between_attempts' => env('MAIL_RETRY_DELAY', 300), // 5 minutes
    ],
]; 