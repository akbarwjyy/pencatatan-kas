<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada ID pemesanan yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_pesan_to_delete = sanitize_input(trim($_GET['id']));

    // --- PENTING: Cek Relasi di Tabel 'transaksi' ---
    // Pemesanan tidak dapat dihapus jika sudah ada transaksi yang terkait dengannya.
    // Ini untuk menjaga integritas data.

    $can_delete = true;

    // Cek di tabel transaksi
    $check_transaksi_sql = "SELECT COUNT(*) FROM transaksi WHERE id_pesan = ?";
    $stmt_check_transaksi = $conn->prepare($check_transaksi_sql);
    $stmt_check_transaksi->bind_param("s", $id_pesan_to_delete);
    $stmt_check_transaksi->execute();
    $stmt_check_transaksi->bind_result($count_transaksi);
    $stmt_check_transaksi->fetch();
    $stmt_check_transaksi->close();

    if ($count_transaksi > 0) {
        $can_delete = false;
        set_flash_message("Pemesanan tidak dapat dihapus karena sudah terkait dengan data Transaksi. Harap hapus transaksi terkait terlebih dahulu.", "error");
    }

    if ($can_delete) {
        // Lanjutkan dengan penghapusan karena tidak ada relasi
        $sql = "DELETE FROM pemesanan WHERE id_pesan = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $id_pesan_to_delete);

            if ($stmt->execute()) {
                set_flash_message("Pemesanan berhasil dihapus!", "success");
            } else {
                set_flash_message("Gagal menghapus pemesanan: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Pemesanan tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar pemesanan
