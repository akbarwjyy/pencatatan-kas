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

    // Tentukan grup akun terpilih (misal: '4' untuk Pendapatan, '6' untuk Kas)
    $selected_akun_group = substr($selected_akun, 0, 1);
    $is_pendapatan_akun = ($selected_akun_group == '4'); // Akun Pendapatan
    $is_kas_akun = ($selected_akun_group == '6'); // Akun Kas (sesuai penomoran di add.php)

    // --- START MODIFIKASI: Refaktor konstruksi query untuk saldo awal masuk ---
    $sql_saldo_awal_masuk = "SELECT SUM(km.jumlah) FROM kas_masuk km 
                             LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi";
    $where_saldo_awal_masuk = [];
    $params_saldo_awal_masuk = [];
    $types_saldo_awal_masuk = "";

    $where_saldo_awal_masuk[] = "km.tgl_kas_masuk < ?";
    $params_saldo_awal_masuk[] = $start_date;
    $types_saldo_awal_masuk .= "s";

    if (!$is_kas_akun) { // Jika bukan akun Kas, filter berdasarkan akun transaksi
        $where_saldo_awal_masuk[] = "tr.id_akun = ?";
        $params_saldo_awal_masuk[] = $selected_akun;
        $types_saldo_awal_masuk .= "s";
    }
    if ($is_pendapatan_akun) { // Jika akun pendapatan, hanya yang dari pesanan
        $where_saldo_awal_masuk[] = "tr.id_pesan IS NOT NULL";
    }
    $sql_saldo_awal_masuk .= " WHERE " . implode(" AND ", $where_saldo_awal_masuk);

    $stmt_saldo_awal_masuk = $conn->prepare($sql_saldo_awal_masuk);
    if ($stmt_saldo_awal_masuk === false) {
        set_flash_message("Error menyiapkan query saldo awal masuk: " . $conn->error . " (Query: " . htmlspecialchars($sql_saldo_awal_masuk) . ")", "error");
    } else {
        if (!empty($params_saldo_awal_masuk)) {
            $stmt_saldo_awal_masuk->bind_param($types_saldo_awal_masuk, ...$params_saldo_awal_masuk);
        }
        $stmt_saldo_awal_masuk->execute();
        $stmt_saldo_awal_masuk->bind_result($saldo_masuk_awal);
        $stmt_saldo_awal_masuk->fetch();
        $stmt_saldo_awal_masuk->close();
        $saldo_masuk_awal = $saldo_masuk_awal ?: 0;
    }
    // --- END MODIFIKASI ---

    // --- START MODIFIKASI: Refaktor konstruksi query untuk saldo awal keluar ---
    $sql_saldo_awal_keluar = "SELECT SUM(jumlah) FROM kas_keluar";
    $where_saldo_awal_keluar = [];
    $params_saldo_awal_keluar = [];
    $types_saldo_awal_keluar = "";

    $where_saldo_awal_keluar[] = "tgl_kas_keluar < ?";
    $params_saldo_awal_keluar[] = $start_date;
    $types_saldo_awal_keluar .= "s";

    if (!$is_kas_akun) { // Jika bukan akun Kas, filter berdasarkan id_akun
        $where_saldo_awal_keluar[] = "id_akun = ?";
        $params_saldo_awal_keluar[] = $selected_akun;
        $types_saldo_awal_keluar .= "s";
    }
    $sql_saldo_awal_keluar .= " WHERE " . implode(" AND ", $where_saldo_awal_keluar);

    $stmt_saldo_awal_keluar = $conn->prepare($sql_saldo_awal_keluar);
    if ($stmt_saldo_awal_keluar === false) {
        set_flash_message("Error menyiapkan query saldo awal keluar: " . $conn->error . " (Query: " . htmlspecialchars($sql_saldo_awal_keluar) . ")", "error");
    } else {
        if (!empty($params_saldo_awal_keluar)) {
            $stmt_saldo_awal_keluar->bind_param($types_saldo_awal_keluar, ...$params_saldo_awal_keluar);
        }
        $stmt_saldo_awal_keluar->execute();
        $stmt_saldo_awal_keluar->bind_result($saldo_keluar_awal);
        $stmt_saldo_awal_keluar->fetch();
        $stmt_saldo_awal_keluar->close();
        $saldo_keluar_awal = $saldo_keluar_awal ?: 0;
    }
    // --- END MODIFIKASI ---

    // Kas masuk adalah kredit dan kas keluar adalah debit, maka saldo awal adalah kas masuk dikurangi kas keluar
    $saldo_awal = ($saldo_masuk_awal ?? 0) - ($saldo_keluar_awal ?? 0);

    // --- START MODIFIKASI: Refaktor konstruksi query untuk entri kas masuk (Kredit) ---
    $sql_km_akun = "SELECT 
                    km.tgl_kas_masuk AS tanggal, 
                    km.keterangan, 
                    km.jumlah, 
                    'Kredit' AS tipe_saldo, 
                    tr.id_transaksi,
                    km.harga,
                    km.kuantitas,
                    p.id_pesan AS no_pesan, 
                    c.nama_customer AS nama_customer 
                    FROM kas_masuk km 
                    LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                    LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan 
                    LEFT JOIN customer c ON p.id_customer = c.id_customer";
    $where_km_akun = [];
    $params_km_akun = [];
    $types_km_akun = "";

    $where_km_akun[] = "km.tgl_kas_masuk BETWEEN ? AND ?";
    $params_km_akun[] = $start_date;
    $params_km_akun[] = $end_date;
    $types_km_akun .= "ss";

    if (!$is_kas_akun) { // Jika bukan akun Kas, filter berdasarkan akun transaksi
        $where_km_akun[] = "tr.id_akun = ?";
        $params_km_akun[] = $selected_akun;
        $types_km_akun .= "s";
    }
    if ($is_pendapatan_akun) { // Jika akun pendapatan, hanya yang dari pesanan
        $where_km_akun[] = "tr.id_pesan IS NOT NULL";
    }
    $sql_km_akun .= " WHERE " . implode(" AND ", $where_km_akun);
    $sql_km_akun .= " ORDER BY km.tgl_kas_masuk ASC"; // Urutkan agar saldo berjalan benar

    $stmt_km_akun = $conn->prepare($sql_km_akun);
    if ($stmt_km_akun === false) {
        set_flash_message("Error menyiapkan query entri kas masuk: " . $conn->error . " (Query: " . htmlspecialchars($sql_km_akun) . ")", "error");
    } else {
        if (!empty($params_km_akun)) {
            $stmt_km_akun->bind_param($types_km_akun, ...$params_km_akun);
        }
        $stmt_km_akun->execute();
        $result_km_akun = $stmt_km_akun->get_result();
        while ($row = $result_km_akun->fetch_assoc()) {
            $account_ledger_entries[] = $row;
        }
        $stmt_km_akun->close();
    }
    // --- END MODIFIKASI ---

    // --- START MODIFIKASI: Refaktor konstruksi query untuk entri kas keluar (Debit) ---
    $sql_kk_akun = "SELECT kk.tgl_kas_keluar AS tanggal, kk.keterangan, kk.jumlah, 'Debit' AS tipe_saldo, NULL AS id_transaksi, kk.harga, kk.kuantitas,
                    NULL AS no_pesan, NULL AS nama_customer 
                    FROM kas_keluar kk 
                    LEFT JOIN akun a ON kk.id_akun = a.id_akun";
    $where_kk_akun = [];
    $params_kk_akun = [];
    $types_kk_akun = "";

    $where_kk_akun[] = "kk.tgl_kas_keluar BETWEEN ? AND ?";
    $params_kk_akun[] = $start_date;
    $params_kk_akun[] = $end_date;
    $types_kk_akun .= "ss";

    if (!$is_kas_akun) { // Jika bukan akun Kas, filter berdasarkan id_akun
        $where_kk_akun[] = "kk.id_akun = ?";
        $params_kk_akun[] = $selected_akun;
        $types_kk_akun .= "s";
    }
    $sql_kk_akun .= " WHERE " . implode(" AND ", $where_kk_akun);
    $sql_kk_akun .= " ORDER BY kk.tgl_kas_keluar ASC"; // Urutkan agar saldo berjalan benar

    $stmt_kk_akun = $conn->prepare($sql_kk_akun);
    if ($stmt_kk_akun === false) {
        set_flash_message("Error menyiapkan query entri kas keluar: " . $conn->error . " (Query: " . htmlspecialchars($sql_kk_akun) . ")", "error");
    } else {
        if (!empty($params_kk_akun)) {
            $stmt_kk_akun->bind_param($types_kk_akun, ...$params_kk_akun);
        }
        $stmt_kk_akun->execute();
        $result_kk_akun = $stmt_kk_akun->get_result();
        while ($row = $result_kk_akun->fetch_assoc()) {
            $account_ledger_entries[] = $row;
        }
        $stmt_kk_akun->close();
    }
    // --- END MODIFIKASI ---

    // Urutkan semua entri berdasarkan tanggal
    usort($account_ledger_entries, function ($a, $b) {
        // Jika tanggal sama, prioritaskan Kredit (masuk) sebelum Debit (keluar)
        if ($a['tanggal'] == $b['tanggal']) {
            if ($a['tipe_saldo'] == 'Kredit' && $b['tipe_saldo'] == 'Debit') return -1;
            if ($a['tipe_saldo'] == 'Debit' && $b['tipe_saldo'] == 'Kredit') return 1;
            return 0;
        }
        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
    });
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Buku Besar Kas Per Periode</h1>
        <p class="text-gray-600 mb-6">Lihat pergerakan saldo untuk akun kas tertentu.</p>

        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4 print:hidden">
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

                /* Reset semua styling untuk print */
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                .print\:hidden {
                    display: none !important;
                }

                /* Reset container dan spacing */
                .container {
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    background: white !important;
                }

                .rounded-lg,
                .shadow-md {
                    border-radius: 0 !important;
                    box-shadow: none !important;
                }

                .px-4,
                .py-8,
                .p-6 {
                    padding: 0 !important;
                }

                /* Style untuk header laporan */
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid black;
                    padding-bottom: 10px;
                }

                .print-company-name {
                    font-size: 18px !important;
                    font-weight: bold !important;
                    margin-bottom: 5px;
                    color: black !important;
                }

                .print-report-title {
                    font-size: 16px !important;
                    font-weight: bold !important;
                    margin-bottom: 5px;
                    color: black !important;
                }

                .print-period {
                    font-size: 12px !important;
                    margin-bottom: 0;
                    color: black !important;
                }

                /* Style untuk tabel */
                .print-table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin-top: 20px;
                    font-size: 11px !important;
                }

                .print-table th,
                .print-table td {
                    border: 1px solid black !important;
                    padding: 4px 6px !important;
                    text-align: left !important;
                    color: black !important;
                }

                .print-table th {
                    background-color: #f0f0f0 !important;
                    font-weight: bold !important;
                    text-align: center !important;
                }

                .print-table .text-center {
                    text-align: center !important;
                }

                .print-table .text-right {
                    text-align: right !important;
                }

                /* Hide elemen yang tidak diperlukan untuk print */
                .mb-4,
                .mb-6,
                .text-gray-600 {
                    margin-bottom: 0 !important;
                    display: none !important;
                }

                /* Styling khusus untuk akun kas */
                .kas-section {
                    margin-bottom: 15px;
                }

                .kas-section-title {
                    font-weight: bold !important;
                    font-size: 12px !important;
                    margin-bottom: 5px;
                    text-transform: uppercase;
                    background-color: #e5e5e5 !important;
                    color: black !important;
                }

                .saldo-akhir {
                    font-weight: bold !important;
                    background-color: #f5f5f5 !important;
                }

                /* Fix untuk warna text di print */
                .text-gray-900,
                .text-gray-500,
                .text-gray-700 {
                    color: black !important;
                }
            }

            @media screen {
                .print-only {
                    display: none;
                }
            }

            @media print {
                .print-only {
                    display: block;
                }

                .screen-only {
                    display: none;
                }
            }
        </style>

        <!-- Header untuk print -->
        <div class="print-only print-header">
            <div class="print-company-name">Ampyang Cap Garuda</div>
            <div class="print-report-title">Laporan Buku Besar Kas</div>
            <?php if (!empty($selected_akun)) : ?>
                <div class="print-period">periode <?php echo date('d-m-Y', strtotime($start_date)); ?> s/d <?php echo date('d-m-Y', strtotime($end_date)); ?></div>
            <?php endif; ?>
        </div>

        <?php if (empty($selected_akun)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 screen-only">
                <p class="font-medium">Silakan pilih akun dan periode untuk melihat buku besar.</p>
            </div>
        <?php elseif (empty($account_ledger_entries) && $saldo_awal == 0) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 screen-only">
                <p class="font-medium">Tidak ada pergerakan kas ditemukan untuk akun "<?php echo htmlspecialchars($account_name); ?>" pada periode ini.</p>
            </div>
        <?php else : ?>
            <!-- Screen display -->
            <div class="screen-only">
                <h2 class="text-xl font-bold text-gray-800 mt-4 mb-2">Buku Besar Akun: <?php echo htmlspecialchars($account_name); ?></h2>
                <p class="text-gray-600 mb-6">Periode: <?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?> s/d <?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></p>
            </div>

            <!-- Print dan Screen display -->
            <div class="overflow-x-auto">
                <!-- Format untuk semua akun (disamakan dengan akun kas) -->
                <table class="min-w-full bg-white border border-gray-200 print-table">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Debet</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Kredit</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <!-- Section DEBIT (Menampilkan kas masuk sebagai Pendapatan) -->
                        <tr class="bg-gray-50">
                            <td colspan="5" class="px-3 py-2 text-sm font-bold text-gray-900 kas-section-title">DEBIT</td>
                        </tr>

                        <?php
                        $current_saldo = $saldo_awal;
                        $total_debet = 0;

                        // Tampilkan entries yang bertipe Kredit (kas masuk)
                        foreach ($account_ledger_entries as $entry) :
                            if ($entry['tipe_saldo'] == 'Kredit') :
                                $debit = $entry['jumlah'] ?? 0;
                                $total_debet += $debit;
                                $current_saldo += $debit; // Kas masuk menambah saldo
                        ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 text-center"><?php echo date('d-m-Y', strtotime($entry['tanggal'])); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($entry['keterangan'] ?? 'Pendapatan TP001'); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right">Rp <?php echo number_format($debit, 0, ',', '.'); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-center">-</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right">Rp <?php echo number_format(abs($current_saldo), 0, ',', '.'); ?></td>
                                </tr>
                        <?php
                            endif;
                        endforeach;
                        ?>

                        <!-- Total Pendapatan -->
                        <tr class="bg-gray-100">
                            <td class="px-3 py-2 text-sm font-bold text-gray-900"></td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900">Total Pendapatan</td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-right">Rp <?php echo number_format($total_debet, 0, ',', '.'); ?></td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-center">-</td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-right">Rp <?php echo number_format(abs($current_saldo), 0, ',', '.'); ?></td>
                        </tr>

                        <!-- Section KREDIT (Menampilkan kas keluar sebagai Pengeluaran) -->
                        <tr class="bg-gray-50">
                            <td colspan="5" class="px-3 py-2 text-sm font-bold text-gray-900 kas-section-title">KREDIT</td>
                        </tr>

                        <?php
                        $total_kredit = 0;

                        // Tampilkan entries yang bertipe Debit (kas keluar)
                        foreach ($account_ledger_entries as $entry) :
                            if ($entry['tipe_saldo'] == 'Debit') :
                                $kredit = $entry['jumlah'] ?? 0;
                                $total_kredit += $kredit;
                                $current_saldo -= $kredit; // Kas keluar mengurangi saldo
                        ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 text-center"><?php echo date('d-m-Y', strtotime($entry['tanggal'])); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($entry['keterangan'] ?? 'Pengeluaran Biaya Biaya Listrik BY001'); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-center">-</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right">Rp <?php echo number_format($kredit, 0, ',', '.'); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right">Rp <?php echo number_format(abs($current_saldo), 0, ',', '.'); ?></td>
                                </tr>
                        <?php
                            endif;
                        endforeach;
                        ?>

                        <!-- Total Pengeluaran -->
                        <tr class="bg-gray-100">
                            <td class="px-3 py-2 text-sm font-bold text-gray-900"></td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900">Total Pengeluaran</td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-center">-</td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-right">Rp <?php echo number_format($total_kredit, 0, ',', '.'); ?></td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-right">Rp <?php echo number_format(abs($current_saldo), 0, ',', '.'); ?></td>
                        </tr>

                        <!-- Saldo Akhir Kas -->
                        <tr class="bg-blue-100 saldo-akhir">
                            <td class="px-3 py-2 text-sm font-bold text-gray-900"></td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900">SALDO AKHIR KAS</td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-right">Rp <?php echo number_format(abs($current_saldo), 0, ',', '.'); ?></td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-center">-</td>
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 text-right">Rp <?php echo number_format(abs($current_saldo), 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>