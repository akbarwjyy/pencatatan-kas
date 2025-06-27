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

$sql = "SELECT km.*, tr.id_pesan, tr.metode_pembayaran, c.nama_customer, a.nama_akun
        FROM kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        LEFT JOIN customer c ON p.id_customer = c.id_customer
        LEFT JOIN akun a ON tr.id_akun = a.id_akun"; // Mengambil nama akun dari transaksi jika terkait

$where_clause = [];
$params = [];
$param_types = "";

if (!empty($start_date)) {
    $where_clause[] = "km.tgl_kas_masuk >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clause[] = "km.tgl_kas_masuk <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if (!empty($where_clause)) {
    $sql .= " WHERE " . implode(" AND ", $where_clause);
}

$sql .= " ORDER BY km.tgl_kas_masuk DESC";

// Inisialisasi $cash_incomes agar selalu ada, bahkan jika query gagal
$cash_incomes = [];

// Perbaikan: Tambahkan pengecekan apakah prepare() berhasil
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    // prepare() gagal, tampilkan error MySQLi
    set_flash_message("Error menyiapkan statement SQL: " . $conn->error . " (Query: " . htmlspecialchars($sql) . ")", "error");
} else {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cash_incomes[] = $row;
        }
    }
    $stmt->close();
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Kas Masuk</h1>
        <p class="text-gray-600 mb-6">Lihat daftar pemasukan kas berdasarkan periode tanggal.</p>

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
                <a href="kas_masuk.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <?php if (empty($cash_incomes)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Tidak ada data kas masuk yang ditemukan untuk periode ini.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Kas Masuk</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Kas Masuk</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Asal Transaksi</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Metode Pembayaran</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Akun Penerima (dari Transaksi)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $total_jumlah = 0;
                        foreach ($cash_incomes as $income) :
                            $total_jumlah += ($income['jumlah'] ?? 0);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($income['id_kas_masuk']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['tgl_kas_masuk']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo format_rupiah($income['jumlah'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['keterangan']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo !empty($income['id_transaksi']) ? 'ID Transaksi: ' . htmlspecialchars($income['id_transaksi']) . ' (Pemesanan: ' . htmlspecialchars($income['id_pesan'] ?? '-') . ')' : '-'; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['metode_pembayaran'] ?? '-'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['nama_customer'] ?? '-'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['nama_akun'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="3" class="px-6 py-3 border-t text-right text-xs uppercase text-gray-700">Total Kas Masuk:</td>
                            <td class="px-6 py-3 border-t text-sm text-gray-900"><?php echo format_rupiah($total_jumlah); ?></td>
                            <td colspan="4" class="px-6 py-3 border-t"></td>
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