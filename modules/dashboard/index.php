<?php
// Sertakan header
require_once '../../layout/header.php';

// Filter bulan dan tahun
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$bulan_angka = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$bulan = $tahun . '-' . $bulan_angka;

// Ambil data ringkasan untuk dashboard berdasarkan bulan yang dipilih
$bulan_ini_awal = $tahun . '-' . $bulan_angka . '-01';
$bulan_ini_akhir = date('Y-m-t', strtotime($bulan_ini_awal)); // t = jumlah hari di bulan ini
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

// 3. Jumlah Pemesanan Belum Lunas untuk bulan yang dipilih
$jumlah_pemesanan_belum_lunas = 0;
$sql_pemesanan_belum_lunas = "SELECT COUNT(*) AS total FROM pemesanan WHERE sisa > 0 AND tgl_pesan BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql_pemesanan_belum_lunas)) {
    $stmt->bind_param("ss", $bulan_ini_awal, $bulan_ini_akhir);
    $stmt->execute();
    $stmt->bind_result($jumlah_pemesanan_belum_lunas);
    $stmt->fetch();
    $stmt->close();
}

// 4. Saldo Kas untuk bulan yang dipilih
// Menghitung saldo awal bulan (semua transaksi sebelum bulan ini)
$saldo_awal_bulan = 0;

// Total kas masuk sebelum bulan ini
$sql_kas_masuk_sebelumnya = "SELECT SUM(jumlah) AS total FROM kas_masuk WHERE tgl_kas_masuk < ?";
if ($stmt = $conn->prepare($sql_kas_masuk_sebelumnya)) {
    $stmt->bind_param("s", $bulan_ini_awal);
    $stmt->execute();
    $stmt->bind_result($total_kas_masuk_sebelumnya);
    $stmt->fetch();
    $stmt->close();
}

// Total kas keluar sebelum bulan ini
$sql_kas_keluar_sebelumnya = "SELECT SUM(jumlah) AS total FROM kas_keluar WHERE tgl_kas_keluar < ?";
if ($stmt = $conn->prepare($sql_kas_keluar_sebelumnya)) {
    $stmt->bind_param("s", $bulan_ini_awal);
    $stmt->execute();
    $stmt->bind_result($total_kas_keluar_sebelumnya);
    $stmt->fetch();
    $stmt->close();
}

// Hitung saldo awal bulan
$saldo_awal_bulan = ($total_kas_masuk_sebelumnya ?: 0) - ($total_kas_keluar_sebelumnya ?: 0);

// Hitung saldo akhir bulan (saldo awal + transaksi bulan ini)
$saldo_kas_saat_ini = $saldo_awal_bulan + ($total_kas_masuk_bulan_ini ?: 0) - ($total_kas_keluar_bulan_ini ?: 0);

?>

<div class="bg-gradient-to-r from-blue-500 to-emerald-600 text-white p-8 rounded-lg shadow-xl mb-8">
    <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight mb-2">
        Selamat Datang di Dashboard!
    </h1>
    <p class="text-lg md:text-xl font-light opacity-90 mb-2">Aplikasi Pencatatan Kas Industri Rumah Tangga Ampyang Cap Garuda</p>
    <p class="text-sm md:text-base opacity-80">Pantau kondisi keuangan dan operasional bisnis Anda dengan mudah dan cepat.</p>
</div>

<!-- Ringkasan Keuangan -->
<div class="bg-white p-6 rounded-lg shadow-lg mb-8">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 pb-2 border-b">
        <h2 class="text-2xl font-bold text-gray-800">
            📈 Ringkasan Keuangan Bulan <?php echo date('F Y', strtotime($bulan_ini_awal)); ?>
        </h2>

        <form action="" method="get" class="flex flex-wrap items-center mt-4 md:mt-0">
            <div class="flex items-center mr-2 mb-2 md:mb-0">
                <label for="bulan" class="mr-2 text-gray-700">Bulan:</label>
                <select id="bulan" name="bulan" class="border rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($bulan_angka == str_pad($i, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-center mr-2 mb-2 md:mb-0">
                <label for="tahun" class="mr-2 text-gray-700">Tahun:</label>
                <select id="tahun" name="tahun" class="border rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--) : ?>
                        <option value="<?php echo $y; ?>" <?php echo ($tahun == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                Filter
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Kas Masuk</h3>
                <span class="text-2xl">💰</span>
            </div>
            <p class="text-2xl font-bold text-emerald-600"><?php echo format_rupiah($total_kas_masuk_bulan_ini); ?></p>
            <p class="text-sm text-gray-500 mt-2">Total pemasukan <?php echo date('F Y', strtotime($bulan_ini_awal)); ?></p>
        </div>

        <div class="bg-gradient-to-br from-red-50 to-white border border-red-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Kas Keluar</h3>
                <span class="text-2xl">💸</span>
            </div>
            <p class="text-2xl font-bold text-red-600"><?php echo format_rupiah($total_kas_keluar_bulan_ini); ?></p>
            <p class="text-sm text-gray-500 mt-2">Total pengeluaran <?php echo date('F Y', strtotime($bulan_ini_awal)); ?></p>
        </div>

        <div class="bg-gradient-to-br from-yellow-50 to-white border border-yellow-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Pemesanan</h3>
                <span class="text-2xl">📝</span>
            </div>
            <p class="text-2xl font-bold text-yellow-600"><?php echo htmlspecialchars($jumlah_pemesanan_belum_lunas); ?></p>
            <p class="text-sm text-gray-500 mt-2">Pesanan belum lunas <?php echo date('F Y', strtotime($bulan_ini_awal)); ?></p>
        </div>

        <div class="bg-gradient-to-br from-blue-50 to-white border border-blue-100 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-700">Saldo Kas</h3>
                <span class="text-2xl">🏦</span>
            </div>
            <p class="text-2xl font-bold text-blue-600"><?php echo format_rupiah($saldo_kas_saat_ini); ?></p>
            <p class="text-sm text-gray-500 mt-2">Saldo akhir <?php echo date('F Y', strtotime($bulan_ini_awal)); ?></p>
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
?>