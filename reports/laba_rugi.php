<?php
// Sertakan header
require_once '../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

// Jika tanggal belum diset, gunakan bulan ini sebagai default
if (empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$total_pendapatan = 0;
$biaya_bahan_baku = 0;
$biaya_produksi = 0;
$biaya_pengemasan = 0;
$biaya_transportasi = 0;
$biaya_lain_lain = 0;
$total_operasional = 0;
$laba_rugi = 0;

// Query untuk total pendapatan (kas masuk)
$sql_pendapatan = "SELECT SUM(jumlah) AS total FROM kas_masuk WHERE tgl_kas_masuk BETWEEN ? AND ?";
$stmt_pendapatan = $conn->prepare($sql_pendapatan);
if ($stmt_pendapatan) {
    $stmt_pendapatan->bind_param("ss", $start_date, $end_date);
    $stmt_pendapatan->execute();
    $stmt_pendapatan->bind_result($total_pendapatan);
    $stmt_pendapatan->fetch();
    $stmt_pendapatan->close();
}

// Query untuk biaya berdasarkan nama akun
$sql_biaya = "SELECT a.nama_akun, SUM(kk.jumlah) AS total 
              FROM kas_keluar kk 
              JOIN akun a ON kk.id_akun = a.id_akun 
              WHERE kk.tgl_kas_keluar BETWEEN ? AND ? 
              GROUP BY a.nama_akun";
$stmt_biaya = $conn->prepare($sql_biaya);
if ($stmt_biaya) {
    $stmt_biaya->bind_param("ss", $start_date, $end_date);
    $stmt_biaya->execute();
    $result_biaya = $stmt_biaya->get_result();
    while ($row = $result_biaya->fetch_assoc()) {
        $nama_akun = strtolower($row['nama_akun']);
        if (strpos($nama_akun, 'bahan') !== false) {
            $biaya_bahan_baku += $row['total'];
        } elseif (strpos($nama_akun, 'produksi') !== false || strpos($nama_akun, 'gas') !== false || strpos($nama_akun, 'listrik') !== false) {
            $biaya_produksi += $row['total'];
        } elseif (strpos($nama_akun, 'kemas') !== false) {
            $biaya_pengemasan += $row['total'];
        } elseif (strpos($nama_akun, 'transport') !== false) {
            $biaya_transportasi += $row['total'];
        } else {
            $biaya_lain_lain += $row['total'];
        }
    }
    $stmt_biaya->close();
}

$total_operasional = $biaya_bahan_baku + $biaya_produksi + $biaya_pengemasan + $biaya_transportasi + $biaya_lain_lain;
$laba_rugi = ($total_pendapatan ?? 0) - $total_operasional;

?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Laba Rugi Per Periode</h1>
        <p class="text-gray-600 mb-6">Lihat ringkasan laba atau rugi bersih berdasarkan periode tanggal.</p>

        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Awal:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Akhir:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <button type="submit"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Filter
                </button>
                <a href="laba_rugi.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <?php
        // Cek jika ada error dari SQL sebelumnya
        if (display_flash_message()) :
            // flash message sudah ditampilkan oleh display_flash_message(), jadi tidak perlu div tambahan
        ?>
        <?php else : // Jika tidak ada error SQL, tampilkan ringkasan laba rugi 
        ?>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-md max-w-lg mx-auto">
                <h2 class="text-xl font-bold text-gray-800 text-center mb-4">Laporan Laba Rugi</h2>
                <p class="text-gray-600 text-center mb-6"><strong><?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?></strong> s/d <strong><?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></strong></p>

                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-700 font-semibold">Total Pendapatan:</span>
                    <span class="text-green-600 font-bold"><?php echo format_rupiah($total_pendapatan ?? 0); ?></span>
                </div>

                <div class="mt-4 mb-2">
                    <span class="text-gray-700 font-semibold">Biaya Operasional:</span>
                </div>

                <div class="flex justify-between py-1 pl-4">
                    <span class="text-gray-600">Biaya Bahan Baku:</span>
                    <span class="text-red-600"><?php echo format_rupiah($biaya_bahan_baku); ?></span>
                </div>

                <div class="flex justify-between py-1 pl-4">
                    <span class="text-gray-600">Biaya Produksi (Gas/Listrik):</span>
                    <span class="text-red-600"><?php echo format_rupiah($biaya_produksi); ?></span>
                </div>

                <div class="flex justify-between py-1 pl-4">
                    <span class="text-gray-600">Biaya Pengemasan:</span>
                    <span class="text-red-600"><?php echo format_rupiah($biaya_pengemasan); ?></span>
                </div>

                <div class="flex justify-between py-1 pl-4">
                    <span class="text-gray-600">Biaya Transportasi:</span>
                    <span class="text-red-600"><?php echo format_rupiah($biaya_transportasi); ?></span>
                </div>

                <div class="flex justify-between py-1 pl-4 border-b border-gray-200 pb-2">
                    <span class="text-gray-600">Biaya Lain-lain:</span>
                    <span class="text-red-600"><?php echo format_rupiah($biaya_lain_lain); ?></span>
                </div>

                <div class="flex justify-between py-2 border-b border-gray-300 font-semibold">
                    <span class="text-gray-700">Total Operasional:</span>
                    <span class="text-red-600"><?php echo format_rupiah($total_operasional); ?></span>
                </div>

                <div class="flex justify-between pt-4 font-extrabold text-lg">
                    <span class="text-gray-800">Laba Rugi:</span>
                    <span class="<?php echo ($laba_rugi >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo format_rupiah($laba_rugi); ?> <?php echo ($laba_rugi >= 0) ? '(Laba)' : '(Rugi)'; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>