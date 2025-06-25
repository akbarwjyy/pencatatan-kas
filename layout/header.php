<?php
// Pastikan session dimulai sebelum menggunakan $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database dan fungsi-fungsi umum
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers.php'; // Untuk flash message, dll.

// Cek apakah pengguna sudah login, jika tidak redirect ke halaman login
if (!is_logged_in() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    redirect('login.php');
}

// Ambil data pengguna dari session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Tamu';
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Guest';

// Dapatkan nama file script saat ini
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Pencatatan Kas Ampyang Cap Garuda - <?php echo ucwords(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <header class="bg-gray-900 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between px-8 py-4">
            <div class="flex items-center gap-3">
                <!-- <span class="text-3xl">🍬</span> -->
                <h1 class="text-xl md:text-2xl font-extrabold tracking-wide">Aplikasi Pencatatan Kas Ampyang Cap Garuda</h1>
            </div>
            <div class="mt-2 md:mt-0 flex items-center gap-2">
                <span class="text-lg">👤</span>
                <span class="font-semibold">Selamat datang, <span class="underline decoration-white"><?php echo htmlspecialchars($user_name); ?></span></span>
                <span class="ml-2 text-sm">(Jabatan: <span class="font-semibold text-yellow-200"><?php echo htmlspecialchars($user_role); ?></span>)</span>
            </div>
        </div>
        <?php
        if ($current_page !== 'login.php') {
            include 'sidebar.php';
        }
        ?>
    </header>
    <div class="wrapper px-4 md:px-8 pt-6">
        <div class="main-content">
            <?php
            // Tampilkan pesan flash jika ada
            echo display_flash_message();
            ?>