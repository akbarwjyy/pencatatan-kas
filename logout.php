<?php
// Memulai sesi PHP jika belum dimulai.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = array();

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

session_destroy();

// Sertakan file fungsi untuk redirect
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helpers.php';

// Set pesan flash sukses logout
set_flash_message("Anda berhasil logout.", "success");

// Redirect ke halaman login
redirect('login.php');
