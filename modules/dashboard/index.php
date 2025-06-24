<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Ambil data ringkasan untuk dashboard

// 1. Total Kas Masuk Bulan Ini
$bulan_ini_awal = date('Y-m-01');
$bulan_ini_akhir = date('Y-m-t'); // t = jumlah hari di bulan ini
$total_kas_masuk_bulan_ini = 0;
$sql_kas_masuk_bulan_ini = "SELECT SUM(jumlah) AS total FROM kas_masuk WHERE tgl_kas_masuk BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql_kas_masuk_bulan_ini)) {
    $stmt->bind_param("ss", $bulan_ini_awal, $bulan_ini_akhir);
    $stmt->execute();
    $stmt->bind_result($total_kas_masuk_bulan_ini);
    $stmt->fetch();
    $stmt->close();
}

// 2. Total Kas Keluar Bulan Ini
$total_kas_keluar_bulan_ini = 0;
$sql_kas_keluar_bulan_ini = "SELECT SUM(jumlah) AS total FROM kas_keluar WHERE tgl_kas_keluar BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql_kas_keluar_bulan_ini)) {
    $stmt->bind_param("ss", $bulan_ini_awal, $bulan_ini_akhir);
    $stmt->execute();
    $stmt->bind_result($total_kas_keluar_bulan_ini);
    $stmt->fetch();
    $stmt->close();
}

// 3. Jumlah Pemesanan Belum Lunas
$jumlah_pemesanan_belum_lunas = 0;
$sql_pemesanan_belum_lunas = "SELECT COUNT(*) AS total FROM pemesanan WHERE sisa > 0";
$result_pemesanan_belum_lunas = $conn->query($sql_pemesanan_belum_lunas);
if ($result_pemesanan_belum_lunas && $row = $result_pemesanan_belum_lunas->fetch_assoc()) {
    $jumlah_pemesanan_belum_lunas = $row['total'];
}

// 4. Saldo Kas Saat Ini (Estimasi sederhana: Total Kas Masuk - Total Kas Keluar secara keseluruhan)
$total_all_kas_masuk = 0;
$sql_all_kas_masuk = "SELECT SUM(jumlah) AS total FROM kas_masuk";
$result_all_kas_masuk = $conn->query($sql_all_kas_masuk);
if ($result_all_kas_masuk && $row = $result_all_kas_masuk->fetch_assoc()) {
    $total_all_kas_masuk = $row['total'];
}

$total_all_kas_keluar = 0;
$sql_all_kas_keluar = "SELECT SUM(jumlah) AS total FROM kas_keluar";
$result_all_kas_keluar = $conn->query($sql_all_kas_keluar);
if ($result_all_kas_keluar && $row = $result_all_kas_keluar->fetch_assoc()) {
    $total_all_kas_keluar = $row['total'];
}
$saldo_kas_saat_ini = $total_all_kas_masuk - $total_all_kas_keluar;

?>

<h1>Dashboard Utama</h1>
<p>Selamat datang di Aplikasi Pencatatan Kas Industri Rumah Tangga Ampyang Cap Garuda.</p>
<p>Di halaman ini Anda dapat melihat ringkasan singkat kondisi keuangan dan operasional terkini.</p>

<div class="dashboard-summary">
    <div class="card">
        <h3>Kas Masuk Bulan Ini (<?php echo date('F Y'); ?>)</h3>
        <p class="amount-large"><?php echo format_rupiah($total_kas_masuk_bulan_ini); ?></p>
    </div>
    <div class="card">
        <h3>Kas Keluar Bulan Ini (<?php echo date('F Y'); ?>)</h3>
        <p class="amount-large"><?php echo format_rupiah($total_kas_keluar_bulan_ini); ?></p>
    </div>
    <div class="card">
        <h3>Pemesanan Belum Lunas</h3>
        <p class="amount-large"><?php echo htmlspecialchars($jumlah_pemesanan_belum_lunas); ?> Pesanan</p>
    </div>
    <div class="card">
        <h3>Estimasi Saldo Kas Total</h3>
        <p class="amount-large"><?php echo format_rupiah($saldo_kas_saat_ini); ?></p>
    </div>
</div>

<style>
    /* Styling khusus untuk Dashboard */
    .dashboard-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }

    .card {
        background-color: #e8f5e9;
        /* Light green */
        border-left: 5px solid #4CAF50;
        /* Green border */
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .card h3 {
        color: #333;
        font-size: 1.2em;
        margin-top: 0;
        margin-bottom: 10px;
    }

    .card p.amount-large {
        font-size: 2em;
        font-weight: bold;
        color: #4CAF50;
        margin: 0;
    }
</style>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>