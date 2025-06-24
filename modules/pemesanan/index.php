<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data pemesanan dari database, join dengan customer dan akun untuk menampilkan nama
$sql = "SELECT p.*, c.nama_customer, a.nama_akun
        FROM pemesanan p
        JOIN customer c ON p.id_customer = c.id_customer
        JOIN akun a ON p.id_akun = a.id_akun
        ORDER BY p.tgl_pesan DESC"; // Urutkan berdasarkan tanggal pemesanan terbaru
$result = $conn->query($sql);

$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<h1>Manajemen Pemesanan</h1>
<p>Kelola daftar pemesanan produk Ampyang Cap Garuda.</p>

<?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
    <a href="add.php" class="btn">Tambah Pemesanan Baru</a>
<?php endif; ?>

<?php if (empty($orders)) : ?>
    <p>Belum ada data pemesanan yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Pesan</th>
                <th>Customer</th>
                <th>Akun</th>
                <th>Tgl Pesan</th>
                <th>Tgl Kirim</th>
                <th>Quantity</th>
                <th>Uang Muka</th>
                <th>Sub Total</th>
                <th>Sisa</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                    <td><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                    <td><?php echo htmlspecialchars($order['nama_akun']); ?></td>
                    <td><?php echo htmlspecialchars($order['tgl_pesan']); ?></td>
                    <td><?php echo htmlspecialchars($order['tgl_kirim']); ?></td>
                    <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                    <td><?php echo format_rupiah($order['uang_muka']); ?></td>
                    <td><?php echo format_rupiah($order['sub_total']); ?></td>
                    <td><?php echo format_rupiah($order['sisa']); ?></td>
                    <td>
                        <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                            <a href="edit.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>" class="btn">Edit</a>
                            <a href="delete.php?id=<?php echo htmlspecialchars($order['id_pesan']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus pemesanan ini? Pastikan tidak ada transaksi terkait!');">Hapus</a>
                        <?php else : ?>
                            <span class="btn" style="background-color: #ccc; cursor: not-allowed;">Tidak Ada Aksi</span>
                        <?php endif; ?>
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