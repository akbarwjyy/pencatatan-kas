<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada ID kas masuk yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_kas_masuk_to_delete = sanitize_input(trim($_GET['id']));

    // Pertama, pastikan entri kas masuk ini TIDAK terkait dengan transaksi
    $check_transaksi_sql = "SELECT COUNT(*) FROM kas_masuk WHERE id_kas_masuk = ? AND id_transaksi IS NOT NULL";
    $stmt_check = $conn->prepare($check_transaksi_sql);
    $stmt_check->bind_param("s", $id_kas_masuk_to_delete);
    $stmt_check->execute();
    $stmt_check->bind_result($count_transaksi_related);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count_transaksi_related > 0) {
        set_flash_message("Kas Masuk tidak dapat dihapus karena terkait dengan Transaksi. Silakan hapus Transaksi terkait di modul Transaksi.", "error");
        redirect('index.php');
    }

    // Jika tidak terkait transaksi, lanjutkan penghapusan
    $sql = "DELETE FROM kas_masuk WHERE id_kas_masuk = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_kas_masuk_to_delete);

        if ($stmt->execute()) {
            set_flash_message("Kas Masuk berhasil dihapus!", "success");
        } else {
            set_flash_message("Gagal menghapus kas masuk: " . $stmt->error, "error");
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Kas Masuk tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar kas masuk
