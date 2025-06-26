<?php

// Konfigurasi Database
$host = "localhost"; // Biasanya 'localhost' jika database berada di server yang sama
$user = "root";      // Username database Anda (default XAMPP/WAMP adalah 'root')
$pass = "";          // Password database Anda (default XAMPP/WAMP adalah kosong)
$db_name = "db_ampyang"; // Nama database yang sudah Anda buat

// Buat koneksi database menggunakan MySQLi (objek oriented)
$conn = new mysqli($host, $user, $pass, $db_name);

// Periksa apakah koneksi berhasil
if ($conn->connect_error) {
    // Jika koneksi gagal, hentikan eksekusi script dan tampilkan pesan error
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set charset ke utf8mb4 untuk mendukung berbagai karakter (emoticon, dll.)
// Ini penting untuk mencegah masalah encoding data
$conn->set_charset("utf8mb4");

// Anda bisa menghapus baris di bawah ini setelah memastikan koneksi berhasil
// echo "Koneksi database berhasil!";