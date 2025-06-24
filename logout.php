<?php
// Memulai sesi PHP jika belum dimulai.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Menghapus semua variabel sesi
$_SESSION = array();

// Jika menggunakan cookie sesi, hapus juga cookie tersebut.
// Ini akan menghancurkan sesi, dan bukan hanya data sesi.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Menghancurkan sesi
session_destroy();

// Sertakan file fungsi untuk redirect
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helpers.php'; // Opsional, jika ingin set pesan flash logout

// Set pesan flash sukses logout
set_flash_message("Anda berhasil logout.", "success");

// Redirect ke halaman login
redirect('login.php');
