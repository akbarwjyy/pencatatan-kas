<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada ID pengguna yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_pengguna_to_delete = sanitize_input(trim($_GET['id']));

    // Cegah pengguna menghapus akun mereka sendiri
    if ($id_pengguna_to_delete === $_SESSION['user_id']) {
        set_flash_message("Anda tidak dapat menghapus akun Anda sendiri.", "error");
        redirect('index.php');
    }

    // Cek apakah pengguna ini memiliki relasi di tabel lain
    // Berdasarkan skema yang diberikan, tabel `pengguna` tidak memiliki relasi `id_pengguna`
    // sebagai foreign key di tabel lain. Namun, jika di masa depan `id_pengguna` ditambahkan
    // ke tabel seperti `pemesanan` atau `kas_masuk` untuk melacak siapa yang melakukan input,
    // maka Anda perlu menambahkan pengecekan relasi di sini sebelum menghapus.
    // Contoh placeholder jika ada relasi:
    // $check_relation_sql = "SELECT COUNT(*) FROM pemesanan WHERE id_pengguna = ?";
    // $stmt_check = $conn->prepare($check_relation_sql);
    // $stmt_check->bind_param("s", $id_pengguna_to_delete);
    // $stmt_check->execute();
    // $stmt_check->bind_result($count);
    // $stmt_check->fetch();
    // $stmt_check->close();
    // if ($count > 0) {
    //     set_flash_message("Pengguna tidak dapat dihapus karena sudah terkait dengan data pemesanan. Harap hapus data terkait terlebih dahulu.", "error");
    //     redirect('index.php');
    // }

    // Query untuk menghapus data pengguna
    $sql = "DELETE FROM pengguna WHERE id_pengguna = ?";

    // Gunakan prepared statement untuk keamanan
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_pengguna_to_delete);

        if ($stmt->execute()) {
            set_flash_message("Pengguna berhasil dihapus!", "success");
        } else {
            set_flash_message("Gagal menghapus pengguna: " . $stmt->error, "error");
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
    }
} else {
    set_flash_message("Permintaan tidak valid. ID Pengguna tidak ditemukan.", "error");
}

redirect('index.php'); // Selalu redirect kembali ke halaman daftar pengguna
