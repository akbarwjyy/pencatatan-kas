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
$selected_akun = isset($_GET['id_akun']) ? sanitize_input($_GET['id_akun']) : '';

// Jika tanggal belum diset, gunakan bulan ini sebagai default
if (empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Ambil daftar akun untuk dropdown filter
$accounts_filter = [];
$account_sql_filter = "SELECT id_akun, nama_akun FROM akun ORDER BY nama_akun ASC";
$account_result_filter = $conn->query($account_sql_filter);
if ($account_result_filter->num_rows > 0) {
    while ($row = $account_result_filter->fetch_assoc()) {
        $accounts_filter[] = $row;
    }
}

$account_ledger_entries = [];
$account_name = "Semua Akun";
$saldo_awal = 0; // Saldo awal periode yang dipilih

if (!empty($selected_akun)) {
    // Dapatkan nama akun terpilih
    foreach ($accounts_filter as $acc) {
        if ($acc['id_akun'] == $selected_akun) {
            $account_name = $acc['nama_akun'];
            break;
        }
    }

    // Hitung saldo awal untuk akun yang dipilih (total kas masuk sebelum start_date - total kas keluar sebelum start_date)
    $sql_saldo_awal_masuk = "SELECT SUM(jumlah) FROM kas_masuk km 
                             LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                             WHERE km.tgl_kas_masuk < ? AND (tr.id_akun = ? OR km.id_transaksi IS NULL)"; // Perlu penyesuaian jika kas_masuk manual tidak punya id_akun
    $stmt_saldo_awal_masuk = $conn->prepare($sql_saldo_awal_masuk);
    if ($stmt_saldo_awal_masuk === false) {
        set_flash_message("Error menyiapkan query saldo awal masuk: " . $conn->error, "error");
    } else {
        $stmt_saldo_awal_masuk->bind_param("ss", $start_date, $selected_akun);
        $stmt_saldo_awal_masuk->execute();
        $stmt_saldo_awal_masuk->bind_result($saldo_masuk_awal);
        $stmt_saldo_awal_masuk->fetch();
        $stmt_saldo_awal_masuk->close();
        $saldo_masuk_awal = $saldo_masuk_awal ?: 0;
    }


    $sql_saldo_awal_keluar = "SELECT SUM(jumlah) FROM kas_keluar WHERE tgl_kas_keluar < ? AND id_akun = ?";
    $stmt_saldo_awal_keluar = $conn->prepare($sql_saldo_awal_keluar);
    if ($stmt_saldo_awal_keluar === false) {
        set_flash_message("Error menyiapkan query saldo awal keluar: " . $conn->error, "error");
    } else {
        $stmt_saldo_awal_keluar->bind_param("ss", $start_date, $selected_akun);
        $stmt_saldo_awal_keluar->execute();
        $stmt_saldo_awal_keluar->bind_result($saldo_keluar_awal);
        $stmt_saldo_awal_keluar->fetch();
        $stmt_saldo_awal_keluar->close();
        $saldo_keluar_awal = $saldo_keluar_awal ?: 0;
    }

    $saldo_awal = ($saldo_masuk_awal ?? 0) - ($saldo_keluar_awal ?? 0);


    // Ambil entri untuk akun yang dipilih dalam periode
    // Kas Masuk
    $sql_km_akun = "SELECT km.tgl_kas_masuk AS tanggal, km.keterangan, km.jumlah, 'Debit' AS tipe_saldo, tr.id_transaksi
                    FROM kas_masuk km 
                    LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                    WHERE km.tgl_kas_masuk BETWEEN ? AND ? 
                    AND (tr.id_akun = ? OR km.id_transaksi IS NULL)"; // Menarik dari akun yang sama atau jika manual
    $stmt_km_akun = $conn->prepare($sql_km_akun);
    if ($stmt_km_akun === false) {
        set_flash_message("Error menyiapkan query entri kas masuk: " . $conn->error, "error");
    } else {
        $stmt_km_akun->bind_param("sss", $start_date, $end_date, $selected_akun);
        $stmt_km_akun->execute();
        $result_km_akun = $stmt_km_akun->get_result();
        while ($row = $result_km_akun->fetch_assoc()) {
            $account_ledger_entries[] = $row;
        }
        $stmt_km_akun->close();
    }


    // Kas Keluar
    $sql_kk_akun = "SELECT kk.tgl_kas_keluar AS tanggal, kk.keterangan, kk.jumlah, 'Kredit' AS tipe_saldo, NULL AS id_transaksi
                    FROM kas_keluar kk 
                    WHERE kk.tgl_kas_keluar BETWEEN ? AND ? AND kk.id_akun = ?";
    $stmt_kk_akun = $conn->prepare($sql_kk_akun);
    if ($stmt_kk_akun === false) {
        set_flash_message("Error menyiapkan query entri kas keluar: " . $conn->error, "error");
    } else {
        $stmt_kk_akun->bind_param("sss", $start_date, $end_date, $selected_akun);
        $stmt_kk_akun->execute();
        $result_kk_akun = $stmt_kk_akun->get_result();
        while ($row = $result_kk_akun->fetch_assoc()) {
            $account_ledger_entries[] = $row;
        }
        $stmt_kk_akun->close();
    }

    // Urutkan semua entri berdasarkan tanggal
    usort($account_ledger_entries, function ($a, $b) {
        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
    });
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Buku Besar Kas Per Periode</h1>
        <p class="text-gray-600 mb-6">Lihat pergerakan saldo untuk akun kas tertentu.</p>

        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Pilih Akun:</label>
                <select id="id_akun" name="id_akun" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Pilih Akun --</option>
                    <?php foreach ($accounts_filter as $akun_option) : ?>
                        <option value="<?php echo htmlspecialchars($akun_option['id_akun']); ?>"
                            <?php echo ($selected_akun == $akun_option['id_akun']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($akun_option['nama_akun']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                <a href="buku_besar_kas.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2 print:hidden">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <style>
            @media print {
                .print\:hidden {
                    display: none !important;
                }

                .bg-gray-50 {
                    background: white !important;
                }

                .shadow-md,
                .shadow-sm {
                    box-shadow: none !important;
                }

                .rounded-lg {
                    border-radius: 0 !important;
                }

                .container {
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .px-4,
                .py-8 {
                    padding: 0 !important;
                }

                .p-6 {
                    padding: 8px !important;
                }

                .mb-6,
                .mb-4 {
                    margin-bottom: 8px !important;
                }

                .text-gray-600 {
                    color: black !important;
                }

                .text-gray-800 {
                    color: black !important;
                }

                .text-gray-500 {
                    color: black !important;
                }

                .text-gray-900 {
                    color: black !important;
                }

                .bg-gray-100 {
                    background: #f5f5f5 !important;
                }

                .hover\:bg-gray-50:hover {
                    background: white !important;
                }

                .px-6 {
                    padding-left: 4px !important;
                    padding-right: 4px !important;
                }

                .py-3,
                .py-4 {
                    padding-top: 2px !important;
                    padding-bottom: 2px !important;
                }

                .text-xs {
                    font-size: 10px !important;
                }

                .text-sm {
                    font-size: 11px !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .min-w-full {
                    min-width: auto !important;
                }

                thead {
                    display: none !important;
                }
            }
        </style>

        <?php if (empty($selected_akun)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Silakan pilih akun dan periode untuk melihat buku besar.</p>
            </div>
        <?php elseif (empty($account_ledger_entries) && $saldo_awal == 0) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Tidak ada pergerakan kas ditemukan untuk akun "<?php echo htmlspecialchars($account_name); ?>" pada periode ini.</p>
            </div>
        <?php else : ?>
            <h2 class="text-xl font-bold text-gray-800 mt-4 mb-2">Buku Besar Akun: <?php echo htmlspecialchars($account_name); ?></h2>
            <p class="text-gray-600 mb-6">Periode: <?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?> s/d <?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></p>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Transaksi</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Nama Akun</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Debit</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Kredit</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">-</td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($account_name); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">-</td>
                            <td class="px-6 py-4 text-sm text-gray-900">-</td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo format_rupiah($saldo_awal); ?></td>
                        </tr>
                        <?php
                        $current_saldo = $saldo_awal;
                        foreach ($account_ledger_entries as $entry) :
                            $debit = 0;
                            $kredit = 0;

                            if ($entry['tipe_saldo'] == 'Debit') {
                                $debit = ($entry['jumlah'] ?? 0);
                                $current_saldo += $debit;
                            } else { // Kredit
                                $kredit = ($entry['jumlah'] ?? 0);
                                $current_saldo -= $kredit;
                            }
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($entry['tanggal']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($entry['id_transaksi'] ?? '-'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($account_name); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo ($debit > 0) ? format_rupiah($debit) : '-'; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo ($kredit > 0) ? format_rupiah($kredit) : '-'; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo format_rupiah($current_saldo); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="5" class="px-6 py-3 border-t text-right text-xs uppercase text-gray-700"><strong>Saldo Akhir:</strong></td>
                            <td class="px-6 py-3 border-t text-sm text-gray-900"><strong><?php echo format_rupiah($current_saldo); ?></strong></td>
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