<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data transaksi dari database, join dengan pemesanan, customer, dan akun
$sql = "SELECT tr.*, p.id_pesan AS no_pesan, p.sub_total AS total_tagihan_pemesanan, p.sisa AS sisa_pemesanan, c.nama_customer, a.nama_akun
        FROM transaksi tr
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        LEFT JOIN customer c ON tr.id_customer = c.id_customer
        LEFT JOIN akun a ON tr.id_akun = a.id_akun
        ORDER BY tr.tgl_transaksi DESC";
$result = $conn->query($sql);

$transactions = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}
?>

<h1>Manajemen Transaksi</h1>
<p>Kelola daftar transaksi pembayaran dari pemesanan.</p>

<a href="add.php" class="btn">Tambah Transaksi Baru</a>

<?php if (empty($transactions)) : ?>
    <p>Belum ada data transaksi yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Transaksi</th>
                <th>No. Pesan</th>
                <th>Customer</th>
                <th>Akun Penerima</th>
                <th>Tgl Transaksi</th>
                <th>Jumlah Dibayar</th>
                <th>Metode Pembayaran</th>
                <th>Keterangan</th>
                <th>Total Tagihan Pemesanan</th>
                <th>Sisa Pembayaran Setelah Ini</th>
                <th>Status Pelunasan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $transaction) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($transaction['id_transaksi']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['no_pesan']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['nama_customer']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['nama_akun']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['tgl_transaksi']); ?></td>
                    <td><?php echo format_rupiah($transaction['jumlah_dibayar']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['metode_pembayaran']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['keterangan']); ?></td>
                    <td><?php echo format_rupiah($transaction['total_tagihan']); ?></td>
                    <td><?php echo format_rupiah($transaction['sisa_pembayaran']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['status_pelunasan']); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo htmlspecialchars($transaction['id_transaksi']); ?>" class="btn">Edit</a>
                        <a href="delete.php?id=<?php echo htmlspecialchars($transaction['id_transaksi']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Ini akan mempengaruhi data kas masuk dan pemesanan terkait!');">Hapus</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>