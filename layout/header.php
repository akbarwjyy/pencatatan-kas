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
</head>

<body>
    <div class="header">
        <h1>Aplikasi Pencatatan Kas Ampyang Cap Garuda</h1>
        <p>Selamat datang, **<?php echo htmlspecialchars($user_name); ?>** (Jabatan: **<?php echo htmlspecialchars($user_role); ?>**)</p>
    </div>

    <div class="wrapper">
        <?php include 'sidebar.php'; // Sertakan sidebar di sini 
        ?>

        <div class="main-content">
            <?php
            // Tampilkan pesan flash jika ada
            echo display_flash_message();
            ?>
            ```

            **Penjelasan `header.php`:**

            * **`session_start()`**: Penting untuk memulai sesi PHP agar Anda bisa menggunakan variabel `$_SESSION`.
            * **`require_once`**: Meng-*include* file koneksi database, fungsi umum, dan helper. `__DIR__` digunakan untuk memastikan jalur relatif yang benar dari lokasi `header.php`.
            * **Cek Login**: Logika dasar untuk memeriksa apakah pengguna sudah login. Jika tidak, akan di-redirect ke `login.php`. Ini diterapkan di `header.php` agar setiap halaman yang meng-*include* `header.php` otomatis terlindungi.
            * **Meta Tag & Title**: Dasar-dasar HTML untuk setiap halaman.
            * **Link CSS**: Menghubungkan file `style.css` yang telah Anda buat. Pastikan jalurnya benar (`../assets/css/style.css` karena `header.php` ada di `layout/`).
            * **Wrapper & main-content**: Struktur dasar untuk tata letak dengan sidebar (ini akan menampung konten utama dan sidebar).
            * **`include 'sidebar.php';`**: Memanggil file sidebar di sini, sehingga sidebar akan selalu muncul di setiap halaman.
            * **`display_flash_message()`**: Menampilkan pesan sukses/error dari fungsi helper.

            ---