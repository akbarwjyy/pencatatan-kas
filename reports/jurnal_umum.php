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

// Query untuk Kas Masuk
$sql_kas_masuk = "SELECT km.tgl_kas_masuk AS tanggal, km.id_transaksi, 'Kas Masuk' AS tipe, km.jumlah, km.keterangan, 
                         a.nama_akun AS akun_asal, NULL AS akun_tujuan
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
        $row['akun_transaksi'] = $row['akun_asal'] ?: 'Tidak Terkait Transaksi';
        $entries[] = $row;
    }
    $stmt_km->close();
}

// Query untuk Kas Keluar
$sql_kas_keluar = "SELECT kk.tgl_kas_keluar AS tanggal, kk.id_kas_keluar AS id_transaksi, 'Kas Keluar' AS tipe, kk.jumlah, kk.keterangan,
                           NULL AS akun_asal, a.nama_akun AS akun_tujuan
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
        $entries[] = $row;
    }
    $stmt_kk->close();
}

// Urutkan semua entri berdasarkan tanggal
usort($entries, function ($a, $b) {
    return strtotime($a['tanggal']) - strtotime($b['tanggal']);
});

?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Jurnal Umum Kas</h1>
        <p class="text-gray-600 mb-6">Menampilkan semua pergerakan kas (masuk dan keluar) secara kronologis.</p>

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
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
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
                                $credit_account = $entry['akun_transaksi'] ?? 'N/A';
                                $kredit_amount = ($entry['jumlah'] ?? 0);
                            } else { // Kas Keluar
                                $debit_account = $entry['akun_tujuan'] ?? 'N/A';
                                $debit_amount = ($entry['jumlah'] ?? 0);
                                $credit_account = "Kas";
                                $kredit_amount = ($entry['jumlah'] ?? 0);
                            }
                            $total_debit_final += $debit_amount;
                            $total_kredit_final += $kredit_amount;
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-2 text-sm text-gray-500 align-top" rowspan="3"><?php echo htmlspecialchars($entry['id_transaksi'] ?? '-'); ?></td>
                                <td class="px-6 py-2 text-sm text-gray-500 align-top" rowspan="3"><?php echo htmlspecialchars($entry['tanggal']); ?></td>
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
                                <td class="px-6 py-2 text-sm text-gray-900 text-right "><?php echo htmlspecialchars($entry['keterangan']); ?></td>
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
// Sertakan footer
require_once '../layout/footer.php';
?>