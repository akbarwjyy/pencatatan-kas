<?php
// includes/helpers.php

/**
 * Fungsi untuk mengatur pesan flash (pesan yang hanya muncul sekali).
 * Memerlukan session_start() di awal file PHP yang menggunakannya.
 *
 * @param string $message Pesan yang akan disimpan.
 * @param string $type Tipe pesan (e.g., 'success', 'error', 'warning').
 */
function set_flash_message($message, $type = 'success')
{
    if (!isset($_SESSION)) {
        session_start(); // Pastikan session sudah dimulai
    }
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Fungsi untuk menampilkan pesan flash dan menghapusnya dari session.
 *
 * @return string HTML untuk menampilkan pesan, atau string kosong jika tidak ada pesan.
 */
function display_flash_message()
{
    if (!isset($_SESSION)) {
        session_start(); // Pastikan session sudah dimulai
    }
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['message'];
        $type = $_SESSION['flash_message']['type'];
        // Hapus pesan dari session setelah ditampilkan
        unset($_SESSION['flash_message']);
        return "<div class='message " . htmlspecialchars($type) . "'>" . htmlspecialchars($message) . "</div>";
    }
    return "";
}

/**
 * Fungsi untuk memformat angka menjadi format mata uang Rupiah.
 *
 * @param int|float $amount Jumlah uang.
 * @return string Format Rupiah (e.g., "Rp 1.000.000").
 */
function format_rupiah($amount)
{
    return "Rp " . number_format($amount, 0, ',', '.');
}
