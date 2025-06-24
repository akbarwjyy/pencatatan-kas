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

<body>
    <div class="header bg-gradient-to-r from-blue-500 to-indigo-600 text-white py-6 px-8 rounded-b-lg shadow-lg flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div class="flex items-center space-x-4">
            <span class="text-4xl">🍬</span>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-wide drop-shadow-lg">Aplikasi Pencatatan Kas Ampyang Cap Garuda</h1>
                <p class="mt-1 text-base md:text-lg font-medium flex items-center">
                    <span class="inline-block mr-2 text-xl">👤</span>
                    Selamat datang, <span class="font-semibold underline decoration-white mx-1"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="ml-2">(Jabatan: <span class="font-semibold text-yellow-200"><?php echo htmlspecialchars($user_role); ?></span>)</span>
                </p>
            </div>
        </div>
    </div>

    <div class="wrapper">
        <?php include 'sidebar.php'; // Sertakan sidebar di sini
        ?>

        <div class="main-content">
            <?php
            // Tampilkan pesan flash jika ada
            echo display_flash_message();
            ?>