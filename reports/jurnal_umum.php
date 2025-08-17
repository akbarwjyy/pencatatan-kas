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

$entries = [];
$total_debit = 0;
$total_kredit = 0;

// Query untuk Kas Masuk - menggunakan jumlah dari kas_masuk dan akun dari transaksi
$sql_kas_masuk = "SELECT 
                    km.tgl_kas_masuk AS tanggal, 
                    km.id_transaksi, 
                    'Kas Masuk' AS tipe, 
                    km.jumlah,
                    km.keterangan, 
                    a.nama_akun AS akun_asal, 
                    NULL AS akun_tujuan,
                    km.harga
                    -- PERBAIKAN: Hapus kuantitas karena kolom sudah tidak ada
                  FROM kas_masuk km
                  LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                  LEFT JOIN akun a ON tr.id_akun = a.id_akun -- Ambil akun dari transaksi terkait
                  WHERE km.tgl_kas_masuk BETWEEN ? AND ?";
$stmt_km = $conn->prepare($sql_kas_masuk);
if ($stmt_km === false) {
    set_flash_message("Error menyiapkan query Kas Masuk: " . $conn->error . " (Query: " . htmlspecialchars($sql_kas_masuk) . ")", "error");
} else {
    $stmt_km->bind_param("ss", $start_date, $end_date);
    $stmt_km->execute();
    $result_km = $stmt_km->get_result();
    while ($row = $result_km->fetch_assoc()) {
        // Jika akun_asal kosong, gunakan default 'Pendapatan'
        if (empty($row['akun_asal'])) {
            $row['akun_asal'] = 'Pendapatan';
        }
        $entries[] = $row;
    }
    $stmt_km->close();
}

// Query untuk Kas Keluar
$sql_kas_keluar = "SELECT kk.tgl_kas_keluar AS tanggal, kk.id_kas_keluar AS id_transaksi, 'Kas Keluar' AS tipe, kk.jumlah, kk.keterangan,
                           NULL AS akun_asal, a.nama_akun AS akun_tujuan,
                           kk.harga
                           -- PERBAIKAN: Hapus kuantitas untuk konsistensi
                   FROM kas_keluar kk
                   JOIN akun a ON kk.id_akun = a.id_akun
                   WHERE kk.tgl_kas_keluar BETWEEN ? AND ?";
$stmt_kk = $conn->prepare($sql_kas_keluar);
if ($stmt_kk === false) {
    set_flash_message("Error menyiapkan query Kas Keluar: " . $conn->error . " (Query: " . htmlspecialchars($sql_kas_keluar) . ")", "error");
} else {
    $stmt_kk->bind_param("ss", $start_date, $end_date);
    $stmt_kk->execute();
    $result_kk = $stmt_kk->get_result();
    while ($row = $result_kk->fetch_assoc()) {
        // Jika akun_tujuan kosong, gunakan default 'Beban'
        if (empty($row['akun_tujuan'])) {
            $row['akun_tujuan'] = 'Beban';
        }
        $entries[] = $row;
    }
    $stmt_kk->close();
}

// Urutkan semua entri berdasarkan tanggal
usort($entries, function ($a, $b) {
    return strtotime($a['tanggal']) - strtotime($b['tanggal']);
});
?>

