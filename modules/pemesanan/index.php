<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data pemesanan dari database, join dengan customer
$orders = [];

// Cek koneksi database
if (!$conn) {
    set_flash_message("Koneksi database gagal: " . mysqli_connect_error(), "error");
} else {
    // --- START MODIFIKASI: Sesuaikan query SQL dengan struktur tabel pemesanan yang baru ---
    // Menghapus JOIN ke transaksi dan akun karena kolom-kolom tersebut tidak lagi di pemesanan
    // Menggunakan total_tagihan_keseluruhan dan keterangan yang baru
    $sql = "SELECT p.*, c.nama_customer
            FROM pemesanan p
            JOIN customer c ON p.id_customer = c.id_customer
            ORDER BY p.tgl_pesan DESC";
    // --- END MODIFIKASI ---

    $result = $conn->query($sql);

    // Cek apakah query berhasil
    if ($result === false) {
        set_flash_message("Error query database: " . $conn->error, "error");
    } else if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Pemesanan</h1>
        <p class="text-gray-600 mb-6">Kelola daftar pemesanan produk Ampyang Cap Garuda.</p>

        <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
            <a href="add.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition mb-6">
                <span class="flex items-center gap-2">
                    <span>➕</span> Tambah Pemesanan Baru
                </span>
            </a>
        <?php endif; ?>

        <?php if (empty($orders)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                <p class="font-medium">Belum ada data pemesanan yang tersedia.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Pesan</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Kirim</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <?php /* --- START MODIFIKASI: Hapus Akun dan Qty, Ganti Total, Tambah Keterangan --- */ ?>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Total Keseluruhan</th>
                            <?php /* --- END MODIFIKASI --- */ ?>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">DP</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-2 py-1 border-b text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($orders as $order) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 py-1 text-sm"><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo date('d/m/Y', strtotime($order['tgl_pesan'])); ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo !empty($order['tgl_kirim']) ? date('d/m/Y', strtotime($order['tgl_kirim'])) : '-'; ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                                <?php /* --- START MODIFIKASI: Tampilkan Total Keseluruhan dan Keterangan --- */ ?>
                                <td class="px-2 py-1 text-sm"><?php echo format_rupiah($order['total_tagihan_keseluruhan']); ?></td>
                                <?php /* --- END MODIFIKASI --- */ ?>
                                <td class="px-2 py-1 text-sm"><?php echo format_rupiah($order['uang_muka']); ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo format_rupiah($order['sisa']); ?></td>
                                <td class="px-2 py-1 text-sm">
                                    <span class="px-1 py-0 text-xs rounded <?php echo ($order['sisa'] == 0) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ($order['sisa'] == 0) ? 'Lunas' : 'Belum'; ?>
                                    </span>
                                </td>
                                <td class="px-2 py-1 text-sm"><?php echo htmlspecialchars($order['keterangan'] ?? '-'); ?></td>
                                <td class="px-2 py-1 text-sm text-center">
                                    <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                                        <div class="flex justify-center space-x-1">
                                            <a href="edit.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>"
                                                class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 transition">
                                                Edit
                                            </a>
                                            <a href="delete.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>"
                                                class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition"
                                                onclick="return confirm('Apakah Anda yakin ingin menghapus pemesanan ini?');">
                                                Hapus
                                            </a>
                                        </div>
                                    <?php else : ?>
                                        <span class="text-gray-400 text-xs">-</span>
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
?>