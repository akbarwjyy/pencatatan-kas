<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada ID akun yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_akun = sanitize_input(trim($_GET['id']));

    // Cek apakah akun ini memiliki relasi di tabel lain (misal: kas_keluar, transaksi, pemesanan)
    // Jika ada, penghapusan tidak boleh dilakukan untuk menjaga integritas data.
    // Contoh sederhana: Cek di kas_keluar
    $check_kas_keluar_sql = "SELECT COUNT(*) FROM kas_keluar WHERE id_akun = ?";
    $stmt_check = $conn->prepare($check_kas_keluar_sql);
    $stmt_check->bind_param("s", $id_akun);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        set_flash_message("Akun tidak dapat dihapus karena sudah terkait dengan data Kas Keluar. Harap hapus data terkait terlebih dahulu.", "error");
        redirect('index.php');
    } else {
        // Lanjutkan dengan pengecekan relasi lain jika ada (transaksi, pemesanan)
        // ... (Tambahkan logika serupa untuk tabel transaksi dan pemesanan jika id_akun digunakan di sana)

        // Query untuk menghapus data akun
        $sql = "DELETE FROM akun WHERE id_akun = ?";

        // Gunakan prepared statement untuk keamanan
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $id_akun); // 's' karena parameter adalah string

            if ($stmt->execute()) {
                set_flash_message("Akun berhasil dihapus!", "success");
            } else {
                set_flash_message("Gagal menghapus akun: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Akun tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar akun
