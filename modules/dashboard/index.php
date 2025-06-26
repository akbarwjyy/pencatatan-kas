<?php
// Sertakan header
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

<div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-8 rounded-lg shadow-xl mb-8">
    <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight mb-2 flex items-center gap-4">
        <!-- <span>🚀</span> -->
        Selamat Datang di Dashboard!
    </h1>
    <p class="text-lg md:text-xl font-light opacity-90 mb-2">Aplikasi Pencatatan Kas Industri Rumah Tangga Ampyang Cap Garuda</p>
    <p class="text-sm md:text-base opacity-80">Pantau kondisi keuangan dan operasional bisnis Anda dengan mudah dan cepat.</p>
</div>

<h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6 flex items-center gap-2">
    <span>📈</span> Ringkasan Keuangan Bulan Ini
</h2>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 dashboard-summary">
    <div class="card bg-gradient-to-br from-green-200 to-green-50 border-l-8 border-green-500 rounded-xl p-6 shadow-lg flex flex-col items-center">
        <span class="text-4xl mb-2">💰</span>
        <h3 class="text-lg font-bold text-gray-700 mb-1 text-center">Kas Masuk Bulan Ini<br><span class="text-xs font-normal">(<?php echo date('F Y'); ?>)</span></h3>
        <p class="amount-large text-2xl font-extrabold text-green-700 mt-2"><?php echo format_rupiah($total_kas_masuk_bulan_ini); ?></p>
    </div>
    <div class="card bg-gradient-to-br from-green-200 to-green-50 border-l-8 border-green-500 rounded-xl p-6 shadow-lg flex flex-col items-center">
        <span class="text-4xl mb-2">💸</span>
        <h3 class="text-lg font-bold text-gray-700 mb-1 text-center">Kas Keluar Bulan Ini<br><span class="text-xs font-normal">(<?php echo date('F Y'); ?>)</span></h3>
        <p class="amount-large text-2xl font-extrabold text-green-700 mt-2"><?php echo format_rupiah($total_kas_keluar_bulan_ini); ?></p>
    </div>
    <div class="card bg-gradient-to-br from-green-200 to-green-50 border-l-8 border-green-500 rounded-xl p-6 shadow-lg flex flex-col items-center">
        <span class="text-4xl mb-2">📝</span>
        <h3 class="text-lg font-bold text-gray-700 mb-1 text-center">Pemesanan Belum Lunas</h3>
        <p class="amount-large text-2xl font-extrabold text-green-700 mt-2"><?php echo htmlspecialchars($jumlah_pemesanan_belum_lunas); ?> Pesanan</p>
    </div>
    <div class="card bg-gradient-to-br from-green-200 to-green-50 border-l-8 border-green-500 rounded-xl p-6 shadow-lg flex flex-col items-center">
        <span class="text-4xl mb-2">🏦</span>
        <h3 class="text-lg font-bold text-gray-700 mb-1 text-center">Estimasi Saldo Kas Total</h3>
        <p class="amount-large text-2xl font-extrabold text-green-700 mt-2"><?php echo format_rupiah($saldo_kas_saat_ini); ?></p>
    </div>
</div>

<style>
    /* Styling legacy untuk fallback jika Tailwind tidak aktif */
    /* Hapus jika semua styling sudah menggunakan Tailwind */
    .dashboard-summary {
        margin-top: 30px;
    }

    .card h3 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .card p.amount-large {
        margin: 0;
    }
</style>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>