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

$sql = "SELECT p.*, c.nama_customer
        FROM pemesanan p
        JOIN customer c ON p.id_customer = c.id_customer
        ";

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
                <a href="pemesanan.php"
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

                .px-3 {
                    padding-left: 4px !important;
                    padding-right: 4px !important;
                }

                .py-2 {
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

                .bg-green-100,
                .bg-yellow-100 {
                    background: white !important;
                }

                .text-green-800,
                .text-yellow-800 {
                    color: black !important;
                }

                .rounded-full {
                    border-radius: 0 !important;
                    border: 1px solid black !important;
                }

                thead {
                    display: none !important;
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
                        $total_sub_total = 0;
                        $total_sisa = 0;
                        foreach ($orders as $order) :
                            $total_quantity += ($order['quantity'] ?? 0);
                            $total_uang_muka += ($order['uang_muka'] ?? 0); // Use ?? 0 for potential NULLs
                            $total_sub_total += ($order['sub_total'] ?? 0); // Use ?? 0 for potential NULLs
                            $total_sisa += ($order['sisa'] ?? 0); // Use ?? 0 for potential NULLs
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['tgl_pesan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['tgl_kirim']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($order['quantity']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($order['sub_total'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($order['uang_muka'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($order['sisa'] ?? 0); ?></td>
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
                            <td colspan="4" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo htmlspecialchars($total_quantity); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_sub_total); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_uang_muka); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_sisa); ?></td>
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