<?php
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// MODIFIKASI: Query yang lebih akurat untuk mendeteksi pembelian langsung
// Pembelian langsung memiliki karakteristik:
// 1. status_pesanan = 'Lunas' 
// 2. metode_pembayaran = 'Tunai'
// 3. tgl_pesan = tgl_kirim = tgl_transaksi (pembelian pada hari yang sama)
// 4. uang_muka = total_tagihan_keseluruhan (dibayar penuh)

$sql = "SELECT tr.id_transaksi, tr.id_customer, tr.id_akun, tr.tgl_transaksi, tr.jumlah_dibayar, tr.metode_pembayaran, tr.keterangan, tr.total_tagihan, tr.sisa_pembayaran,
        c.nama_customer, a.nama_akun,
        p.total_quantity,
        p.keterangan AS pemesanan_keterangan,
        p.tgl_pesan, p.tgl_kirim, p.uang_muka, p.total_tagihan_keseluruhan, p.status_pesanan
        FROM transaksi tr
        LEFT JOIN customer c ON tr.id_customer = c.id_customer
        LEFT JOIN akun a ON tr.id_akun = a.id_akun
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        WHERE p.status_pesanan = 'Lunas' 
        AND tr.metode_pembayaran = 'Tunai'
        AND p.tgl_pesan = p.tgl_kirim 
        AND p.tgl_pesan = tr.tgl_transaksi
        AND p.uang_muka = p.total_tagihan_keseluruhan
        AND tr.sisa_pembayaran = 0
        ORDER BY tr.tgl_transaksi DESC";

$result = $conn->query($sql);

$beli_langsung_transactions = [];
// Tambahkan penanganan error query
if ($result === false) {
    set_flash_message("Error saat mengambil daftar pembelian langsung: " . $conn->error . ". Pastikan struktur database sudah sesuai.", "error");
    $beli_langsung_transactions = []; // Pastikan array kosong jika ada error
} else if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $beli_langsung_transactions[] = $row;
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Pembelian Langsung</h1>
        <p class="text-gray-600 mb-6">Kelola daftar transaksi penjualan tunai atau pembelian langsung.</p>

        <a href="add.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition mb-6">
            <span class="flex items-center gap-2">
                <span>➕</span> Tambah Pembelian Langsung
            </span>
        </a>

        <?php if (empty($beli_langsung_transactions)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Belum ada data pembelian langsung yang tersedia.</p>
                <p class="text-sm mt-1">Pembelian langsung adalah transaksi dengan status lunas, metode tunai, dan tanggal pesan = tanggal kirim = tanggal transaksi.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Transaksi</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Total Qty</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah Bayar</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $total_amount_paid = 0;
                        $total_items_sold = 0;
                        foreach ($beli_langsung_transactions as $transaction) :
                            $total_amount_paid += ($transaction['jumlah_dibayar'] ?? 0);
                            $total_items_sold += ($transaction['total_quantity'] ?? 0);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($transaction['id_transaksi']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['nama_customer'] ?? '-'); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['tgl_transaksi']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['total_quantity'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($transaction['jumlah_dibayar'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['metode_pembayaran']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['pemesanan_keterangan'] ?? $transaction['keterangan'] ?? '-'); ?></td>
                                <td class="px-3 py-2 text-sm text-center">
                                    <div class="flex justify-center space-x-1">
                                        <a href="edit.php?id=<?php echo htmlspecialchars($transaction['id_transaksi']); ?>"
                                            class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 transition">
                                            Edit
                                        </a>
                                        <a href="delete.php?id=<?php echo htmlspecialchars($transaction['id_transaksi']); ?>"
                                            class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?');">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="3" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total Keseluruhan:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo htmlspecialchars($total_items_sold); ?></td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_amount_paid); ?></td>
                            <td colspan="2" class="px-3 py-2 border-t"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>