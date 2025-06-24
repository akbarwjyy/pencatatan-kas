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
        // 1. Ambil detail transaksi yang akan dihapus
        $sql_get_transaksi = "SELECT id_pesan, jumlah_dibayar FROM transaksi WHERE id_transaksi = ?";
        if ($stmt_get = $conn->prepare($sql_get_transaksi)) {
            $stmt_get->bind_param("s", $id_transaksi_to_delete);
            if (!$stmt_get->execute()) {
                throw new Exception("Gagal mengambil detail transaksi untuk dihapus: " . $stmt_get->error);
            }
            $stmt_get->bind_result($related_id_pesan, $related_jumlah_dibayar);
            $stmt_get->fetch();
            $stmt_get->close();

            if (empty($related_id_pesan)) {
                throw new Exception("Transaksi tidak ditemukan atau tidak memiliki pemesanan terkait.");
            }
        } else {
            throw new Exception("Error prepared statement (get transaksi): " . $conn->error);
        }

        // 2. Hapus entri dari tabel kas_masuk yang terkait
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

        // 3. Hapus entri dari tabel transaksi
        $sql_delete_transaksi = "DELETE FROM transaksi WHERE id_transaksi = ?";
        if ($stmt_transaksi_delete = $conn->prepare($sql_delete_transaksi)) {
            $stmt_transaksi_delete->bind_param("s", $id_transaksi_to_delete);
            if (!$stmt_transaksi_delete->execute()) {
                throw new Exception("Gagal menghapus transaksi: " . $stmt_transaksi_delete->error);
            }
            $stmt_transaksi_delete->close();
        } else {
            throw new Exception("Error prepared statement (delete transaksi): " . $conn->error);
        }

        // 4. Perbarui sisa pembayaran di tabel pemesanan yang terkait
        // Tambahkan kembali jumlah yang dibayar ke sisa pemesanan
        $sql_update_pemesanan = "UPDATE pemesanan SET sisa = sisa + ?, status_pelunasan = 'Belum Lunas' WHERE id_pesan = ?";
        if ($stmt_update_pemesanan = $conn->prepare($sql_update_pemesanan)) {
            $stmt_update_pemesanan->bind_param("is", $related_jumlah_dibayar, $related_id_pesan);
            if (!$stmt_update_pemesanan->execute()) {
                throw new Exception("Gagal memperbarui sisa pemesanan terkait: " . $stmt_update_pemesanan->error);
            }
            $stmt_update_pemesanan->close();
        } else {
            throw new Exception("Error prepared statement (update pemesanan): " . $conn->error);
        }

        // Commit transaksi jika semua berhasil
        $conn->commit();
        set_flash_message("Transaksi dan entri kas masuk terkait berhasil dihapus! Sisa pemesanan telah diperbarui.", "success");
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        set_flash_message("Error saat menghapus transaksi: " . $e->getMessage(), "error");
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Transaksi tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar transaksi
