<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data pemesanan dari database, join dengan customer dan akun untuk menampilkan nama
$orders = [];

// Cek koneksi database
if (!$conn) {
    set_flash_message("Koneksi database gagal: " . mysqli_connect_error(), "error");
} else {
    $sql = "SELECT p.*, c.nama_customer, a.nama_akun 
            FROM pemesanan p 
            JOIN customer c ON p.id_customer = c.id_customer 
            JOIN akun a ON p.id_akun = a.id_akun 
            ORDER BY p.tgl_pesan DESC";

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
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Pesan</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Akun</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Pesan</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tgl Kirim</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Uang Muka</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Sub Total</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($orders as $order) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($order['nama_akun']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($order['tgl_pesan']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($order['tgl_kirim']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($order['quantity']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo format_rupiah($order['uang_muka']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo format_rupiah($order['sub_total']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo format_rupiah($order['sisa']); ?></td>
                                <td class="px-4 py-2 text-sm space-x-1">
                                    <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                                        <a href="edit.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>"
                                            class="inline-block bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition">
                                            Edit
                                        </a>
                                        <a href="delete.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>"
                                            class="inline-block bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus pemesanan ini? Pastikan tidak ada transaksi terkait!');">
                                            Hapus
                                        </a>
                                    <?php else : ?>
                                        <span class="inline-block bg-gray-300 text-gray-500 px-3 py-1 rounded cursor-not-allowed">
                                            Tidak Ada Aksi
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