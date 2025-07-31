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

    // Cek apakah akun ini memiliki relasi di tabel lain
    $can_delete = true;
    $error_message = "";

    // 1. Cek di tabel kas_keluar
    $check_kas_keluar_sql = "SELECT COUNT(*) FROM kas_keluar WHERE id_akun = ?";
    $stmt_check = $conn->prepare($check_kas_keluar_sql);
    $stmt_check->bind_param("s", $id_akun);
    $stmt_check->execute();
    $stmt_check->bind_result($count_kas_keluar);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count_kas_keluar > 0) {
        $can_delete = false;
        $error_message .= "- Terdapat {$count_kas_keluar} data di Kas Keluar\\n";
    }

    // 2. Cek di tabel transaksi
    $check_transaksi_sql = "SELECT COUNT(*) FROM transaksi WHERE id_akun = ?";
    $stmt_check = $conn->prepare($check_transaksi_sql);
    $stmt_check->bind_param("s", $id_akun);
    $stmt_check->execute();
    $stmt_check->bind_result($count_transaksi);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count_transaksi > 0) {
        $can_delete = false;
        $error_message .= "- Terdapat {$count_transaksi} data di Transaksi\\n";
    }

    if (!$can_delete) {
        $message = "Akun tidak dapat dihapus karena masih digunakan di:\\n" . $error_message;
        $message .= "\\nHarap hapus data terkait terlebih dahulu.";
        set_flash_message($message, "error");
        redirect('index.php');
    }

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
} else {
    set_flash_message("Permintaan tidak valid. ID Akun tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar akun
