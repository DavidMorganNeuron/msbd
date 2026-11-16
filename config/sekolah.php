<?php

// Identitas sekolah. Ganti di sini, semua halaman ikut berubah.
return [
    'nama' => 'SMA Methodist 2 Medan',
    'singkat' => 'SMA Methodist 2',
    'kota' => 'Medan',
    'sistem' => 'Data Siswa & PPDB',
    'logo' => 'images/logo.png', // PNG transparan, letakkan di public/images/logo.png
    'akreditasi' => 'A',
    'npsn' => '10257325',

    // Role yang boleh mengganti kata sandi sendiri.
    // Role yang tidak ada di daftar ini tidak melihat menu "Ganti Sandi"
    // dan ditolak (403) kalau membuka /akun/sandi. Sandinya di-reset oleh TU.
    'role_ganti_sandi' => ['siswa', 'guru', 'wali_kelas', 'tu', 'kepsek'],
];
