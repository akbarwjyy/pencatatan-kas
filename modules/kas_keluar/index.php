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

$sql = "SELECT kk.*
        FROM kas_keluar kk
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
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Kas Keluar</h1>
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
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($cash_expenses as $expense) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($expense['id_kas_keluar']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($expense['tgl_kas_keluar']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php 
                                    // Gunakan nama akun tetap berdasarkan ID akun
                                    $id_akun = $expense['id_akun'] ?? '';
                                    switch ($id_akun) {
                                        case '1101': echo 'Kas'; break;
                                        case '1102': echo 'Bank'; break;
                                        case '1103': echo 'Piutang Usaha'; break;
                                        case '1104': echo 'Persediaan'; break;
                                        case '1105': echo 'Perlengkapan'; break;
                                        case '1201': echo 'Peralatan'; break;
                                        case '1202': echo 'Akumulasi Penyusutan Peralatan'; break;
                                        case '2101': echo 'Utang Usaha'; break;
                                        case '2102': echo 'Utang Bank'; break;
                                        case '3101': echo 'Modal Pemilik'; break;
                                        case '3102': echo 'Prive'; break;
                                        case '4001': echo 'Pendapatan'; break;
                                        case '5101': echo 'Beban Gaji'; break;
                                        case '5102': echo 'Beban Sewa'; break;
                                        case '5103': echo 'Beban Listrik dan Air'; break;
                                        case '5104': echo 'Beban Perlengkapan'; break;
                                        case '5105': echo 'Beban Penyusutan Peralatan'; break;
                                        case '5106': echo 'Beban Lain-lain'; break;
                                        default: echo htmlspecialchars($expense['nama_akun'] ?? 'N/A'); break;
                                    }
                                ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($expense['keterangan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($expense['harga'] ?? 1000); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo $expense['kuantitas'] ?? intval($expense['jumlah'] / 1000); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($expense['jumlah'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-center">
                                    <div class="flex justify-center space-x-1">
                                        <a href="edit.php?id=<?php echo htmlspecialchars($expense['id_kas_keluar']); ?>"
                                            class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 transition">
                                            Edit
                                        </a>
                                        <a href="delete.php?id=<?php echo htmlspecialchars($expense['id_kas_keluar']); ?>"
                                            class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus entri kas keluar ini?');">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="6" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_jumlah); ?></td>
                            <td class="px-3 py-2 border-t"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>