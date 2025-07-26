<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

// --- START MODIFIKASI: Ambil total_tagihan_keseluruhan dan total_quantity ---
$sql = "SELECT p.*, c.nama_customer
        FROM pemesanan p
        JOIN customer c ON p.id_customer = c.id_customer
        ";
// --- END MODIFIKASI ---

$where_clause = [];
$params = [];
$param_types = "";

if (!empty($start_date)) {
    $where_clause[] = "p.tgl_pesan >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clause[] = "p.tgl_pesan <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if (!empty($where_clause)) {
    $sql .= " WHERE " . implode(" AND ", $where_clause);
}

$sql .= " ORDER BY p.tgl_pesan DESC";

// Inisialisasi $orders agar selalu ada, bahkan jika query gagal
$orders = [];

// Perbaikan: Tambahkan pengecekan apakah prepare() berhasil
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    // prepare() gagal, tampilkan error MySQLi
    set_flash_message("Error menyiapkan statement SQL: " . $conn->error . " (Query: " . htmlspecialchars($sql) . ")", "error");
    // Tidak perlu memproses lebih lanjut jika statement tidak bisa disiapkan
} else {
    if (!empty($params)) {
        // Perbaikan: Gunakan call_user_func_array untuk bind_param jika PHP versi lama atau jika parameter dinamis
        // Tapi dengan ...$params, itu sudah cukup modern dan efektif.
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute(); // Baris ini akan error jika $stmt adalah false
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
            @media print {

                /* Sembunyikan semua elemen yang tidak perlu dicetak */
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

                /* Reset styling untuk tampilan cetak */
                * {
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
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

                .bg-white,
                .bg-gray-50,
                .bg-gray-100 {
                    background: white !important;
                }

                .shadow-md,
                .shadow-sm {
                    box-shadow: none !important;
                }

                .rounded-lg {
                    border-radius: 0 !important;
                }

                .px-4,
                .py-8,
                .p-6 {
                    padding: 0 !important;
                }

                .mb-6,
                .mb-4 {
                    margin-bottom: 10px !important;
                }

                /* Styling untuk judul laporan */
                h1 {
                    font-size: 18px !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    margin-bottom: 5px !important;
                    color: black !important;
                }

                .text-gray-600 {
                    font-size: 12px !important;
                    text-align: center !important;
                    margin-bottom: 15px !important;
                    color: black !important;
                }

                /* Styling untuk tabel */
                table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin-top: 10px !important;
                    font-size: 10px !important;
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

                /* Sembunyikan kolom aksi dan status pembayaran */
                th:last-child,
                td:last-child,
                th:nth-last-child(2),
                td:nth-last-child(2) {
                    display: none !important;
                }

                /* Styling untuk footer tabel (total) */
                tfoot tr {
                    background-color: #f0f0f0 !important;
                    font-weight: bold !important;
                }

                tfoot td {
                    border-top: 2px solid black !important;
                    font-weight: bold !important;
                }

                /* Styling untuk status pembayaran */
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

                /* Formatting untuk mata uang */
                .currency {
                    text-align: right !important;
                }

                /* Menghindari page break di tengah tabel */
                table {
                    page-break-inside: avoid !important;
                }

                tr {
                    page-break-inside: avoid !important;
                    page-break-after: auto !important;
                }

                /* Header untuk setiap halaman */
                @page {
                    margin: 1cm !important;

                    @top-center {
                        content: "Laporan Pemesanan" !important;
                    }
                }

                /* Pesan tidak ada data */
                .bg-yellow-100.border-l-4 {
                    background: white !important;
                    border: 1px solid black !important;
                    border-left: 4px solid black !important;
                    padding: 10px !important;
                    margin: 10px 0 !important;
                }

                .text-yellow-700 {
                    color: black !important;
                }
            }
        </style>

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
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase print:hidden">Aksi</th>
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
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['tgl_pesan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['tgl_kirim']); ?></td>
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
                                <td class="px-3 py-2 text-sm text-center print:hidden">
                                    <div class="flex justify-center space-x-1">
                                        <a href="../modules/pemesanan/edit.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>"
                                            class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 transition">
                                            Edit
                                        </a>
                                        <a href="../modules/pemesanan/delete.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>"
                                            class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus pemesanan ini?');">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="3" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo htmlspecialchars($total_quantity); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900 currency"><?php echo format_rupiah($total_tagihan_keseluruhan); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900 currency"><?php echo format_rupiah($total_uang_muka); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900 currency"><?php echo format_rupiah($total_sisa); ?></td>
                            <td class="px-3 py-2 border-t"></td>
                            <td class="px-3 py-2 border-t print:hidden"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>