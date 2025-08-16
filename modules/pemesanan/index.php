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
    // --- START MODIFIKASI: Tampilkan hanya pemesanan (exclude pembelian langsung) ---
    // Kita mengecualikan baris yang:
    // 1) memiliki flag pembelian_langsung = 1, atau
    // 2) memenuhi pola pembelian langsung: status Lunas, tgl_pesan = tgl_kirim, dan uang_muka = total_tagihan_keseluruhan
    $sql = "SELECT p.*, c.nama_customer
        FROM pemesanan p
        JOIN customer c ON p.id_customer = c.id_customer
        WHERE NOT (
            (
                p.status_pesanan = 'Lunas'
                AND p.tgl_pesan = p.tgl_kirim
                AND COALESCE(p.uang_muka, 0) = COALESCE(p.total_tagihan_keseluruhan, 0)
            )
        )
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
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Daftar Pemesanan</h1>
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
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Total Keseluruhan</th>
                            <?php /* --- START MODIFIKASI: Ganti 'DP' menjadi 'Total Qty' --- */ ?>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Total Qty</th>
                            <?php /* --- END MODIFIKASI --- */ ?>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-2 py-1 border-b text-left text-xs font-medium text-gray-500 uppercase">Status</th>

                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($orders as $order) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 py-1 text-sm"><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo date('d/m/Y', strtotime($order['tgl_pesan'])); ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo !empty($order['tgl_kirim']) ? date('d/m/Y', strtotime($order['tgl_kirim'])) : '-'; ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                                <td class="px-2 py-1 text-sm"><?php echo format_rupiah($order['total_tagihan_keseluruhan']); ?></td>
                                <?php /* --- START MODIFIKASI: Tampilkan 'total_quantity' --- */ ?>
                                <td class="px-2 py-1 text-sm"><?php echo htmlspecialchars($order['total_quantity']); ?></td>
                                <?php /* --- END MODIFIKASI --- */ ?>
                                <td class="px-2 py-1 text-sm"><?php echo format_rupiah($order['sisa']); ?></td>
                                <td class="px-2 py-1 text-sm">
                                    <span class="px-1 py-0 text-xs rounded <?php echo ($order['sisa'] == 0) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ($order['sisa'] == 0) ? 'Lunas' : 'Belum'; ?>
                                    </span>
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