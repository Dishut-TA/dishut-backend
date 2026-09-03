<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CPI Engine URL
    |--------------------------------------------------------------------------
    |
    | URL dasar Python CPI Engine service. PHP mengirim request HTTP ke sini
    | ketika user meng-upload file untuk analisis lahan kritis.
    |
    | Catatan: Hindari konflik port dengan PHP.
    | - PHP default: http://127.0.0.1:8000
    | - Python CPI Engine: http://127.0.0.1:8001 (lihat CPI/.env)
    |
    */
    'engine_url' => env('CPI_ENGINE_URL', 'http://127.0.0.1:8001'),

    /*
    |--------------------------------------------------------------------------
    | CPI Engine Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout dalam detik untuk menunggu respons Python service.
    | Data geospasial besar bisa membutuhkan waktu lama untuk diproses.
    | Default 600 detik (10 menit) sesuai PRD.
    |
    */
    'engine_timeout' => env('CPI_ENGINE_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Storage Path Override
    |--------------------------------------------------------------------------
    |
    | Jika PHP dan Python berjalan di server yang sama, path absolut ini
    | digunakan sebagai base directory untuk menyimpan file upload.
    | Biarkan null untuk menggunakan Laravel default (storage/app/projects/).
    |
    */
    'storage_path' => env('CPI_STORAGE_PATH', null),

];
