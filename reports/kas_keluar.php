<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

$sql = "SELECT kk.*, a.nama_akun
        FROM kas_keluar kk
        JOIN akun a ON kk.id_akun = a.id_akun";

$where_clause = [];
$params = [];
$param_types = "";

if (!empty($start_date)) {
    $where_clause[] = "kk.tgl_kas_keluar >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clause[] = "kk.tgl_kas_keluar <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if (!empty($where_clause)) {
    $sql .= " WHERE " . implode(" AND ", $where_clause);
}

$sql .= " ORDER BY kk.tgl_kas_keluar DESC";

// Inisialisasi $cash_expenses agar selalu ada, bahkan jika query gagal
$cash_expenses = [];

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
            $cash_expenses[] = $row;
        }
    }
    $stmt->close();
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Kas Keluar</h1>
        <p class="text-gray-600 mb-6">Lihat daftar pengeluaran kas berdasarkan periode tanggal.</p>

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
                <a href="kas_keluar.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <?php if (empty($cash_expenses)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Tidak ada data kas keluar yang ditemukan untuk periode ini.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Kas Keluar</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Kas Keluar</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Akun</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $total_jumlah = 0;
                        foreach ($cash_expenses as $expense) :
                            $total_jumlah += ($expense['jumlah'] ?? 0);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($expense['id_kas_keluar']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($expense['tgl_kas_keluar']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($expense['nama_akun']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo format_rupiah($expense['jumlah'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($expense['keterangan']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="3" class="px-6 py-3 border-t text-right text-xs uppercase text-gray-700">Total Kas Keluar:</td>
                            <td class="px-6 py-3 border-t text-sm text-gray-900"><?php echo format_rupiah($total_jumlah); ?></td>
                            <td colspan="1" class="px-6 py-3 border-t"></td>
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