<div class="container mx-auto px-4 py-8" id="report-container">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Jurnal Umum</h1>
        <p class="text-gray-600 mb-6">Lihat jurnal umum berdasarkan periode tanggal.</p>

        <style>
            .print-only {
                display: none;
                /* Sembunyikan saat tampilan normal */
            }

            @media print {

                /* Sembunyikan elemen yang tidak diperlukan */
                .print\:hidden,
                .print-hide,
                header,
                nav,
                .navbar,
                .sidebar,
                .breadcrumb,
                form,
                .form-container,
                .bg-gray-50,
                button {
                    display: none !important;
                }

                /* Sembunyikan judul pertama bawaan halaman */
                h1:first-of-type {
                    display: none !important;
                }

                /* Tampilkan elemen khusus print */
                .print-only {
                    display: block !important;
                }

                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                body {
                    font-family: Arial, sans-serif !important;
                    font-size: 12px !important;
                    line-height: 1.4 !important;
                    color: black !important;
                    background: white !important;
                    margin: 0 !important;
                    padding: 10px !important;
                }

                .container {
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                /* Judul utama */
                h2 {
                    font-size: 18px !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    margin-bottom: 3px !important;
                    color: black !important;
                }

                /* Periode */
                .periode {
                    font-size: 12px !important;
                    text-align: center !important;
                    margin-bottom: 15px !important;
                    color: black !important;
                }

                table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin-top: 10px !important;
                    font-size: 10px !important;
                    page-break-inside: avoid !important;
                }

                th,
                td {
                    border: 1px solid black !important;
                    padding: 4px 2px !important;
                    text-align: left !important;
                    vertical-align: top !important;
                    color: black !important;
                }

                th {
                    background-color: #f5f5f5 !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    font-size: 9px !important;
                }

                /* Kolom angka rata kanan */
                .text-right,
                td:nth-child(5),
                td:nth-child(6) {
                    text-align: right !important;
                    white-space: nowrap !important;
                    font-variant-numeric: tabular-nums;
                }

                /* Indentasi nama akun */
                .pl-8 {
                    padding-left: 20px !important;
                }

                .pl-16 {
                    padding-left: 40px !important;
                }

                /* Footer total */
                tfoot tr {
                    background-color: #f0f0f0 !important;
                    font-weight: bold !important;
                }

                tfoot td {
                    border-top: 2px solid black !important;
                    font-weight: bold !important;
                }

                tr {
                    page-break-inside: avoid !important;
                    page-break-after: auto !important;
                }

                @page {
                    margin: 1cm !important;
                }
            }
        </style>

        <!-- Judul Laporan -->
        <h2 class="print-only" style="text-align:center; font-size:20px; font-weight:bold; margin-bottom:5px;">
            Jurnal Umum
        </h2>

        <!-- Periode -->
        <div class="print-only" style="text-align:center; margin-bottom:15px; font-size:14px;">
            Periode
            <?= !empty($start_date) ? date('d-m-Y', strtotime($start_date)) : '-' ?>
            s/d
            <?= !empty($end_date) ? date('d-m-Y', strtotime($end_date)) : '-' ?>
        </div>



        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4 print-hide">
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
                <a href="jurnal_umum.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <?php if (empty($entries)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 print-hide">
                <p class="font-medium">Tidak ada entri jurnal ditemukan untuk periode ini.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Transaksi</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-6 py-3 border-b text-right text-xs font-medium text-gray-500 uppercase">Debet</th>
                            <th class="px-6 py-3 border-b text-right text-xs font-medium text-gray-500 uppercase">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $total_debit_final = 0;
                        $total_kredit_final = 0;
                        foreach ($entries as $entry) :
                            $debit_account = '';
                            $credit_account = '';
                            $debit_amount = 0;
                            $kredit_amount = 0;

                            if ($entry['tipe'] == 'Kas Masuk') {
                                $debit_account = "Kas";
                                $debit_amount = ($entry['jumlah'] ?? 0);
                                $credit_account = $entry['akun_asal'] ?? 'Pendapatan';
                                $kredit_amount = ($entry['jumlah'] ?? 0);
                                $keterangan_tambahan = "";
                                // PERBAIKAN: Hapus penggunaan kuantitas karena tidak ada lagi
                                // if (!empty($entry['kuantitas']) && !empty($entry['harga'])) {
                                //     $keterangan_tambahan = "(" . ($entry['kuantitas']) . " x " . format_rupiah($entry['harga']) . ")";
                                // }
                            } else { // Kas Keluar
                                $debit_account = $entry['akun_tujuan'] ?? 'Beban';
                                $debit_amount = ($entry['jumlah'] ?? 0);
                                $credit_account = "Kas";
                                $kredit_amount = ($entry['jumlah'] ?? 0);
                                $keterangan_tambahan = "";
                                // PERBAIKAN: Hapus penggunaan kuantitas karena tidak ada lagi
                                // if (!empty($entry['kuantitas']) && !empty($entry['harga'])) {
                                //     $keterangan_tambahan = "(" . ($entry['kuantitas']) . " x " . format_rupiah($entry['harga']) . ")";
                                // }
                            }
                            $total_debit_final += $debit_amount;
                            $total_kredit_final += $kredit_amount;
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-2 text-sm text-gray-500 align-top" rowspan="3"><?php echo htmlspecialchars($entry['id_transaksi'] ?? '-'); ?></td>
                                <td class="px-6 py-2 text-sm text-gray-500 align-top" rowspan="3"><?php echo !empty($entry['tanggal']) ? date('d/m/Y', strtotime($entry['tanggal'])) : '-'; ?></td>
                                <td class="px-6 py-1 text-sm text-gray-700 pl-8"><?php echo htmlspecialchars($debit_account); ?></td>
                                <td class="px-6 py-1 text-sm text-gray-900 text-right"><?php echo ($debit_amount > 0) ? format_rupiah($debit_amount) : '-'; ?></td>
                                <td class="px-6 py-1 text-sm text-gray-900 text-right">-</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-1 text-sm text-gray-700 pl-16"><?php echo htmlspecialchars($credit_account); ?></td>
                                <td class="px-6 py-1 text-sm text-gray-900 text-right">-</td>
                                <td class="px-6 py-1 text-sm text-gray-900 text-right"><?php echo ($kredit_amount > 0) ? format_rupiah($kredit_amount) : '-'; ?></td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-2 text-sm text-gray-900 font-italic"><?php echo htmlspecialchars($entry['keterangan']) . ' ' . ($keterangan_tambahan ?? ''); ?></td>
                                <td class="px-6 py-2 text-sm text-gray-900 text-right">-</td>
                                <td class="px-6 py-2 text-sm text-gray-900 text-right">-</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="3" class="px-6 py-3 border-t text-right text-xs uppercase text-gray-700"><strong>Total Jurnal:</strong></td>
                            <td class="px-6 py-3 border-t text-sm text-gray-900 text-right"><strong><?php echo format_rupiah($total_debit_final); ?></strong></td>
                            <td class="px-6 py-3 border-t text-sm text-gray-900 text-right"><strong><?php echo format_rupiah($total_kredit_final); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>