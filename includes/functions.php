<?php
// includes/functions.php

/**
 * Fungsi untuk membersihkan (sanitize) input string dari potensi XSS.
 * Menggunakan htmlspecialchars untuk mengkonversi karakter khusus menjadi entitas HTML.
 *
 * @param string $data Input string yang akan dibersihkan.
 * @return string Data yang sudah bersih.
 */
function sanitize_input($data)
{
    $data = trim($data); // Hapus spasi dari awal dan akhir string
    $data = stripslashes($data); // Hapus backslashes
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Konversi karakter khusus ke entitas HTML
    return $data;
}

/**
 * Fungsi untuk memvalidasi format email.
 *
 * @param string $email String email yang akan divalidasi.
 * @return bool True jika format email valid, false jika tidak.
 */
function is_valid_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Fungsi untuk memvalidasi password (minimal 8 karakter).
 * Anda bisa menambahkan aturan lain di sini (huruf besar, angka, simbol).
 *
 * @param string $password Password yang akan divalidasi.
 * @return bool True jika password valid, false jika tidak.
 */
function is_valid_password($password)
{
    return strlen($password) >= 8; // Minimal 8 karakter
}

/**
 * Fungsi untuk melakukan hash pada password.
 * Disarankan menggunakan PASSWORD_DEFAULT untuk algoritma hashing terbaik yang tersedia.
 *
 * @param string $password Password plain text.
 * @return string Password yang sudah di-hash.
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Fungsi untuk memverifikasi password yang diinput dengan password hash di database.
 *
 * @param string $password_input Password plain text yang diinput pengguna.
 * @param string $hashed_password Password yang sudah di-hash dari database.
 * @return bool True jika cocok, false jika tidak.
 */
function verify_password($password_input, $hashed_password)
{
    return password_verify($password_input, $hashed_password);
}

/**
 * Fungsi untuk redirect ke halaman lain.
 * Menggunakan output buffering jika diperlukan untuk menghindari "headers already sent" error.
 *
 * @param string $location URL tujuan redirect.
 * @param bool $use_javascript Gunakan JavaScript redirect jika header sudah terkirim
 */
function redirect($location)
{
    // Jika output buffering tidak aktif dan header sudah terkirim
    if (!ob_get_level() && headers_sent()) {
        // Gunakan JavaScript untuk redirect
        echo '<script>window.location.href="' . htmlspecialchars($location) . '";</script>';
        // Sebagai fallback, tambahkan link untuk klik manual
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($location) . '">';
        echo '<p>Silahkan klik <a href="' . htmlspecialchars($location) . '">di sini</a> jika tidak ter-redirect secara otomatis.</p>';
        echo '</noscript>';
        exit();
    }

    // Jika masih bisa menggunakan header (tidak ada output atau masih dalam buffer)
    if (ob_get_level()) {
        ob_end_clean(); // Bersihkan output buffer jika ada
    }

    header("Location: " . $location);
    exit(); // Penting untuk menghentikan eksekusi script setelah redirect
}

/**
 * Fungsi untuk mengecek apakah pengguna sudah login.
 * Memerlukan session_start() di awal file PHP yang menggunakannya.
 *
 * @return bool True jika pengguna sudah login, false jika belum.
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']); // Sesuaikan dengan nama session ID pengguna Anda
}

/**
 * Fungsi untuk mengecek hak akses pengguna berdasarkan jabatan.
 *
 * @param string $required_role Jabatan yang dibutuhkan (e.g., 'Admin', 'Pemilik', 'Pegawai').
 * @return bool True jika pengguna memiliki hak akses yang sesuai, false jika tidak.
 */
function has_permission($required_role)
{
    if (!is_logged_in()) {
        return false;
    }
    // Asumsi $_SESSION['user_role'] menyimpan jabatan pengguna saat login
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    $user_role = $_SESSION['user_role'];

    switch ($required_role) {
        case 'Admin':
            return $user_role === 'Admin';
        case 'Pemilik':
            return $user_role === 'Pemilik' || $user_role === 'Admin'; // Admin juga punya akses Pemilik
        case 'Pegawai':
            return $user_role === 'Pegawai' || $user_role === 'Admin' || $user_role === 'Pemilik'; // Semua yang lebih tinggi punya akses Pegawai
        default:
            return false;
    }
}
