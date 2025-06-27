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

$total_kas_masuk = 0;
$total_kas_keluar = 0;
$laba_rugi_bersih = 0;

// Query untuk total kas masuk
$sql_kas_masuk = "SELECT SUM(jumlah) AS total FROM kas_masuk WHERE tgl_kas_masuk BETWEEN ? AND ?";
$stmt_km = $conn->prepare($sql_kas_masuk);
if ($stmt_km === false) {
    set_flash_message("Error menyiapkan query kas masuk: " . $conn->error . " (Query: " . htmlspecialchars($sql_kas_masuk) . ")", "error");
} else {
    $stmt_km->bind_param("ss", $start_date, $end_date);
    $stmt_km->execute();
    $stmt_km->bind_result($total_kas_masuk);
    $stmt_km->fetch();
    $stmt_km->close();
}

// Query untuk total kas keluar
$sql_kas_keluar = "SELECT SUM(jumlah) AS total FROM kas_keluar WHERE tgl_kas_keluar BETWEEN ? AND ?";
$stmt_kk = $conn->prepare($sql_kas_keluar);
if ($stmt_kk === false) {
    set_flash_message("Error menyiapkan query kas keluar: " . $conn->error . " (Query: " . htmlspecialchars($sql_kas_keluar) . ")", "error");
} else {
    $stmt_kk->bind_param("ss", $start_date, $end_date);
    $stmt_kk->execute();
    $stmt_kk->bind_result($total_kas_keluar);
    $stmt_kk->fetch();
    $stmt_kk->close();
}

$laba_rugi_bersih = ($total_kas_masuk ?? 0) - ($total_kas_keluar ?? 0); // Use ?? 0 for potential NULLs from SUM

?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Laba Rugi</h1>
        <p class="text-gray-600 mb-6">Lihat ringkasan laba atau rugi bersih berdasarkan periode tanggal.</p>

        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Dari Tanggal:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Sampai Tanggal:</label>
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
                <h2 class="text-xl font-bold text-gray-800 text-center mb-4">Ringkasan Laba Rugi Periode:</h2>
                <p class="text-gray-600 text-center mb-4"><strong><?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?></strong> s/d <strong><?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></strong></p>

                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-700">Total Kas Masuk:</span>
                    <span class="text-green-600 font-bold"><?php echo format_rupiah($total_kas_masuk ?? 0); ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-700">Total Kas Keluar:</span>
                    <span class="text-red-600 font-bold"><?php echo format_rupiah($total_kas_keluar ?? 0); ?></span>
                </div>
                <hr class="my-4 border-t-2 border-gray-300">
                <div class="flex justify-between pt-4 font-extrabold text-lg">
                    <span class="text-gray-800">Laba/Rugi Bersih:</span>
                    <span class="<?php echo ($laba_rugi_bersih >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo format_rupiah($laba_rugi_bersih); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Sertakan footer
require_once '../layout/footer.php';
?>