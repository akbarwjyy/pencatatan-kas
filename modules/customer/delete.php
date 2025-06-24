<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada ID customer yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_customer_to_delete = sanitize_input(trim($_GET['id']));

    // --- PENTING: Cek Relasi di Tabel 'pemesanan' dan 'transaksi' ---
    // Sesuai rancangan, tabel 'pemesanan' memiliki foreign key 'id_customer'.
    // Tabel 'transaksi' juga memiliki foreign key 'id_customer'.
    // Anda tidak boleh menghapus customer jika ada pemesanan atau transaksi yang terkait dengannya.
    // Ini untuk menjaga integritas data.

    $can_delete = true;
    $related_tables = [];

    // Cek di tabel pemesanan
    $check_pemesanan_sql = "SELECT COUNT(*) FROM pemesanan WHERE id_customer = ?";
    $stmt_check_pemesanan = $conn->prepare($check_pemesanan_sql);
    $stmt_check_pemesanan->bind_param("s", $id_customer_to_delete);
    $stmt_check_pemesanan->execute();
    $stmt_check_pemesanan->bind_result($count_pemesanan);
    $stmt_check_pemesanan->fetch();
    $stmt_check_pemesanan->close();

    if ($count_pemesanan > 0) {
        $can_delete = false;
        $related_tables[] = "Pemesanan";
    }

    // Cek di tabel transaksi
    $check_transaksi_sql = "SELECT COUNT(*) FROM transaksi WHERE id_customer = ?";
    $stmt_check_transaksi = $conn->prepare($check_transaksi_sql);
    $stmt_check_transaksi->bind_param("s", $id_customer_to_delete);
    $stmt_check_transaksi->execute();
    $stmt_check_transaksi->bind_result($count_transaksi);
    $stmt_check_transaksi->fetch();
    $stmt_check_transaksi->close();

    if ($count_transaksi > 0) {
        $can_delete = false;
        $related_tables[] = "Transaksi";
    }


    if (!$can_delete) {
        $message_tables = implode(" dan ", $related_tables);
        set_flash_message("Customer tidak dapat dihapus karena sudah terkait dengan data " . $message_tables . ". Harap hapus data terkait terlebih dahulu.", "error");
    } else {
        // Lanjutkan dengan penghapusan karena tidak ada relasi
        $sql = "DELETE FROM customer WHERE id_customer = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $id_customer_to_delete);

            if ($stmt->execute()) {
                set_flash_message("Customer berhasil dihapus!", "success");
            } else {
                set_flash_message("Gagal menghapus customer: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Customer tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar customer
