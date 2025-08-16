<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Ambil filter tanggal dari GET
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date   = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';
// Jika tanggal belum diset, gunakan bulan ini sebagai default
if (empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Query awal - setelah implementasi detail_beli_langsung, transaksi beli langsung tidak lagi membuat dummy pemesanan
// Jadi kita hanya perlu mengambil semua pemesanan yang bukan transaksi langsung (yang memiliki id_pesan di transaksi)
$sql = "SELECT p.*, c.nama_customer
        FROM pemesanan p
        JOIN customer c ON p.id_customer = c.id_customer";

// Simpan parameter filter tambahan
$params = [];
$param_types = "";

// Tambah filter tanggal ke query
$where_added = false;
if (!empty($start_date)) {
    $sql .= " WHERE p.tgl_pesan >= ?";
    $params[] = $start_date;
    $param_types .= "s";
    $where_added = true;
}
if (!empty($end_date)) {
    if ($where_added) {
        $sql .= " AND p.tgl_pesan <= ?";
    } else {
        $sql .= " WHERE p.tgl_pesan <= ?";
    }
    $params[] = $end_date;
    $param_types .= "s";
}

$sql .= " ORDER BY p.tgl_pesan DESC";

// Eksekusi query
$orders = [];
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    set_flash_message("Error SQL: " . $conn->error, "error");
} else {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    $stmt->close();
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Pemesanan</h1>
        <p class="text-gray-600 mb-6">Lihat daftar pemesanan berdasarkan periode tanggal.</p>

        <!-- Form Filter - Disembunyikan saat cetak -->
        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4 print:hidden">
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
                <a href="pemesanan.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <!-- CSS untuk tampilan cetak yang diperbaiki -->
        <style>
            .print-only {
                display: none;
                /* Sembunyikan saat tampilan normal */
            }

            @media print {

                /* Sembunyikan elemen yang tidak diperlukan */
                .print\:hidden,
                header,
                nav,
                .navbar,
                .sidebar,
                .breadcrumb,
                form,
                .form-container {
                    display: none !important;
                }

                /* Sembunyikan judul pertama bawaan halaman */
                h1:first-of-type {
                    display: none !important;
                }

                /* Tampilkan hanya saat print */
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

                h2 {
                    font-size: 18px !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    margin-bottom: 3px !important;
                    color: black !important;
                }

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
                td:nth-child(6),
                td:nth-child(7),
                td:nth-child(8) {
                    text-align: right !important;
                    white-space: nowrap !important;
                    font-variant-numeric: tabular-nums;
                }

                tfoot tr {
                    background-color: #f0f0f0 !important;
                    font-weight: bold !important;
                }

                tfoot td {
                    border-top: 2px solid black !important;
                    font-weight: bold !important;
                }

                /* Status badge */
                .bg-green-100,
                .bg-yellow-100 {
                    background: white !important;
                    border: 1px solid black !important;
                    padding: 2px 4px !important;
                    font-size: 8px !important;
                }

                .text-green-800,
                .text-yellow-800 {
                    color: black !important;
                }

                .rounded-full {
                    border-radius: 0 !important;
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
        <h2 class="print-only">
            Laporan Pemesanan
        </h2>

        <!-- Periode -->
        <div class="print-only periode">
            Periode
            <?= !empty($start_date) ? date('d-m-Y', strtotime($start_date)) : '-' ?>
            s/d
            <?= !empty($end_date) ? date('d-m-Y', strtotime($end_date)) : '-' ?>
        </div>


        <?php if (empty($orders)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Tidak ada data pemesanan yang ditemukan untuk periode ini.</p>
            </div>

        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Pesan</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Kirim</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Nama Customer</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah Ampyang</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Total Harga</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Uang Muka</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Sisa Pembayaran</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Status Pembayaran</th>

                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $total_quantity = 0;
                        $total_uang_muka = 0;
                        $total_tagihan_keseluruhan = 0; // Mengubah nama variabel
                        $total_sisa = 0;
                        foreach ($orders as $order) :
                            $total_quantity += ($order['total_quantity'] ?? 0); // Menggunakan total_quantity
                            $total_uang_muka += ($order['uang_muka'] ?? 0);
                            $total_tagihan_keseluruhan += ($order['total_tagihan_keseluruhan'] ?? 0); // Menggunakan total_tagihan_keseluruhan
                            $total_sisa += ($order['sisa'] ?? 0);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($order['tgl_pesan'])); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($order['tgl_kirim'])); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['total_quantity'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900 currency"><?php echo format_rupiah($order['total_tagihan_keseluruhan'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900 currency"><?php echo format_rupiah($order['uang_muka'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900 currency"><?php echo format_rupiah($order['sisa'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo ($order['sisa'] == 0) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ($order['sisa'] == 0) ? 'Lunas' : 'Belum Lunas'; ?>
                                    </span>
                                </td>
            </div>
            </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>

        </tfoot>
        </table>
    </div>
<?php endif; ?>
</div>
</div>

<?php
?>