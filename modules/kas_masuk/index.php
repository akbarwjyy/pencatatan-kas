<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data kas masuk dari database, join dengan transaksi dan akun
// Perhatikan: kas_masuk mungkin tidak selalu punya id_transaksi (jika diinput manual)
$sql = "SELECT km.*, tr.id_pesan, tr.jumlah_dibayar AS jumlah_transaksi, a.nama_akun
        FROM kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN akun a ON tr.id_akun = a.id_akun  -- Jika akun di transaksi yang diacu
        ORDER BY km.tgl_kas_masuk DESC";
$result = $conn->query($sql);

$cash_incomes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cash_incomes[] = $row;
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Kas Masuk</h1>
        <p class="text-gray-600 mb-6">Kelola daftar pemasukan kas di luar transaksi pemesanan (misalnya modal, dll).</p>

        <a href="add.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition mb-6">
            <span class="flex items-center gap-2">
                <span>➕</span> Tambah Kas Masuk Lainnya
            </span>
        </a>

        <?php if (empty($cash_incomes)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Belum ada data kas masuk yang tersedia.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Kas Masuk</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Transaksi (jika ada)</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Kas Masuk</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Akun Penerima (dari Transaksi)</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($cash_incomes as $income) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($income['id_kas_masuk']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['id_transaksi'] ?? '-'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['tgl_kas_masuk']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo format_rupiah($income['jumlah'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['keterangan']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($income['nama_akun'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 text-sm space-x-2">
                                    <?php
                                    // Aksi Edit/Delete hanya untuk kas masuk yang tidak terkait transaksi
                                    // Kas masuk dari transaksi diedit/dihapus via modul Transaksi
                                    if (empty($income['id_transaksi'])) :
                                    ?>
                                        <a href="edit.php?id=<?php echo htmlspecialchars($income['id_kas_masuk']); ?>"
                                            class="inline-block bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition">
                                            Edit
                                        </a>
                                        <a href="delete.php?id=<?php echo htmlspecialchars($income['id_kas_masuk']); ?>"
                                            class="inline-block bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus entri kas masuk ini?');">
                                            Hapus
                                        </a>
                                    <?php else : ?>
                                        <span class="inline-block bg-gray-300 text-gray-500 px-3 py-1 rounded cursor-not-allowed">
                                            Dikelola oleh Transaksi
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>