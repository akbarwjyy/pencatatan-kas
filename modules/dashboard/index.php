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
    <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight mb-2">
        Selamat Datang di Dashboard!
    </h1>
    <p class="text-lg md:text-xl font-light opacity-90 mb-2">Aplikasi Pencatatan Kas Industri Rumah Tangga Ampyang Cap Garuda</p>
    <p class="text-sm md:text-base opacity-80">Pantau kondisi keuangan dan operasional bisnis Anda dengan mudah dan cepat.</p>
</div>

<!-- Ringkasan Keuangan -->
<div class="bg-white p-6 rounded-lg shadow-lg mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-2 border-b">
        📈 Ringkasan Keuangan Bulan <?php echo date('F Y'); ?>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Kas Masuk</h3>
                <span class="text-2xl">💰</span>
            </div>
            <p class="text-2xl font-bold text-emerald-600"><?php echo format_rupiah($total_kas_masuk_bulan_ini); ?></p>
            <p class="text-sm text-gray-500 mt-2">Total pemasukan bulan ini</p>
        </div>

        <div class="bg-gradient-to-br from-red-50 to-white border border-red-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Kas Keluar</h3>
                <span class="text-2xl">💸</span>
            </div>
            <p class="text-2xl font-bold text-red-600"><?php echo format_rupiah($total_kas_keluar_bulan_ini); ?></p>
            <p class="text-sm text-gray-500 mt-2">Total pengeluaran bulan ini</p>
        </div>

        <div class="bg-gradient-to-br from-yellow-50 to-white border border-yellow-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Pemesanan</h3>
                <span class="text-2xl">📝</span>
            </div>
            <p class="text-2xl font-bold text-yellow-600"><?php echo htmlspecialchars($jumlah_pemesanan_belum_lunas); ?></p>
            <p class="text-sm text-gray-500 mt-2">Pesanan belum lunas</p>
        </div>

        <div class="bg-gradient-to-br from-blue-50 to-white border border-blue-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Saldo Kas</h3>
                <span class="text-2xl">🏦</span>
            </div>
            <p class="text-2xl font-bold text-blue-600"><?php echo format_rupiah($saldo_kas_saat_ini); ?></p>
            <p class="text-sm text-gray-500 mt-2">Total saldo saat ini</p>
        </div>
    </div>
</div>


<style>
    @media (max-width: 768px) {
        .sidebar-mobile-hidden {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .main-content-mobile {
            margin-left: 0 !important;
        }
    }
</style>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>