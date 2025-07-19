<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data pemesanan dan transaksi
$sql = "SELECT 
            COALESCE(tr.id_transaksi, CONCAT('PENDING-', p.id_pesan)) as id_transaksi,
            tr.tgl_transaksi,
            tr.jumlah_dibayar,
            tr.metode_pembayaran,
            p.id_pesan AS no_pesan,
            p.sub_total AS total_tagihan_pemesanan,
            p.sisa AS sisa_pemesanan,
            p.status_pesanan,
            p.tgl_pesan,
            c.id_customer,
            c.nama_customer,
            CASE 
                WHEN p.id_pesan IS NULL THEN 'Lunas'
                WHEN p.sisa = 0 THEN 'Lunas'
                WHEN tr.id_transaksi IS NULL THEN 'Belum Bayar'
                ELSE 'Belum Lunas'
            END AS status_pembayaran,
            CASE
                WHEN p.id_pesan IS NULL THEN COALESCE(tr.jumlah_dibayar, 0)
                ELSE p.sub_total
            END AS total_tagihan,
            COALESCE(tr.jumlah_dibayar, 0) as jumlah_dibayar
        FROM pemesanan p
        LEFT JOIN transaksi tr ON p.id_pesan = tr.id_pesan
        LEFT JOIN customer c ON p.id_customer = c.id_customer
        WHERE p.status_pesanan != 'Batal'
        UNION
        SELECT 
            tr.id_transaksi,
            tr.tgl_transaksi,
            tr.jumlah_dibayar,
            tr.metode_pembayaran,
            NULL as no_pesan,
            tr.jumlah_dibayar as total_tagihan_pemesanan,
            0 as sisa_pemesanan,
            'Lunas' as status_pesanan,
            tr.tgl_transaksi as tgl_pesan,
            c.id_customer,
            c.nama_customer,
            'Lunas' as status_pembayaran,
            tr.jumlah_dibayar as total_tagihan,
            tr.jumlah_dibayar
        FROM transaksi tr
        LEFT JOIN customer c ON tr.id_customer = c.id_customer
        WHERE tr.id_pesan IS NULL
        ORDER BY tgl_transaksi DESC";
$result = $conn->query($sql);

$transactions = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Transaksi</h1>
        <p class="text-gray-600 mb-6">Kelola daftar transaksi pembayaran dari pemesanan.</p>

        <a href="add.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition mb-6">
            <span class="flex items-center gap-2">
                <span>➕</span> Tambah Transaksi Baru
            </span>
        </a>

        <?php if (empty($transactions)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Belum ada data transaksi yang tersedia.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">No</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Transaksi</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">No Pesan</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Total Tagihan</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Dibayar</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php $no = 1;
                        foreach ($transactions as $transaction) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo $no++; ?></td>
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($transaction['id_transaksi']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['no_pesan'] ?? '-'); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['nama_customer']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($transaction['tgl_transaksi']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($transaction['total_tagihan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($transaction['jumlah_dibayar']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($transaction['sisa_pemesanan'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm">
                                    <?php
                                    switch ($transaction['status_pembayaran']) {
                                        case 'Lunas':
                                            $status_class = 'bg-green-100 text-green-800';
                                            break;
                                        case 'Belum Lunas':
                                            $status_class = 'bg-red-100 text-red-800';
                                            break;
                                        default:
                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($transaction['status_pembayaran']); ?>
                                    </span>
                                </td>
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
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>