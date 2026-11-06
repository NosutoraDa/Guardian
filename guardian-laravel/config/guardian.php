<?php

return [

    'examples' => [
        [
            'label' => 'Link phishing',
            'text' => 'https://hadiah-bank-mandiri.blogspot.com/login-verifikasi?token=99283 Akun Anda akan diblokir hari ini, segera verifikasi dan masukkan kata sandi serta OTP.',
        ],
        [
            'label' => 'Undangan APK',
            'text' => 'Halo Kak, ini undangan pernikahan digital kami. Silakan instal SuratUndangan.apk ini ya.',
        ],
        [
            'label' => 'Lowongan kerja',
            'text' => 'Lowongan kerja paruh waktu, komisi besar. Untuk daftar, transfer biaya admin 150rb sekarang.',
        ],
    ],

    'ai' => [
        'endpoint' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'virustotal' => [
        'api_key' => env('VIRUSTOTAL_API_KEY'),
    ],

];
