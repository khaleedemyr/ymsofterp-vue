<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proses laporan AI tanpa antrian (sync saat submit)
    |--------------------------------------------------------------------------
    |
    | true = job dijalankan di request yang sama saat membuat laporan (tidak
    | perlu queue:work). Hati-hati: bisa timeout di PHP/nginx untuk data besar.
    | false = pakai antrian seperti biasa (disarankan + queue:work).
    |
    */
    'ai_dispatch_sync' => filter_var(env('GOOGLE_REVIEW_AI_DISPATCH_SYNC', false), FILTER_VALIDATE_BOOLEAN),

];
