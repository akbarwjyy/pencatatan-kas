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
$total_operasional = 0;
$laba_rugi = 0;
$biaya_operasional = [];

// Ambil total pendapatan dari transaksi (bukan kas_masuk agar tidak double)
$sql_pendapatan = "SELECT SUM(jumlah_dibayar) AS total FROM transaksi WHERE tgl_transaksi BETWEEN ? AND ?";
$stmt_pendapatan = $conn->prepare($sql_pendapatan);
if ($stmt_pendapatan) {
    $stmt_pendapatan->bind_param("ss", $start_date, $end_date);
    $stmt_pendapatan->execute();
    $stmt_pendapatan->bind_result($total_pendapatan);
    $stmt_pendapatan->fetch();
    $stmt_pendapatan->close();
}

// Query biaya berdasarkan akun (hanya tampil akun yang dipakai)
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
        $biaya_operasional[] = $row;
        $total_operasional += $row['total'];
    }
    $stmt_biaya->close();
}

$laba_rugi = ($total_pendapatan ?? 0) - $total_operasional;
?>

<style>
    @media print {

        /* Sembunyikan elemen yang tidak diperlukan */
        header,
        nav,
        form,
        button,
        .bg-gray-50 {
            display: none !important;
        }

        /* Pastikan kontainer utama terlihat */
        .container {
            display: block !important;
            padding: 0;
            margin: 0;
        }

        /* Gaya untuk bagian laporan saat mencetak */
        .print-report {
            display: block !important;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            font-family: Arial, sans-serif;
            font-size: 12pt;
            border: 1px solid #000;
            box-shadow: none;
            background: #fff;
        }

        .print-report h2 {
            font-size: 18pt;
            text-align: center;
            margin-bottom: 5px;
            color: #000;
            font-weight: bold;
        }

        .print-report p {
            font-size: 12pt;
            text-align: center;
            margin-bottom: 20px;
            color: #000;
        }

        .print-report .report-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .print-report .border-b {
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }

        .print-report .bold {
            font-weight: bold;
        }

        .print-report .profit-row {
            background-color: #d4edda;
            /* Light green background for profit */
            padding: 5px 0;
            margin-top: 10px;
        }

        /* Atur margin halaman */
        @page {
            margin: 2cm;
        }
    }

    /* Gaya untuk tampilan layar */
    .report-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .border-b {
        border-bottom: 1px solid #000;
        padding-bottom: 5px;
    }

    .bold {
        font-weight: bold;
    }

    .profit-row {
        background-color: #d4edda;
        padding: 5px 0;
        margin-top: 10px;
    }
</style>

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

        <?php if (display_flash_message()) : ?>
        <?php else : ?>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-md max-w-lg mx-auto print-report">
                <h2 class="text-xl font-bold text-gray-800 text-center mb-4">Laporan Laba Rugi</h2>
                <p class="text-gray-600 text-center mb-6">Ampyang Cap Garuda<br>Periode: <?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?> s/d <?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></p>

                <div class="border-b">
                    <div class="report-row bold">
                        <span>PENDAPATAN</span>
                        <span><?php echo format_rupiah($total_pendapatan ?? 0); ?></span>
                    </div>
                </div>

                <div class="border-b mt-4">
                    <div class="report-row bold">
                        <span>PENGELUARAN</span>
                        <span></span>
                    </div>
                    <?php foreach ($biaya_operasional as $biaya) : ?>
                        <div class="report-row">
                            <span><?php echo htmlspecialchars($biaya['nama_akun']); ?></span>
                            <span><?php echo format_rupiah($biaya['total']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="report-row bold">
                        <span>Total Pengeluaran</span>
                        <span><?php echo format_rupiah($total_operasional); ?></span>
                    </div>
                </div>

                <div class="profit-row report-row bold">
                    <span>LABA / RUGI BERSIH</span>
                    <span><?php echo format_rupiah(abs($laba_rugi)); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
?>