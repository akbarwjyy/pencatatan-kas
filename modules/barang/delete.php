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

// Cek apakah barang digunakan dalam pemesanan
$check_sql = "SELECT COUNT(*) as count FROM pemesanan WHERE id_barang = ?";
if ($stmt_check = $conn->prepare($check_sql)) {
    $stmt_check->bind_param("s", $id_barang);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        set_flash_message("Barang tidak dapat dihapus karena masih digunakan dalam pemesanan.", "error");
        redirect('index.php');
    }
    $stmt_check->close();
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
