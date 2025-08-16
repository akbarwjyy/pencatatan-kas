<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data kas keluar dari database, join dengan akun untuk menampilkan nama akun
// Pastikan kolom harga dan kuantitas ada di tabel
try {
    // Cek apakah kolom harga sudah ada
    $check_column_sql = "SHOW COLUMNS FROM kas_keluar LIKE 'harga'";
    $column_result = $conn->query($check_column_sql);
    if ($column_result->num_rows == 0) {
        // Kolom harga belum ada, tambahkan
        $conn->query("ALTER TABLE kas_keluar ADD COLUMN harga DECIMAL(15, 2) DEFAULT 0");
    }

    // Cek apakah kolom kuantitas sudah ada
    $check_column_sql = "SHOW COLUMNS FROM kas_keluar LIKE 'kuantitas'";
    $column_result = $conn->query($check_column_sql);
    if ($column_result->num_rows == 0) {
        // Kolom kuantitas belum ada, tambahkan
        $conn->query("ALTER TABLE kas_keluar ADD COLUMN kuantitas INT DEFAULT 0");
    }
} catch (Exception $e) {
    // Jika gagal menambahkan kolom, lanjutkan saja
}

$sql = "SELECT kk.*, a.nama_akun
        FROM kas_keluar kk
        JOIN akun a ON kk.id_akun = a.id_akun
        ORDER BY kk.tgl_kas_keluar DESC"; // Urutkan berdasarkan tanggal terbaru
$result = $conn->query($sql);

$cash_expenses = [];
$total_jumlah = 0;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cash_expenses[] = $row;
        $total_jumlah += (float)$row['jumlah'];
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Daftar Kas Keluar</h1>
        <p class="text-gray-600 mb-6">Kelola daftar pengeluaran kas operasional dan lainnya.</p>

        <a href="add.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition mb-6">
            <span class="flex items-center gap-2">
                <span>➕</span> Tambah Kas Keluar Baru
            </span>
        </a>

        <?php if (empty($cash_expenses)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Belum ada data kas keluar yang tersedia.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Nama Akun</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Kuantitas</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($cash_expenses as $expense) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($expense['id_kas_keluar']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($expense['tgl_kas_keluar'])); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($expense['nama_akun']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($expense['keterangan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($expense['harga'] ?? 1000); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo $expense['kuantitas'] ?? intval($expense['jumlah'] / 1000); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($expense['jumlah'] ?? 0); ?></td>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="6" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_jumlah); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>