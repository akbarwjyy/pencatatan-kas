<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Cek apakah ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message("ID Barang tidak valid.", "error");
    redirect('index.php');
}

$id_barang = sanitize_input($_GET['id']);

// Cek penggunaan barang di berbagai tabel
$can_delete = true;
$error_message = "";

// 1. Cek di tabel detail_pemesanan
$check_detail_sql = "SELECT COUNT(*) as count FROM detail_pemesanan WHERE id_barang = ?";
if ($stmt_check = $conn->prepare($check_detail_sql)) {
    $stmt_check->bind_param("s", $id_barang);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        $can_delete = false;
        $error_message .= "- Terdapat {$row['count']} data di Detail Pemesanan\\n";
    }
    $stmt_check->close();
}

// Jika ada data terkait yang mencegah penghapusan
if (!$can_delete) {
    $message = "Barang tidak dapat dihapus karena masih digunakan di:\\n" . $error_message;
    $message .= "\\nHarap hapus data pemesanan terkait terlebih dahulu.";
    set_flash_message($message, "error");
    redirect('index.php');
}

// Hapus barang dari database
$sql = "DELETE FROM barang WHERE id_barang = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $id_barang);

    if ($stmt->execute()) {
        set_flash_message("Barang berhasil dihapus!", "success");
    } else {
        set_flash_message("Gagal menghapus barang: " . $stmt->error, "error");
    }
    $stmt->close();
} else {
    set_flash_message("Error: " . $conn->error, "error");
}

redirect('index.php');
