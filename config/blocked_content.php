<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Keyword Terlarang
    |--------------------------------------------------------------------------
    | Kata-kata yang jika muncul di URL, akan diblokir.
    | Case-insensitive, jadi 'JUDI' dan 'judi' sama-sama terdeteksi.
    */

    'keywords' => [
        // Judi / Slot
        'judi', 'judol', 'slot', 'gacor', 'maxwin', 'togel',
        'toto', 'casino', 'kasino', 'poker', 'domino', 'bandar',
        'rtp', 'jackpot', 'bet', 'betting', 'taruhan',
        'sbobet', 'sbobet88', 'joker123', 'pragmatic',
        'habanero', 'pgsoft', 'microgaming', 'live22',
        'asia99', 'asia88', 'bintang88', 'dewa', 'sultan',

        // Pinjol / Pinjaman Online
        'pinjol', 'pinjaman-online', 'pinjamanonline',
        'pinjam-uang', 'pinjamuang', 'kredit-illegal',
        'kreditilegal', 'dana-cepat', 'danacepat',
        'uang-cepat', 'uangcepat', 'cair-cepat',
        'hutang', 'utang', 'gadai', 'rentenir',
        'investasi-bodong', 'investasibodong',

        // Lainnya
        'porn', 'bokep', 'xxx', 'dewasa',
        'narkoba', 'drugs', 'obat-terlarang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklist Domain
    |--------------------------------------------------------------------------
    | Daftar domain yang sudah pasti terlarang. Cocokkan berdasarkan
    | domain utama (tanpa subdomain).
    */

    'domains' => [
        // Contoh — nanti ditambahkan sesuai kebutuhan
        // 'contohjudol.com',
        // 'contohpinjol.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pesan Error
    |--------------------------------------------------------------------------
    */

    'message' => 'Link yang Anda masukkan terdeteksi sebagai konten yang tidak diizinkan '
        . '(judi online / pinjaman ilegal / konten terlarang). '
        . 'PENINK melarang pemendekan link ke situs tersebut.',

    'message_link_hub' => 'URL yang Anda masukkan terdeteksi sebagai konten yang tidak diizinkan. '
        . 'Mohon periksa kembali URL tujuan.',
];