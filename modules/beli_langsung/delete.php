<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada ID transaksi yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_transaksi_to_delete = sanitize_input(trim($_GET['id']));

    // Mulai transaksi database untuk memastikan konsistensi
    $conn->begin_transaction();
    try {
        // Ambil detail transaksi yang akan dihapus untuk memastikan ini adalah "beli_langsung"
        // Kita tidak perlu jumlah_dibayar lama karena tidak ada pemesanan yang perlu di-revert sisanya.
        $sql_get_transaksi = "SELECT id_pesan FROM transaksi WHERE id_transaksi = ?";
        if ($stmt_get = $conn->prepare($sql_get_transaksi)) {
            $stmt_get->bind_param("s", $id_transaksi_to_delete);
            if (!$stmt_get->execute()) {
                throw new Exception("Gagal mengambil detail transaksi untuk dihapus: " . $stmt_get->error);
            }
            $stmt_get->bind_result($related_id_pesan);
            $stmt_get->fetch();
            $stmt_get->close();

            // Penting: Pastikan ini memang transaksi beli langsung (id_pesan IS NULL)
            // Jika tidak, mungkin ada kesalahan dan ini harus ditangani di modul transaksi utama.
            if ($related_id_pesan !== NULL) {
                throw new Exception("Transaksi bukan pembelian langsung. Harap hapus melalui modul Transaksi utama.");
            }
        } else {
            throw new Exception("Error prepared statement (get transaksi): " . $conn->error);
        }


        // 1. Hapus entri dari tabel kas_masuk yang terkait
        $sql_delete_kas_masuk = "DELETE FROM kas_masuk WHERE id_transaksi = ?";
        if ($stmt_kas_masuk_delete = $conn->prepare($sql_delete_kas_masuk)) {
            $stmt_kas_masuk_delete->bind_param("s", $id_transaksi_to_delete);
            if (!$stmt_kas_masuk_delete->execute()) {
                throw new Exception("Gagal menghapus entri kas masuk terkait: " . $stmt_kas_masuk_delete->error);
            }
            $stmt_kas_masuk_delete->close();
        } else {
            throw new Exception("Error prepared statement (delete kas_masuk): " . $conn->error);
        }

        // 2. Hapus entri dari tabel transaksi (pembelian langsung)
        // Tambahkan kondisi id_pesan IS NULL untuk memastikan hanya menghapus beli_langsung
        $sql_delete_transaksi = "DELETE FROM transaksi WHERE id_transaksi = ? AND id_pesan IS NULL";
        if ($stmt_transaksi_delete = $conn->prepare($sql_delete_transaksi)) {
            $stmt_transaksi_delete->bind_param("s", $id_transaksi_to_delete);
            if (!$stmt_transaksi_delete->execute()) {
                throw new Exception("Gagal menghapus transaksi: " . $stmt_transaksi_delete->error);
            }
            $stmt_transaksi_delete->close();
        } else {
            throw new Exception("Error prepared statement (delete transaksi): " . $conn->error);
        }

        // Tidak ada langkah 3 (perbarui tabel pemesanan) karena ini beli_langsung (id_pesan IS NULL)

        // Commit transaksi jika semua berhasil
        $conn->commit();
        set_flash_message("Transaksi pembelian langsung dan entri kas masuk terkait berhasil dihapus!", "success");
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        set_flash_message("Error saat menghapus pembelian langsung: " . $e->getMessage(), "error");
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Transaksi tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar pembelian langsung