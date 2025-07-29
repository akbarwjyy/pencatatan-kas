<?php
// Start output buffering
ob_start();

// Pastikan session dimulai sebelum menggunakan $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database dan fungsi-fungsi umum
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers.php'; // Untuk flash message, dll.
require_once __DIR__ . '/../includes/path_helper.php'; // Helper untuk path

// Cek apakah pengguna sudah login, jika tidak redirect ke halaman login
// Kecualikan halaman login dan reset_password dari redirect
if (!is_logged_in() && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'reset_password.php') {
    redirect('login.php');
}

// Ambil data pengguna dari session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Tamu';
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Guest';

// Dapatkan nama file script saat ini
$current_page = basename($_SERVER['PHP_SELF']);
$relative_path = get_relative_path_to_root();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencatatan Kas Ampyang Cap Garuda - <?php echo ucwords(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))); ?></title>
    <link rel="stylesheet" href="<?php echo $relative_path; ?>assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="flex">
        <?php if ($current_page !== 'login.php' && $current_page !== 'reset_password.php') : ?>
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="flex-1 <?php echo ($current_page !== 'login.php' && $current_page !== 'reset_password.php') ? 'md:ml-64' : ''; ?>">
            <header class="bg-gray-900 text-white shadow-lg sticky top-0 z-30">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between px-8 py-4">
                    <div class="flex items-center gap-3">
                        <!-- Mobile Menu Toggle -->
                        <button onclick="document.getElementById('sidebar').classList.toggle('hidden')" class="md:hidden mr-3 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <h1 class="text-xl md:text-2xl font-extrabold tracking-wide">Pencatatan Kas Ampyang Cap Garuda</h1>
                    </div>
                    <div class="mt-2 md:mt-0 flex items-center gap-2">
                        <span class="text-lg">👤</span>
                        <span class="font-semibold">Selamat datang, <span class="underline decoration-white"><?php echo htmlspecialchars($user_name); ?></span></span>
                        <span class="ml-2 text-sm">(Jabatan: <span class="font-semibold text-yellow-200"><?php echo htmlspecialchars($user_role); ?></span>)</span>
                        <?php if ($current_page !== 'login.php' && $current_page !== 'reset_password.php') : ?>
                            <a href="<?php echo $relative_path; ?>logout.php" class="ml-4 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold shadow transition">Logout</a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            <div class="wrapper px-4 md:px-8 pt-6">
                <div class="main-content">
                    <?php
                    // Tampilkan pesan flash jika ada
                    echo display_flash_message();
                    ?>