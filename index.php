<?php
// Memulai sesi PHP jika belum dimulai. Penting untuk manajemen sesi (login/logout).
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file konfigurasi dan fungsi-fungsi penting
// Gunakan __DIR__ untuk memastikan path relatif yang benar
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helpers.php';

// Cek apakah pengguna sudah login
if (is_logged_in()) {
    // Jika sudah login, arahkan ke halaman dashboard
    redirect('modules/dashboard/index.php');
} else {
    // Jika belum login, arahkan ke halaman login
    redirect('login.php');
}
