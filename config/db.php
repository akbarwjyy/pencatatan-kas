<?php

// Konfigurasi Database
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "db_ampyang";

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
